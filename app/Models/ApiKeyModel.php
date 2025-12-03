<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiKeyModel extends Model
{
    protected $table = 'api_keys';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    
    protected $allowedFields = [
        'client_id', 'key_id', 'api_key', 'client_name', 'client_email',
        'status', 'last_used_at'
    ];
    
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    
    protected $validationRules = [
        'client_id' => 'permit_empty|integer',
        'client_name' => 'required|max_length[255]',
        'client_email' => 'required|valid_email|max_length[255]',
        'status' => 'permit_empty|in_list[active,suspended,revoked]'
    ];
    
    protected $validationMessages = [
        'client_name' => [
            'required' => 'Client name is required',
            'max_length' => 'Client name cannot exceed 255 characters'
        ],
        'client_email' => [
            'required' => 'Client email is required',
            'valid_email' => 'Please enter a valid email address',
            'max_length' => 'Client email cannot exceed 255 characters'
        ],
        'status' => [
            'in_list' => 'Status must be active, suspended, or revoked'
        ]
    ];
    
    public function generateApiKey()
    {
        return 'lc_' . bin2hex(random_bytes(24)); // lc_1234567890abcdef...
    }
    
    public function generateKeyId()
    {
        return 'key_' . bin2hex(random_bytes(16));
    }
    
    public function validateApiKey($apiKey)
    {
        $key = $this->where('api_key', $apiKey)
                   ->where('status', 'active')
                   ->first();
        
        if (!$key) {
            return ['valid' => false, 'error' => 'Invalid or inactive API key'];
        }
        
        // Update last used timestamp
        $this->update($key['id'], ['last_used_at' => date('Y-m-d H:i:s')]);
        
        return ['valid' => true, 'key_data' => $key];
    }
    
    // Usage tracking methods removed - no longer using plan limits
    
    public function getApiKeyStats()
    {
        return $this->db->table('api_key_stats')->get()->getResultArray();
    }
    
    public function revokeApiKey($keyId)
    {
        return $this->update($keyId, ['status' => 'revoked']);
    }
    
    public function suspendApiKey($keyId)
    {
        return $this->update($keyId, ['status' => 'suspended']);
    }
    
    public function activateApiKey($keyId)
    {
        return $this->update($keyId, ['status' => 'active']);
    }
    
    public function updateLastUsed($keyId)
    {
        return $this->update($keyId, ['last_used_at' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * Get API keys for a specific client email
     */
    public function getKeysByClientEmail($email)
    {
        return $this->where('client_email', $email)
                   ->orderBy('created_at', 'DESC')
                   ->findAll();
    }
    
    /**
     * Get active API keys for a specific client email
     */
    public function getActiveKeysByClientEmail($email)
    {
        return $this->where('client_email', $email)
                   ->where('status', 'active')
                   ->orderBy('created_at', 'DESC')
                   ->findAll();
    }
    
    /**
     * Check if an email has any API keys
     */
    public function hasApiKeys($email)
    {
        return $this->where('client_email', $email)->countAllResults() > 0;
    }
    
    /**
     * Get API key statistics for a client
     */
    public function getClientApiKeyStats($email)
    {
        $keys = $this->getKeysByClientEmail($email);
        
        $stats = [
            'total' => count($keys),
            'active' => 0,
            'suspended' => 0,
            'revoked' => 0
        ];
        
        foreach ($keys as $key) {
            $stats[$key['status']]++;
        }
        
        return $stats;
    }
    
    /**
     * Check if a client already has an API key
     */
    public function clientHasApiKey($clientId, $clientEmail = null)
    {
        if ($clientId) {
            return $this->where('client_id', $clientId)->countAllResults() > 0;
        }
        
        if ($clientEmail) {
            return $this->where('client_email', $clientEmail)->countAllResults() > 0;
        }
        
        return false;
    }
    
    /**
     * Validate before insert - ensure client can only have one API key
     */
    protected function beforeInsert(array $data)
    {
        return $this->validateUniqueClientApiKey($data);
    }
    
    /**
     * Validate before update - ensure client can only have one API key
     */
    protected function beforeUpdate(array $data)
    {
        return $this->validateUniqueClientApiKey($data);
    }
    
    /**
     * Validate that a client doesn't already have an API key
     */
    private function validateUniqueClientApiKey(array $data)
    {
        $clientId = $data['data']['client_id'] ?? null;
        $clientEmail = $data['data']['client_email'] ?? null;
        
        // Skip validation if no client identifier provided
        if (!$clientId && !$clientEmail) {
            return $data;
        }
        
        // For updates, get the current record ID to exclude it from the check
        $currentId = null;
        if (isset($data['id'])) {
            $currentId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        }
        
        // Check if client already has an API key
        $query = $this->builder();
        
        if ($clientId) {
            $query->where('client_id', $clientId);
        } else {
            $query->where('client_email', $clientEmail);
        }
        
        // Exclude current record for updates
        if ($currentId) {
            $query->where('id !=', $currentId);
        }
        
        $existingCount = $query->countAllResults();
        
        if ($existingCount > 0) {
            $this->errors[] = 'This client already has an API key. Each client can only have one API key.';
            return false;
        }
        
        return $data;
    }
}
