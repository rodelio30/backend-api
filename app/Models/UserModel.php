<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'username', 'password', 'email', 'role', 'is_online', 'last_seen',
        'status', 'max_concurrent_chats', 'current_chats'
    ];
    
    public function updateOnlineStatus($userId, $isOnline)
    {
        $data = ['is_online' => $isOnline];
        if (!$isOnline) {
            $data['last_seen'] = date('Y-m-d H:i:s');
        }
        
        return $this->update($userId, $data);
    }
    
    public function getOnlineAgents()
    {
        return $this->where('is_online', 1)
                    ->whereIn('role', ['admin', 'support'])
                    ->findAll();
    }
    
    /**
     * Get all clients
     */
    public function getClients()
    {
        return $this->where('role', 'client')
                    ->orderBy('username', 'ASC')
                    ->findAll();
    }
    
    /**
     * Get client by email
     */
    public function getClientByEmail($email)
    {
        return $this->where('role', 'client')
                    ->where('email', $email)
                    ->first();
    }
    
    /**
     * Check if email belongs to a client
     */
    public function isClientEmail($email)
    {
        return $this->where('role', 'client')
                    ->where('email', $email)
                    ->countAllResults() > 0;
    }
}