<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatCWModel extends Model
{
    protected $table = 'chat_sessions';
    protected $primaryKey = 'id';
    protected $allowedFields = ['client_id', 'session_id', 'customer_name', 'customer_fullname', 'chat_topic', 'customer_email', 'customer_phone', 'user_role', 'external_username', 'external_fullname', 'external_system_id', 'agent_id', 'api_key', 'status', 'closed_at'];
    
    public function getActiveSessions()
    {
        return $this->select('chat_sessions.*, agents.username as agent_name, agents.full_name as agent_full_name')
                    ->join('agents', 'agents.id = chat_sessions.agent_id', 'left')
                    ->where('chat_sessions.status', 'active')
                    ->orderBy('chat_sessions.created_at', 'DESC')
                    ->findAll();
    }
    
    public function getWaitingSessions()
    {
        return $this->where('status', 'waiting')
                    ->orderBy('created_at', 'ASC')
                    ->findAll();
    }
    
    public function assignAgent($sessionId, $agentId)
    {
        return $this->where('session_id', $sessionId)
                    ->set(['agent_id' => $agentId, 'status' => 'active'])
                    ->update();
    }
    
    public function closeSession($sessionId)
    {
        return $this->where('session_id', $sessionId)
                    ->set(['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')])
                    ->update();
    }
    
    public function getSessionBySessionId($sessionId)
    {
        return $this->where('session_id', $sessionId)->first();
    }
    
    public function getResumableSessionForUser($externalUsername, $externalFullname, $externalSystemId, $minutesBack = 30)
    {
        $timeThreshold = date('Y-m-d H:i:s', strtotime("-{$minutesBack} minutes"));
        
        $query = $this->where('user_role', 'loggedUser')
                     ->whereIn('status', ['active', 'waiting'])
                     ->where('created_at >=', $timeThreshold);
        
        // Build user matching conditions
        if ($externalUsername && $externalFullname) {
            $query->groupStart()
                  ->where('external_username', $externalUsername)
                  ->orWhere('external_fullname', $externalFullname)
                  ->groupEnd();
        } elseif ($externalUsername) {
            $query->where('external_username', $externalUsername);
        } elseif ($externalFullname) {
            $query->where('external_fullname', $externalFullname);
        } else {
            return null; // No user identifier provided
        }
        
        // Add external_system_id if available for extra verification
        if ($externalSystemId) {
            $query->where('external_system_id', $externalSystemId);
        }
        
        return $query->orderBy('created_at', 'DESC')
                    ->orderBy('updated_at', 'DESC')
                    ->first();
    }
    
    /**
     * Get all active logged user sessions for monitoring
     */
    public function getActiveLoggedUserSessions()
    {
        return $this->where('user_role', 'loggedUser')
                    ->whereIn('status', ['active', 'waiting'])
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }
    
    /**
     * Get count of active logged user sessions
     */
    public function getActiveLoggedUserSessionsCount()
    {
        return $this->where('user_role', 'loggedUser')
                    ->whereIn('status', ['active', 'waiting'])
                    ->countAllResults();
    }
}
