<?php

namespace App\Models;

use CodeIgniter\Model;

class UserCWModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['username', 'password', 'email', 'role', 'is_online', 'last_seen'];
    
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
}