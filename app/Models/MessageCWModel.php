<?php

namespace App\Models;

use CodeIgniter\Model;

class MessageCWModel extends Model
{
    protected $table = 'messages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['session_id', 'sender_type', 'sender_id', 'message', 'message_type', 'is_read'];
    
    private $mongoMessageModel;
    
    public function __construct()
    {
        parent::__construct();
        // Don't initialize MongoDB connection here - do it lazily when needed
    }
    
    /**
     * Get MongoDB model instance (lazy initialization)
     */
    private function getMongoModel()
    {
        if (!$this->mongoMessageModel) {
            $this->mongoMessageModel = new \App\Models\MongoMessageModel();
        }
        return $this->mongoMessageModel;
    }
    
    public function getSessionMessages($sessionId)
    {
        // Use MongoDB for message storage
        $messages = $this->getMongoModel()->getSessionMessages($sessionId);
        
        // Add additional user information if needed
        $userModel = new \App\Models\UserModel();
        $chatModel = new \App\Models\ChatModel();
        
        foreach ($messages as &$message) {
            // Add agent name for agent messages
            if ($message['sender_type'] === 'agent' && $message['sender_id']) {
                $user = $userModel->find($message['sender_id']);
                $message['agent_name'] = $user ? $user['username'] : null;
            }
            
            // Add customer information
            $session = $chatModel->find($message['session_id']);
            if ($session) {
                $message['customer_name'] = $session['customer_name'];
                $message['customer_fullname'] = $session['customer_fullname'];
            }
        }
        
        return $messages;
    }
    
    /**
     * Override insert to use MongoDB
     */
    public function insert($data = null, bool $returnID = true)
    {
        return $this->getMongoModel()->insert($data);
    }
    
    public function markAsRead($sessionId, $senderType)
    {
        // Use MongoDB for marking messages as read
        return $this->getMongoModel()->markAsRead($sessionId, $senderType);
    }
    
    /**
     * Get chat history for a logged user across all sessions within specified days
     */
    public function getUserChatHistory($externalUsername, $externalFullname, $externalSystemId, $daysBack = 30, $excludeCurrentSession = null)
    {
        // Use MongoDB for message storage
        return $this->getMongoModel()->getUserChatHistory($externalUsername, $externalFullname, $externalSystemId, $daysBack, $excludeCurrentSession);
    }
    
    /**
     * Get session messages with optional historical context for logged users
     */
    public function getSessionMessagesWithHistory($sessionId, $includeHistory = false, $externalUsername = null, $externalFullname = null, $externalSystemId = null)
    {
        // Use MongoDB for message storage with history support
        return $this->getMongoModel()->getSessionMessagesWithHistory($sessionId, $includeHistory, $externalUsername, $externalFullname, $externalSystemId);
    }
}
