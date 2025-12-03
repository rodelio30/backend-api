<?php

namespace App\Models;

use CodeIgniter\Model;

class UserRoleModel extends Model
{
    protected $table = 'user_roles';
    protected $primaryKey = 'id';
    protected $allowedFields = ['role_name', 'role_description', 'can_access_chat', 'can_see_chat_history'];
    
    public function getRoleByName($roleName)
    {
        return $this->where('role_name', $roleName)->first();
    }
    
    public function getActiveRoles()
    {
        return $this->where('can_access_chat', 1)->findAll();
    }
    
    public function canAccessChat($roleName)
    {
        $role = $this->getRoleByName($roleName);
        return $role ? (bool)$role['can_access_chat'] : false;
    }
    
    public function canSeeChatHistory($roleName)
    {
        $role = $this->getRoleByName($roleName);
        return $role ? (bool)$role['can_see_chat_history'] : false;
    }
}
