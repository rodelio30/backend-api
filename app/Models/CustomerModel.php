<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table = 'customers';
    protected $primaryKey = 'id';
    protected $allowedFields = ['email', 'name', 'phone', 'company', 'notes', 'total_chats', 'last_chat_at'];
    
    public function findOrCreateCustomer($email, $name)
    {
        if ($email) {
            $customer = $this->where('email', $email)->first();
            if ($customer) {
                return $customer;
            }
        }
        
        return $this->insert([
            'email' => $email,
            'name' => $name,
            'total_chats' => 0
        ]);
    }
    
    public function updateChatCount($customerId)
    {
        return $this->where('id', $customerId)
                    ->set('total_chats', 'total_chats + 1', false)
                    ->set('last_chat_at', date('Y-m-d H:i:s'))
                    ->update();
    }
    
    public function getCustomerHistory($customerId, $limit = 10)
    {
        return $this->db->table('chat_sessions')
                       ->select('chat_sessions.*, users.username as agent_name')
                       ->join('users', 'users.id = chat_sessions.agent_id', 'left')
                       ->where('customer_id', $customerId)
                       ->orderBy('created_at', 'DESC')
                       ->limit($limit)
                       ->get()
                       ->getResultArray();
    }
    
    public function searchCustomers($query)
    {
        return $this->groupStart()
                    ->like('name', $query)
                    ->orLike('email', $query)
                    ->orLike('company', $query)
                    ->groupEnd()
                    ->orderBy('last_chat_at', 'DESC')
                    ->findAll();
    }
}