<?php

// namespace App\Controllers;
namespace App\Controllers\ChatWidget;

use App\Models\ApiKeyCWModel;
use App\Controllers\BaseResourceController;

class WidgetAuthController extends BaseResourceController
{
    protected $apiKeyModel;
    
    public function __construct()
    {
        $this->apiKeyModel = new ApiKeyCWModel();
        
        // Add CORS headers for all widget API requests
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        // Handle preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    public function validateWidget()
    {
        // Set CORS headers again (in case constructor headers don't work)
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        
        // Handle JSON request body
        $input = $this->request->getJSON(true);
        
        // Handle both POST and GET requests for flexibility
        $apiKey = $input['api_key'] ?? $this->request->getPost('api_key') ?? $this->request->getGet('api_key');
        $domain = $input['domain'] ?? $this->request->getPost('domain') ?? $this->request->getServer('HTTP_ORIGIN');
        
        if (!$apiKey) {
            return $this->response->setJSON([
                'valid' => false,
                'error' => 'API key is required'
            ])->setStatusCode(400);
        }
        
        // Parse domain from URL if needed
        if ($domain) {
            $parsedUrl = parse_url($domain);
            $domain = $parsedUrl['host'] ?? $domain;
        }
        
        $validation = $this->apiKeyModel->validateApiKey($apiKey, $domain);
        
        if (!$validation['valid']) {
            return $this->response->setJSON($validation)->setStatusCode(403);
        }
        
        $keyData = $validation['key_data'];
        
        return $this->response->setJSON([
            'valid' => true,
            'client_name' => $keyData['client_name']
        ]);
    }
    
    public function validateChatStart()
    {
        // Set CORS headers
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        
        $apiKey = $this->request->getPost('api_key');
        $sessionId = $this->request->getPost('session_id');
        
        $domain = $this->request->getServer('HTTP_ORIGIN');
        if ($domain) {
            $parsedUrl = parse_url($domain);
            $domain = $parsedUrl['host'] ?? $domain;
        }
        
        if (!$apiKey) {
            return $this->response->setJSON([
                'valid' => false,
                'error' => 'API key is required'
            ])->setStatusCode(400);
        }
        
        $validation = $this->apiKeyModel->validateApiKey($apiKey, $domain);
        
        if (!$validation['valid']) {
            return $this->response->setJSON($validation)->setStatusCode(403);
        }
        
        return $this->response->setJSON(['valid' => true]);
    }
    
    public function logMessageSent()
    {
        // Set CORS headers
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        
        $apiKey = $this->request->getPost('api_key');
        $sessionId = $this->request->getPost('session_id');
        
        $domain = $this->request->getServer('HTTP_ORIGIN');
        if ($domain) {
            $parsedUrl = parse_url($domain);
            $domain = $parsedUrl['host'] ?? $domain;
        }
        
        if (!$apiKey) {
            return $this->response->setJSON([
                'valid' => false,
                'error' => 'API key is required'
            ])->setStatusCode(400);
        }
        
        $validation = $this->apiKeyModel->validateApiKey($apiKey, $domain);
        
        if (!$validation['valid']) {
            return $this->response->setJSON($validation)->setStatusCode(403);
        }
        
        return $this->response->setJSON(['valid' => true]);
    }
}