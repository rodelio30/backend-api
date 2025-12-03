<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientApiConfigModel extends Model
{
    protected $table = 'client_api_configs';
    protected $primaryKey = 'id';
    
    protected $allowedFields = [
        'api_key',
        'config_name',
        'base_url',
        'auth_type',
        'auth_value',
        'customer_id_field',
        'is_active',
        'created_at',
        'updated_at'
    ];
    
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    
    protected $validationRules = [
        'api_key' => 'required|max_length[255]',
        'config_name' => 'required|max_length[255]',
        'base_url' => 'required|max_length[500]',
        'auth_type' => 'required|in_list[none,bearer_token,api_key,basic]',
        'customer_id_field' => 'max_length[100]'
    ];
    
    protected $validationMessages = [
        'api_key' => [
            'required' => 'API key is required',
            'max_length' => 'API key cannot exceed 255 characters'
        ],
        'config_name' => [
            'required' => 'Configuration name is required',
            'max_length' => 'Configuration name cannot exceed 255 characters'
        ],
        'base_url' => [
            'required' => 'Base URL is required',
            'max_length' => 'Base URL cannot exceed 500 characters'
        ],
        'auth_type' => [
            'required' => 'Authentication type is required',
            'in_list' => 'Invalid authentication type'
        ]
    ];
    
    /**
     * Get active configuration for a specific API key
     */
    public function getActiveConfigByApiKey($apiKey)
    {
        return $this->where('api_key', $apiKey)
                    ->where('is_active', 1)
                    ->first();
    }
    
    /**
     * Get configuration for API key (without auth_value for security)
     */
    public function getConfigForClient($apiKey)
    {
        $config = $this->where('api_key', $apiKey)
                      ->where('is_active', 1)
                      ->first();
        
        if ($config) {
            // Remove sensitive data
            unset($config['auth_value']);
        }
        
        return $config;
    }
    
    /**
     * Save or update configuration for API key
     */
    public function saveConfigForApiKey($apiKey, $data)
    {
        // Add api_key to data
        $data['api_key'] = $apiKey;
        
        // Check if config already exists
        $existing = $this->where('api_key', $apiKey)->first();
        
        if ($existing) {
            // Update existing record
            return $this->update($existing['id'], $data);
        } else {
            // Create new record
            return $this->insert($data);
        }
    }
    
    /**
     * Check if configuration exists for API key
     */
    public function configExistsForApiKey($apiKey)
    {
        return $this->where('api_key', $apiKey)
                    ->where('is_active', 1)
                    ->countAllResults() > 0;
    }
    
    /**
     * Deactivate configuration for API key
     */
    public function deactivateConfigForApiKey($apiKey)
    {
        return $this->where('api_key', $apiKey)
                    ->set('is_active', 0)
                    ->update();
    }
    
    /**
     * Get all configurations for a client (by client_id through api_keys join)
     */
    public function getConfigsForClient($clientId)
    {
        $db = \Config\Database::connect();
        
        return $db->table('client_api_configs cac')
                 ->select('cac.*, ak.client_name, ak.domain')
                 ->join('api_keys ak', 'ak.api_key = cac.api_key')
                 ->where('ak.client_id', $clientId)
                 ->where('cac.is_active', 1)
                 ->get()
                 ->getResultArray();
    }
    
    /**
     * Validate auth_value based on auth_type
     */
    public function validateAuthValue($authType, $authValue)
    {
        switch ($authType) {
            case 'none':
                return true; // No validation needed
                
            case 'bearer_token':
            case 'api_key':
                return !empty($authValue);
                
            case 'basic':
                // Basic auth should be in format "username:password"
                return !empty($authValue) && strpos($authValue, ':') !== false;
                
            default:
                return false;
        }
    }
    
    /**
     * Validate before insert
     */
    protected function beforeInsert(array $data)
    {
        return $this->validateAuthConfig($data);
    }
    
    /**
     * Validate before update
     */
    protected function beforeUpdate(array $data)
    {
        return $this->validateAuthConfig($data);
    }
    
    /**
     * Validate authentication configuration
     */
    private function validateAuthConfig(array $data)
    {
        $authType = $data['data']['auth_type'] ?? null;
        $authValue = $data['data']['auth_value'] ?? null;
        
        if ($authType && !$this->validateAuthValue($authType, $authValue)) {
            $this->errors[] = 'Authentication value is invalid for the selected authentication type';
            return false;
        }
        
        return $data;
    }
}
