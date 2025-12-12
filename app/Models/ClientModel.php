<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientModel extends Model
{
    protected $table = 'clients';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'username',
        'password',
        'email',
        'full_name',
        'api_username',
        'api_password',
        'status',
        'preferred_locale',
        'oauth_provider',
        'created_at',
        'updated_at'
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[50]|is_unique[clients.username,id,{id}]',
        'email' => 'permit_empty|valid_email|is_unique[clients.email,id,{id}]',
        'password' => 'required|min_length[6]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/]',
        'preferred_locale' => 'permit_empty|in_list[en,zh,ms]'
    ];

    protected $validationMessages = [
        'username' => [
            'required' => 'Username is required',
            'min_length' => 'Username must be at least 3 characters long',
            'is_unique' => 'Username already exists'
        ],
        'email' => [
            'valid_email' => 'Please enter a valid email',
            'is_unique' => 'Email already exists'
        ],
        'password' => [
            'required' => 'Password is required',
            'min_length' => 'Password must be at least 6 characters long',
            'regex_match' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number'
        ],
        'preferred_locale' => [
            'in_list' => 'Selected language is not supported'
        ]
    ];

    /**
     * Get client with their API keys
     */
    public function getClientWithApiKeys($clientId)
    {
        return $this->select('clients.*, 
                            (SELECT COUNT(*) FROM api_keys WHERE client_id = clients.id) as api_key_count')
                    ->where('clients.id', $clientId)
                    ->first();
    }

    /**
     * Get client by username for authentication
     */
    public function getByUsername($username)
    {
        return $this->where('username', $username)->first();
    }

    /**
     * Get client by email
     */
    public function getByEmail($email)
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Update client password
     */
    public function updatePassword($clientId, $newPassword)
    {
        return $this->update($clientId, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
    }

    /**
     * Get client statistics
     */
    public function getClientStats($clientId)
    {
        $db = \Config\Database::connect();
        
        // Get API keys count
        $apiKeysCount = $db->table('api_keys')
                          ->where('client_id', $clientId)
                          ->countAllResults();

        // Get agents count
        $agentsCount = $db->table('agents')
                         ->where('client_id', $clientId)
                         ->countAllResults();

        // Get chat sessions count
        $sessionsCount = $db->table('chat_sessions')
                           ->where('client_id', $clientId)
                           ->countAllResults();

        // Get active sessions count
        $activeSessionsCount = $db->table('chat_sessions')
                                 ->where('client_id', $clientId)
                                 ->where('status', 'active')
                                 ->countAllResults();

        return [
            'api_keys' => $apiKeysCount,
            'agents' => $agentsCount,
            'total_sessions' => $sessionsCount,
            'active_sessions' => $activeSessionsCount
        ];
    }
}
