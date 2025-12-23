<?php

namespace App\Models;

use CodeIgniter\Model;

class AgentModel extends Model
{
    protected $table = 'agents';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'client_id',
        'username',
        'password',
        'email',
        'full_name',
        'api_username',
        'api_password',
        'status',
        'preferred_locale',
        'is_online',
        'last_seen',
        'current_chats',
        'max_concurrent_chats',
        'agent_status',
        'created_at',
        'updated_at'
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'client_id' => 'required|integer',
        'username' => 'required|min_length[3]|max_length[50]|is_unique[agents.username,id,{id}]',
        'email' => 'permit_empty|valid_email|is_unique[agents.email,id,{id}]',
        'password' => 'required|min_length[6]',
        'preferred_locale' => 'permit_empty|in_list[en,zh,ms]'
    ];

    protected $validationMessages = [
        'client_id' => [
            'required' => 'Client ID is required',
            'integer' => 'Invalid client ID'
        ],
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
            'min_length' => 'Password must be at least 6 characters long'
        ],
        'preferred_locale' => [
            'in_list' => 'Selected language is not supported'
        ]
    ];

    /**
     * Get agent by username for authentication
     */
    public function getByUsername($username)
    {
        return $this->where('username', $username)->first();
    }

    /**
     * Get agents by client ID
     */
    public function getByClientId($clientId)
    {
        return $this->where('client_id', $clientId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Get agent with client information
     */
    public function getAgentWithClient($agentId)
    {
        return $this->select('agents.*, clients.username as client_username, clients.email as client_email')
                    ->join('clients', 'clients.id = agents.client_id', 'left')
                    ->where('agents.id', $agentId)
                    ->first();
    }

    /**
     * Update agent password
     */
    public function updatePassword($agentId, $newPassword)
    {
        return $this->update($agentId, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
    }

    /**
     * Get agent's active chat sessions count
     */
    public function getActiveChatCount($agentId)
    {
        $db = \Config\Database::connect();
        return $db->table('chat_sessions')
                  ->where('agent_id', $agentId)
                  ->where('status', 'active')
                  ->countAllResults();
    }

    /**
     * Get agent's total handled sessions count
     */
    public function getTotalSessionsCount($agentId)
    {
        $db = \Config\Database::connect();
        return $db->table('chat_sessions')
                  ->where('agent_id', $agentId)
                  ->countAllResults();
    }

    /**
     * Check if agent belongs to specific client
     */
    public function belongsToClient($agentId, $clientId)
    {
        $agent = $this->find($agentId);
        return $agent && $agent['client_id'] == $clientId;
    }

    /**
     * Get available agents for a client (not busy)
     */
    public function getAvailableAgentsByClient($clientId)
    {
        return $this->where('client_id', $clientId)
                    ->where('status', 'active')
                    ->findAll();
    }

    /**
     * Check username uniqueness across both clients and agents
     */
    public function isUsernameUnique($username, $excludeId = null)
    {
        $db = \Config\Database::connect();
        
        // Check in agents table
        $agentQuery = $db->table('agents')->where('username', $username);
        if ($excludeId) {
            $agentQuery->where('id !=', $excludeId);
        }
        $agentExists = $agentQuery->countAllResults() > 0;
        
        // Check in clients table
        $clientExists = $db->table('clients')->where('username', $username)->countAllResults() > 0;
        
        return !($agentExists || $clientExists);
    }

    /**
     * Update agent online status
     */
    public function updateOnlineStatus($agentId, $isOnline)
    {
        $data = ['is_online' => $isOnline];
        if (!$isOnline) {
            $data['last_seen'] = date('Y-m-d H:i:s');
        }
        
        return $this->update($agentId, $data);
    }

    /**
     * Get online agents for a specific client
     */
    public function getOnlineAgentsByClient($clientId)
    {
        return $this->where('client_id', $clientId)
                    ->where('is_online', 1)
                    ->where('status', 'active')
                    ->findAll();
    }

    /**
     * Update agent workload (current chats count)
     */
    public function updateCurrentChats($agentId, $count)
    {
        return $this->update($agentId, ['current_chats' => $count]);
    }

    /**
     * Increment current chats
     */
    public function incrementCurrentChats($agentId)
    {
        $agent = $this->find($agentId);
        if ($agent) {
            $newCount = ($agent['current_chats'] ?? 0) + 1;
            return $this->update($agentId, ['current_chats' => $newCount]);
        }
        return false;
    }

    /**
     * Decrement current chats
     */
    public function decrementCurrentChats($agentId)
    {
        $agent = $this->find($agentId);
        if ($agent && ($agent['current_chats'] ?? 0) > 0) {
            $newCount = $agent['current_chats'] - 1;
            return $this->update($agentId, ['current_chats' => max(0, $newCount)]);
        }
        return false;
    }
}
