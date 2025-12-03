<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Models\MongoMessageModel;

class MessageModel extends Model
{
    protected $table = 'messages';
    protected $primaryKey = 'id';
    protected $allowedFields = ['session_id', 'sender_type', 'sender_id', 'sender_user_type', 'message', 'message_type', 'is_read'];
    
    protected MongoMessageModel $mongoModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->mongoModel = new MongoMessageModel();
    }
    
    public function getSessionMessages($sessionId)
    {
        // Route to MongoDB
        return $this->mongoModel->getSessionMessages($sessionId);
    }
    
    public function getSessionMessagesForBackend($sessionId)
    {
        // Route to MongoDB
        return $this->mongoModel->getSessionMessagesForBackend($sessionId);
    }
    
    /**
     * Get session messages with 30-day history for logged users in backend interface
     * For logged users, includes messages from all their sessions in the past 30 days
     */
    public function getSessionMessagesWithHistoryForBackend($sessionId)
    {
        // Route to MongoDB with full 30-day history implementation
        return $this->mongoModel->getSessionMessagesWithHistoryForBackend($sessionId);
    }
    
    public function markAsRead($sessionId, $senderType)
    {
        // Route to MongoDB
        return $this->mongoModel->markAsRead($sessionId, $senderType);
    }
    
    /**
     * Insert a new message - Override parent method to route to MongoDB
     */
    public function insert($data = null, bool $returnID = true)
    {
        if (is_array($data)) {
            // Route to MongoDB
            $insertedId = $this->mongoModel->insertMessage($data);
            return $returnID ? $insertedId : ($insertedId !== null);
        }
        
        // Fallback to parent method for other cases
        return parent::insert($data, $returnID);
    }
}
