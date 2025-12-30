<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Exception;

// class General extends BaseResourceController
class General extends BaseController
{
    /**
     * Generate unique session ID
     */
    public function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Sanitize input
     */
    public function sanitizeInput($input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Format timestamp
     */
    public function formatTimestamp($timestamp): string
    {
        return date('M d, Y h:i A', strtotime($timestamp));
    }
    
    /**
     * Check if user token exists (admin)
     */
    public function isAuthenticated(): bool
    {
        return isset($this->request->userData->user_id);
    }

    /**
     * Check if user is client/agent (token-based)
     */
    public function isClientAuthenticated(): bool
    {
        // return isset($this->request->userData->client_user_id) ||
        //        isset($this->request->userData->agent_user_id);
        $tokenObject = $this->request->clientToken ?? null;
        return $tokenObject && isset($tokenObject->data->id);
    }

    /**
     * Get client/agent ID from token
     */
    public function getTokenClientId(): ?int
    {
        if ($this->isClientAuthenticated()) {
            $tokenObject = $this->request->clientToken ?? null;
            $type = $tokenObject->data->type ?? null;

            if ($type === 'client') {
                return (int) $tokenObject->data->id;
            }
            if ($type === 'agent') {
                return (int) $tokenObject->data->id;
            }
        }
        return null;
    }

    /**
     * Get current admin user from token
     */
    public function getCurrentUser()
    {
        if ($this->isAuthenticated()) {
            return $this->userModel->find($this->request->userData->user_id);
        }
        return null;
    }
    
    /**
     * Get current client/agent user info
     */
    public function getCurrentClientUser()
    {
        // Get the current request object
        $request = service('request');

        // Check if the userData property exists on the request
        if (!isset($request->userData) || !is_array($request->userData)) {
            return null;
        }

        // The userData array is what holds the user information
        $userData = $request->userData;

        // ('id', 'username', 'email', 'type', and 'client_id' for agents)

        $type = $userData['type'] ?? null;

        if ($type === 'client' || $type === 'agent') {
            // Ensure essential fields are present for a user
            if (isset($userData['id'], $userData['username'], $userData['email'])) {
                // Cast the ID fields to int for consistency, similar to getClientId()
                $userData['id'] = (int) $userData['id'];
                
                if ($type === 'agent' && isset($userData['client_id'])) {
                    $userData['client_id'] = (int) $userData['client_id'];
                }

                // Return the validated and type-cast user data array
                return $userData;
            }
        }
        
        return null;
    }
    
    /**
     * Send JSON response
     */
    public function jsonResponse($data, $status = 200)
    {
        return $this->response->setJSON($data)->setStatusCode($status);
    }
    
    /**
     * Check if current user is admin
     */
    public function isAdmin()
    {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === 'admin';
    }
    
    /**
     * Check if current user is client (old system - keeping for compatibility)
     */
    public function isClient()
    {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === 'client';
    }
    
    /**
     * Check if current user is admin or support
     */
    public function isAdminOrSupport()
    {
        $user = $this->getCurrentUser();
        return $user && in_array($user['role'], ['admin', 'support']);
    }
    
    /**
     * Check if current client user is a client (not an agent)
     */
    public function isClientUser(): bool
    {
        return $this->session->get('user_type') === 'client';
    }
    
    /**
     * Check if current client user is an agent
     */
    public function isAgentUser(): bool
    {
        return $this->session->get('user_type') === 'agent';
    }
    
    /**
     * Get client ID for current user (works for both clients and agents)
     */
    public function getClientId(): ?int
    {
        $request = service('request');

        if (!isset($request->userData)) {
            return null;
        }

        $type = $request->userData['type'] ?? null;

        if ($type === 'client') {
            return (int) $request->userData['id'];
        }

        if ($type === 'agent') {
            return (int) $request->userData['client_id']; 
        }

        return null;
    }

    /**
     * Ensure the current session belongs to a client account owner.
     *
     * @return RedirectResponse|null
     */
    protected function ensureClientAccountOwner(string $errorMessage = 'Only account owners can access this area.'): ?RedirectResponse
    {
        if (!$this->isClientAuthenticated()) {
            return redirect()->to(getDomainSpecificUrl('login', 'client'));
        }

        if (!$this->isClientUser()) {
            $this->session->setFlashdata('error', $errorMessage);
            return redirect()->to(getDomainSpecificUrl('dashboard', 'client'));
        }

        return null;
    }

    /**
     * Resolve the base URL for the public widget frontend.
     */
    protected function getWidgetFrontendBaseUrl(): string
    {
        $envOverride = env('widget.frontendUrl', env('widget.frontend_url', ''));
        if (!empty($envOverride)) {
            return rtrim($envOverride, '/');
        }

        $appConfig = config('App');
        if (is_object($appConfig) && !empty($appConfig->widgetFrontendUrl ?? null)) {
            return rtrim($appConfig->widgetFrontendUrl, '/');
        }

        // return 'https://livechat.kopisugar.cc';
        // return 'https://api-taapin.danhar.cc';
        // return 'http://localhost:8080/';
        return 'https://clientzone.taapin.com';
    }

    /**
     * Resolve the base URL for the public base url CORS.
     */
    protected function getWidgetBaseUrl(): string
    {
        $envOverride = env('widget.frontendUrl', env('widget.frontend_url', ''));
        if (!empty($envOverride)) {
            return rtrim($envOverride, '/');
        }

        $appConfig = config('App');
        if (is_object($appConfig) && !empty($appConfig->widgetFrontendUrl ?? null)) {
            return rtrim($appConfig->widgetFrontendUrl, '/');
        }

        return 'https://api-taapin.danhar.cc';
    }

    /**
     * Get variable replacement map for canned responses
     * 
     * @param array $session Session data from chat_sessions table
     * @return array Map of variables to their values
     */
    protected function getCannedResponseVariableMap(array $session): array
    {
        return [
            '{uid}' => $session['external_system_id'] ?? '',
            '{user_id}' => $session['external_system_id'] ?? '',
            '{email}' => $session['customer_email'] ?? '',
            '{name}' => $session['customer_name'] ?? '',
            '{topic}' => $session['chat_topic'] ?? '',
            '{session_id}' => $session['session_id'] ?? '',
            '{api_key}' => $session['api_key'] ?? ''
        ];
    }

    /**
     * Replace variables in parameters or content
     * 
     * @param mixed $data String content or array of parameters
     * @param array $session Session data from chat_sessions table
     * @return mixed Processed data with variables replaced
     */
    protected function replaceSessionVariables($data, array $session)
    {
        $map = $this->getCannedResponseVariableMap($session);

        if (is_string($data)) {
            return str_replace(array_keys($map), array_values($map), $data);
        }

        if (is_array($data)) {
            array_walk_recursive($data, function (&$value) use ($map) {
                if (is_string($value)) {
                    $value = str_replace(array_keys($map), array_values($map), $value);
                }
            });
            return $data;
        }

        return $data;
    }
}
