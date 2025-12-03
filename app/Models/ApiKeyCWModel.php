<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiKeyCWModel extends Model
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
    
    public function generateApiKey()
    {
        return 'lc_' . bin2hex(random_bytes(24)); // lc_1234567890abcdef...
    }
    
    public function generateKeyId()
    {
        return 'key_' . bin2hex(random_bytes(16));
    }
    
    public function validateApiKey($apiKey, $domain = null)
    {
        $key = $this->select('id, client_id, key_id, api_key, client_name, client_email, status, last_used_at')
                   ->where('api_key', $apiKey)
                   ->where('status', 'active')
                   ->first();
        
        if (!$key) {
            return ['valid' => false, 'error' => 'Invalid or inactive API key'];
        }
        
        // Domain validation removed
        
        // Update last used timestamp
        $this->update($key['id'], ['last_used_at' => date('Y-m-d H:i:s')]);
        
        return ['valid' => true, 'key_data' => $key];
    }
    
    
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
}
