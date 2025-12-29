<?php

namespace App\Controllers\Clientzone;

use App\Controllers\General;

class CannedResponseController extends General
{
    public function __construct()
    {
        helper('jwt');
    }

    public function getCannedResponses()
    {
        if (!$this->isClientAuthenticated()) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Unauthorized'
            ], 401);
        }

        return $this->jsonResponse([]);
    }

    /**
     * Get canned responses for a specific API key
     */
    public function getCannedResponsesForApiKey()
    {
        if (!$this->isClientAuthenticated()) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Unauthorized'
            ], 401);
        }

        $currentUser = $this->getCurrentClientUser();
        $clientId = $this->getClientId();
        $apiKey = $this->request->getGet('api_key');

        if (!$apiKey) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'API key is required'
            ], 400);
        }

        // Validate API key ownership
        $keyExists = $this->apiKeyModel
            ->where('api_key', $apiKey)
            ->where('client_id', $clientId)
            ->first();

        if (!$keyExists) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Invalid API key'
            ], 403);
        }

        $responses = $this->cannedResponseModel->getResponsesForCreator(
            $apiKey,
            $currentUser['type'],
            $currentUser['id']
        );

        return $this->jsonResponse([
            'status' => 'success',
            'responses' => $responses
        ]);
    }

    /**
     * Get a single canned response (preview / variable replacement)
     */
    public function getCannedResponse($id)
    {
        if (!$this->isClientAuthenticated()) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Unauthorized'
            ], 401);
        }

        $currentUser = $this->getCurrentClientUser();

        if (!$this->cannedResponseModel->canUserManage(
            $id,
            $currentUser['type'],
            $currentUser['id']
        )) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Access denied'
            ], 403);
        }

        $response = $this->cannedResponseModel->find($id);

        if (!$response) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Response not found'
            ], 404);
        }

        // Optional session-based variable replacement
        $sessionId = $this->request->getGet('session_id');
        if ($sessionId) {
            $response = $this->processVariableReplacement($response, $sessionId);
        }

        return $this->jsonResponse([
            'status' => 'success',
            'response' => $response
        ]);
    }

    /**
     * Create / Update canned response
     */
    public function saveCannedResponse()
    {
        if (!$this->isClientAuthenticated()) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Unauthorized'
            ], 401);
        }

        try {
            $clientId = $this->getTokenClientId();

            if (!$clientId) {
                return $this->response->setStatusCode(401)->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid client token'
                ]);
            }

            $currentUser = $this->getCurrentClientUser();
            $clientId = $this->getClientId();

            $data = $this->request->getJSON(true);

            $id   = $this->sanitizeInput($data['id'] ?? null);
            $title = $this->sanitizeInput($data['title'] ?? '');
            $content = $this->sanitizeInput($data['content'] ?? '');
            $responseType = $this->sanitizeInput($data['response_type'] ?? 'plain_text');
            $apiActionType = $this->sanitizeInput($data['api_action_type'] ?? null);
            $apiKey = $this->sanitizeInput($data['api_key'] ?? '');
            $isActive = isset($data['is_active']) && $data['is_active'] ? 1 : 0;

            $apiParametersRaw = $data['api_parameters'] ?? null;
            // Validate api_parameters
            if ($responseType === 'api' && $apiParametersRaw !== null) {

                if (!is_array($apiParametersRaw)) {
                    return $this->jsonResponse([
                        'status' => 'error',
                        'error' => 'api_parameters must be an object'
                    ], 400);
                }

                // Encode array to JSON for DB storage
                $apiParameters = json_encode($apiParametersRaw, JSON_UNESCAPED_UNICODE);
            } else {
                $apiParameters = null;
            }

            if (!$title || !$apiKey) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'error' => 'Title and API key are required'
                ], 400);
            }

            // Validate API key ownership
            $keyExists = $this->apiKeyModel
                ->where('api_key', $apiKey)
                ->where('client_id', $clientId)
                ->first();

            if (!$keyExists) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'error' => 'Invalid API key'
                ], 403);
            }

            // Response-type validation
            if ($responseType === 'api') {
                if (!$apiActionType) {
                    return $this->jsonResponse([
                        'status' => 'error',
                        'error' => 'Custom endpoint is required'
                    ], 400);
                }

                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $apiActionType)) {
                    return $this->jsonResponse([
                        'status' => 'error',
                        'error' => 'Custom endpoint can only contain letters, numbers, hyphens, and underscores'
                    ], 400);
                }

                if ($apiParameters) {
                    json_decode($apiParameters, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $this->jsonResponse([
                            'status' => 'error',
                            'error' => 'API parameters must be valid JSON'
                        ], 400);
                    }
                }
            } else {
                if (!$content) {
                    return $this->jsonResponse([
                        'status' => 'error',
                        'error' => 'Content is required for plain text responses'
                    ], 400);
                }
            }

            // Duplicate title protection
            if ($this->cannedResponseModel->titleExistsForCreator(
                $title,
                $apiKey,
                $currentUser['type'],
                $currentUser['id'],
                $id
            )) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'error' => 'A canned response with this title already exists'
                ], 400);
            }

            $data = [
                'title' => $title,
                'content' => $content ?: '',
                'response_type' => $responseType,
                'api_action_type' => $responseType === 'api' ? $apiActionType : null,
                'api_parameters' => $responseType === 'api' ? $apiParameters : null,
                'api_key' => $apiKey,
                'created_by_user_type' => $currentUser['type'],
                'created_by_user_id' => $currentUser['id'],
                'is_active' => $isActive
            ];

            if ($id) {
                if (!$this->cannedResponseModel->canUserManage(
                    $id,
                    $currentUser['type'],
                    $currentUser['id']
                )) {
                    return $this->jsonResponse([
                        'status' => 'error',
                        'error' => 'Access denied'
                    ], 403);
                }

                $this->cannedResponseModel->update($id, $data);
                return $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Updated successfully'
                ]);
            }

            $this->cannedResponseModel->insert($data);
            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Created successfully'
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'CannedResponse save error: ' . $e->getMessage());
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Server error'
            ], 500);
        }
    }

    /**
     * Delete canned response
     */
    public function deleteCannedResponse()
    {
        if (!$this->isClientAuthenticated()) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Unauthorized'
            ], 401);
        }

        $data = $this->request->getJSON(true);
        $id   = $this->sanitizeInput($data['id'] ?? null);

        $currentUser = $this->getCurrentClientUser();

        if (!$id) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Response ID is required'
            ], 400);
        }

        if (!$this->cannedResponseModel->canUserManage(
            $id,
            $currentUser['type'],
            $currentUser['id']
        )) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Access denied. Response ID does not exist.'
            ], 403);
        }

        $this->cannedResponseModel->delete($id);

        return $this->jsonResponse([
            'status' => 'success',
            'message' => 'Deleted successfully'
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggleCannedResponseStatus()
    {
        if (!$this->isClientAuthenticated()) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Unauthorized'
            ], 401);
        }

        $data = $this->request->getJSON(true);
        $id   = $this->sanitizeInput($data['id'] ?? null);

        $currentUser = $this->getCurrentClientUser();

        if (!$id) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Response ID is required'
            ], 400);
        }

        if (!$this->cannedResponseModel->canUserManage(
            $id,
            $currentUser['type'],
            $currentUser['id']
        )) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Access denied. Response ID does not exist.'
            ], 403);
        }

        $this->cannedResponseModel->toggleStatus($id);

        return $this->jsonResponse([
            'status' => 'success',
            'message' => 'Status updated'
        ]);
    }

    /**
     * Variable replacement for preview
     */
    private function processVariableReplacement(array $response, string $sessionId): array
    {
        $session = $this->chatModel
            ->where('session_id', $sessionId)
            ->first();

        if (!$session) {
            return $response;
        }

        $map = [
            '{uid}' => $session['external_system_id'] ?? '',
            '{user_id}' => $session['external_system_id'] ?? '',
            '{name}' => $session['customer_name'] ?? '',
            '{email}' => $session['customer_email'] ?? '',
            '{topic}' => $session['chat_topic'] ?? '',
            '{session_id}' => $session['session_id'] ?? '',
            '{api_key}' => $session['api_key'] ?? ''
        ];

        foreach ($map as $key => $value) {
            $response['content'] = str_replace($key, $value, $response['content']);
        }

        return $response;
    }
}

