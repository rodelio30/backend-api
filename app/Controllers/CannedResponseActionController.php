<?php

namespace App\Controllers;

use App\Controllers\General;

class CannedResponseActionController extends General
{
    /**
     * Execute API canned response (Live Chat)
     */
    public function execute()
    {
        // CORS (required for widget / live chat)
        $this->response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type');

        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response->setStatusCode(200);
        }

        if ($this->request->getMethod() !== 'POST') {
            return $this->respond(['error' => 'Method not allowed'], 405);
        }

        $payload = $this->request->getJSON(true);

        if (
            empty($payload['canned_response_id']) ||
            empty($payload['session_id'])
        ) {
            return $this->respond([
                'success' => false,
                'message' => 'Invalid payload'
            ], 422);
        }

        $db = \Config\Database::connect();

        // Get canned response
        $canned = $db->table('canned_responses')
            ->where('id', $payload['canned_response_id'])
            ->where('response_type', 'api')
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        if (!$canned) {
            return $this->respond([
                'success' => false,
                'message' => 'API canned response not found'
            ], 404);
        }

        // Get chat session
        $session = $db->table('chat_sessions')
            ->where('session_id', $payload['session_id'])
            ->get()
            ->getRowArray();

        if (!$session) {
            return $this->respond([
                'success' => false,
                'message' => 'Chat session not found'
            ], 404);
        }

        // Get client API config
        $clientConfig = $db->table('client_api_configs')
            ->where('api_key', $canned['api_key'])
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        if (!$clientConfig) {
            return $this->respond([
                'status'  => "error",
                'success' => false,
                'message' => 'Client API configuration not found'
            ], 400);
        }

        // Build API payload
        $params = json_decode($canned['api_parameters'], true) ?? [];
        $params = $this->replaceSessionVariables($params, $session);

        // Always inject customer identifier
        $externalId = $session['external_system_id'] ?? $session['customer_id'] ?? null;

        if (empty($externalId)) {
            return $this->respond([
                'success' => false,
                'message' => 'External user ID missing for this chat session'
            ], 422);
        }

        $params[$clientConfig['customer_id_field']] = $externalId;

        // Build endpoint
        $url = rtrim($clientConfig['base_url'], '/')
             . '/' . $canned['api_action_type'];

        return $this->callExternalApi($url, $clientConfig, $params);
    }

    /**
     * Call external client API
     */
    private function callExternalApi(string $url, array $config, array $payload)
    {
        $headers = ['Content-Type' => 'application/json'];

        switch ($config['auth_type']) {
            case 'bearer_token':
                $headers['Authorization'] = 'Bearer ' . $config['auth_value'];
                break;
            case 'api_key':
                $headers['X-API-Key'] = $config['auth_value'];
                break;
            case 'basic':
                $headers['Authorization'] =
                    'Basic ' . base64_encode($config['auth_value']);
                break;
        }

        try {
            log_message('debug', 'External API Payload: ' . json_encode($payload));

            $client = \Config\Services::curlrequest();
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $payload,
                'timeout' => 10
            ]);

            return $this->respond([
                'success' => true,
                'status' => $response->getStatusCode(),
                'data' => json_decode($response->getBody(), true)
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Canned API Action Error: ' . $e->getMessage());

            return $this->respond([
                'success' => false,
                'message' => 'External API call failed'
            ], 500);
        }
    }
}