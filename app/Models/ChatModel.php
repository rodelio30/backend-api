<?php
namespace App\Models;
use CodeIgniter\Model;
use App\Models\MongoMessageModel;

class ChatModel extends Model
{
    protected $table = 'chat_sessions';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'client_id', 'session_id', 'customer_name', 'customer_fullname', 'chat_topic', 'customer_email', 'customer_phone',
        'user_role', 'external_username', 'external_fullname', 'external_system_id', 
        'agent_id', 'status', 'closed_at', 'api_key', 'accepted_at', 'accepted_by'
    ];
    
    protected MongoMessageModel $mongoModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->mongoModel = new MongoMessageModel();
    }
    
    public function getActiveSessions()
    {
        $sessions = $this->select('chat_sessions.*, users.username as agent_name')
                         ->join('users', 'users.id = chat_sessions.agent_id', 'left')
                         ->where('chat_sessions.status', 'active') 
                         ->orderBy('chat_sessions.created_at', 'DESC')
                         ->findAll();
        
        // Process customer names and last messages
        foreach ($sessions as &$session) {
            $session['customer_name'] = $this->getCustomerDisplayName($session);
            $session['last_message_info'] = $this->getLastMessageInfo($session['session_id']);
        }
        
        return $sessions;
    }
    
    public function getWaitingSessions()
    {
        $sessions = $this->where('chat_sessions.status', 'waiting') 
                         ->orderBy('chat_sessions.created_at', 'ASC')
                         ->findAll();
        
        // Process customer names in PHP for better control
        foreach ($sessions as &$session) {
            $session['customer_name'] = $this->getCustomerDisplayName($session);
        }
        
        return $sessions;
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
    
    private function getCustomerDisplayName($session)
    {
        // Priority order for customer name - check for non-empty and non-null values
        if (isset($session['external_fullname']) && trim($session['external_fullname']) !== '') {
            return trim($session['external_fullname']);
        }
        
        if (isset($session['customer_fullname']) && trim($session['customer_fullname']) !== '') {
            return trim($session['customer_fullname']);
        }
        
        if (isset($session['customer_name']) && trim($session['customer_name']) !== '') {
            return trim($session['customer_name']);
        }
        
        return 'Anonymous';
    }
    
    private function getLastMessageInfo($sessionId)
    {
        // Route to MongoDB using session_id string
        return $this->mongoModel->getLastMessageInfo($sessionId);
    }
    
    
    /**
     * Get active sessions for specific API keys
     */
    public function getActiveSessionsByApiKeys($apiKeys)
    {
        if (empty($apiKeys)) {
            return [];
        }
        
        $apiKeysList = is_array($apiKeys) ? $apiKeys : [$apiKeys];
        
        $sessions = $this->select('chat_sessions.*, users.username as agent_name')
                         ->join('users', 'users.id = chat_sessions.agent_id', 'left')
                         ->where('chat_sessions.status', 'active')
                         ->whereIn('chat_sessions.api_key', $apiKeysList)
                         ->orderBy('chat_sessions.created_at', 'DESC')
                         ->findAll();
        
        // Process customer names and last messages
        foreach ($sessions as &$session) {
            $session['customer_name'] = $this->getCustomerDisplayName($session);
            $session['last_message_info'] = $this->getLastMessageInfo($session['session_id']);
        }
        
        return $sessions;
    }
    
    /**
     * Get session statistics for specific API keys
     */
    public function getSessionStatsByApiKeys($apiKeys)
    {
        if (empty($apiKeys)) {
            return [
                'total' => 0,
                'active' => 0,
                'waiting' => 0,
                'closed' => 0
            ];
        }
        
        $apiKeysList = is_array($apiKeys) ? $apiKeys : [$apiKeys];
        
        $sessions = $this->whereIn('api_key', $apiKeysList)->findAll();
        
        $stats = [
            'total' => count($sessions),
            'active' => 0,
            'waiting' => 0,
            'closed' => 0
        ];
        
        foreach ($sessions as $session) {
            if (isset($stats[$session['status']])) {
                $stats[$session['status']]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get sessions by API keys with optional status filter
     */
    public function getSessionsByApiKeys($apiKeys, $status = null)
    {
        if (empty($apiKeys)) {
            return [];
        }
        
        $apiKeysList = is_array($apiKeys) ? $apiKeys : [$apiKeys];
        
        $query = $this->select('chat_sessions.*, users.username as agent_name')
                      ->join('users', 'users.id = chat_sessions.agent_id', 'left')
                      ->whereIn('chat_sessions.api_key', $apiKeysList);
        
        if ($status) {
            $query->where('chat_sessions.status', $status);
        }
        
        $sessions = $query->orderBy('chat_sessions.created_at', $status === 'waiting' ? 'ASC' : 'DESC')
                         ->findAll();
        
        // Process customer names and last messages for each session
        foreach ($sessions as &$session) {
            $session['customer_name'] = $this->getCustomerDisplayName($session);
            if ($status !== 'waiting') {
                $session['last_message_info'] = $this->getLastMessageInfo($session['session_id']);
            }
        }
        
        return $sessions;
    }
    
}
