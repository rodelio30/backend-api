<?php

namespace App\Models;

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;
use Exception;

/**
 * MongoMessageCWModel - Handles MongoDB operations for message storage
 * Each client gets their own collection: {username}_messages
 * Unknown clients use: unknown_messages
 */
class MongoMessageCWModel
{
    private $client;
    private $database;
    private $databaseConfig;
    
    public function __construct()
    {
        $this->databaseConfig = config('Database')->mongodb;
        $this->initializeConnection();
    }
    
    /**
     * Initialize MongoDB connection
     */
    private function initializeConnection()
    {
        try {
            
            $connectionString = sprintf(
                'mongodb://%s:%s@%s:%d/%s',
                $this->databaseConfig['username'],
                $this->databaseConfig['password'],
                $this->databaseConfig['hostname'],
                $this->databaseConfig['port'],
                $this->databaseConfig['database']
            );
            
            
            try {
                $this->client = new Client($connectionString, $this->databaseConfig['options'] ?? []);
                $this->database = $this->client->selectDatabase($this->databaseConfig['database']);
                
            // Test connection
            $this->database->command(['ping' => 1]);
                
            } catch (Exception $authException) {
                // Try with authSource admin parameter
                $connectionStringWithAuth = sprintf(
                    'mongodb://%s:%s@%s:%d/%s?authSource=admin',
                    $this->databaseConfig['username'],
                    $this->databaseConfig['password'],
                    $this->databaseConfig['hostname'],
                    $this->databaseConfig['port'],
                    $this->databaseConfig['database']
                );
                
                try {
                    $this->client = new Client($connectionStringWithAuth, $this->databaseConfig['options'] ?? []);
                    $this->database = $this->client->selectDatabase($this->databaseConfig['database']);
                    
                    // Test connection
                    $this->database->command(['ping' => 1]);
                    
                } catch (Exception $adminAuthException) {
                    // Try with authSource pointing to the target database
                    $connectionStringWithDbAuth = sprintf(
                        'mongodb://%s:%s@%s:%d/%s?authSource=%s',
                        $this->databaseConfig['username'],
                        $this->databaseConfig['password'],
                        $this->databaseConfig['hostname'],
                        $this->databaseConfig['port'],
                        $this->databaseConfig['database'],
                        $this->databaseConfig['database']
                    );
                    
                    $this->client = new Client($connectionStringWithDbAuth, $this->databaseConfig['options'] ?? []);
                    $this->database = $this->client->selectDatabase($this->databaseConfig['database']);
                    
                    // Test connection
                    $this->database->command(['ping' => 1]);
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get collection name for a client username
     */
    private function getCollectionName($clientUsername = null)
    {
        if (empty($clientUsername) || trim($clientUsername) === '') {
            return 'unknown_messages';
        }
        
        // Sanitize username for collection name
        $sanitizedUsername = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($clientUsername));
        return strtolower($sanitizedUsername) . '_messages';
    }
    
    /**
     * Ensure collection exists and has proper indexes
     */
    private function ensureCollection($collectionName)
    {
        try {
            $collection = $this->database->selectCollection($collectionName);
            
            // Create indexes for performance
            $collection->createIndex(['session_id' => 1]);
            $collection->createIndex(['created_at' => 1]);
            $collection->createIndex(['sender_type' => 1]);
            
            return $collection;
        } catch (Exception $e) {
            error_log('Failed to ensure collection ' . $collectionName . ': ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Insert a message into MongoDB
     */
    public function insertMessage($data)
    {
        try {
            // Get client username from session data or use fallback
            $clientUsername = $this->extractClientUsername($data);
            $collectionName = $this->getCollectionName($clientUsername);
            
            $collection = $this->ensureCollection($collectionName);
            
            // Prepare document for MongoDB with file support
            $document = [
                'session_id' => $data['session_id'],
                'sender_type' => $data['sender_type'],
                'sender_id' => isset($data['sender_id']) ? (int)$data['sender_id'] : null,
                'message' => $data['message'],
                'message_type' => $data['message_type'] ?? 'text',
                'is_read' => isset($data['is_read']) ? (bool)$data['is_read'] : false,
                'created_at' => isset($data['timestamp']) ? $data['timestamp'] : new UTCDateTime()
            ];
            
            // Add file data if present
            if (isset($data['file_data'])) {
                $document['file_data'] = $data['file_data'];
            }
            
            $result = $collection->insertOne($document);
            
            if ($result->getInsertedCount() > 0) {
                return (string)$result->getInsertedId();
            }
            
            return false;
        } catch (Exception $e) {
            error_log('MongoDB insertMessage error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert a message into MongoDB (legacy method for backward compatibility)
     */
    public function insert($data)
    {
        try {
            $debug = [
                'function' => 'insert',
                'input_data' => [
                    'session_id' => $data['session_id'] ?? 'missing',
                    'session_id_type' => gettype($data['session_id'] ?? null),
                    'sender_type' => $data['sender_type'] ?? 'missing',
                    'message' => substr($data['message'] ?? '', 0, 50) . '...'
                ]
            ];
            
            // Get client username from session data or use fallback
            $clientUsername = $this->extractClientUsername($data);
            $collectionName = $this->getCollectionName($clientUsername);
            
            $debug['client_username'] = $clientUsername;
            $debug['collection_name'] = $collectionName;
            
            $collection = $this->ensureCollection($collectionName);
            
            // Prepare document for MongoDB
            $document = [
                'session_id' => $data['session_id'], // Keep session_id as string to match ChatServer
                'sender_type' => $data['sender_type'],
                'sender_id' => isset($data['sender_id']) ? (int)$data['sender_id'] : null,
                'message' => $data['message'],
                'message_type' => $data['message_type'] ?? 'text',
                'is_read' => isset($data['is_read']) ? (bool)$data['is_read'] : false,
                'created_at' => new UTCDateTime()
            ];
            
            $result = $collection->insertOne($document);
            
            if ($result->getInsertedCount() > 0) {
                $debug['success'] = true;
                $debug['inserted_id'] = (string)$result->getInsertedId();
                return (string)$result->getInsertedId();
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get messages for a session
     */
    public function getSessionMessages($sessionId)
    {
        try {
            // Get session info to determine client username using session_id string
            $chatModel = new \App\Models\ChatModel();
            $session = $chatModel->getSessionBySessionId($sessionId);
            
            if (!$session) {
                return [];
            }
            
            $clientUsername = $this->extractClientUsernameFromSession($session);
            $collectionName = $this->getCollectionName($clientUsername);
            $collection = $this->database->selectCollection($collectionName);
            
            // Use session_id string for MongoDB query
            $messages = $collection->find(
                ['session_id' => $sessionId],
                ['sort' => ['created_at' => 1]]
            );
            
            $result = [];
            foreach ($messages as $message) {
                $messageData = [
                    'id' => (string)$message['_id'],
                    'session_id' => $message['session_id'],
                    'sender_type' => $message['sender_type'],
                    'sender_id' => $message['sender_id'],
                    'message' => $message['message'],
                    'message_type' => $message['message_type'] ?? 'text',
                    'is_read' => $message['is_read'] ?? false,
                    'created_at' => $this->convertToMalaysiaTime($message['created_at']),
                    'timestamp' => $this->convertToMalaysiaTime($message['created_at'])
                ];
                
                // Add file data if present
                if (isset($message['file_data'])) {
                    $messageData['file_data'] = $message['file_data'];
                }
                
                $result[] = $messageData;
            }
            
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get messages for a session with chat history for logged users
     */
    public function getSessionMessagesWithHistory($sessionId, $includeHistory = false, $externalUsername = null, $externalFullname = null, $externalSystemId = null)
    {
        $currentMessages = $this->getSessionMessages($sessionId);
        
        if (!$includeHistory || (!$externalUsername && !$externalFullname)) {
            return $currentMessages;
        }
        
        // Get historical messages
        $historicalMessages = $this->getUserChatHistory($externalUsername, $externalFullname, $externalSystemId, 30, $sessionId);
        
        // Combine and sort messages
        $allMessages = array_merge($historicalMessages, $currentMessages);
        
        // Sort by created_at timestamp
        usort($allMessages, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        
        return $allMessages;
    }
    
    /**
     * Get chat history for a user across multiple sessions
     */
    public function getUserChatHistory($externalUsername, $externalFullname, $externalSystemId, $daysBack = 30, $excludeCurrentSession = null)
    {
        try {
            // First, get all sessions for this user from MySQL
            $chatModel = new \App\Models\ChatModel();
            $timeThreshold = date('Y-m-d H:i:s', strtotime("-{$daysBack} days"));
            
            $sessionQuery = $chatModel->where('user_role', 'loggedUser')
                                   ->where('created_at >=', $timeThreshold);
            
            // Build user matching conditions
            if ($externalUsername && $externalFullname) {
                $sessionQuery->groupStart()
                            ->where('external_username', $externalUsername)
                            ->orWhere('external_fullname', $externalFullname)
                            ->groupEnd();
            } elseif ($externalUsername) {
                $sessionQuery->where('external_username', $externalUsername);
            } elseif ($externalFullname) {
                $sessionQuery->where('external_fullname', $externalFullname);
            } else {
                return [];
            }
            
            if ($externalSystemId) {
                $sessionQuery->where('external_system_id', $externalSystemId);
            }
            
            if ($excludeCurrentSession) {
                $sessionQuery->where('session_id !=', $excludeCurrentSession);
            }
            
            $userSessions = $sessionQuery->findAll();
            
            if (empty($userSessions)) {
                return [];
            }
            
            // Get messages from MongoDB for these sessions
            // Use the API key from the first session to determine the correct collection
            $firstSession = $userSessions[0];
            $clientUsername = $this->extractClientUsernameFromSession($firstSession);
            $collectionName = $this->getCollectionName($clientUsername);
            $collection = $this->database->selectCollection($collectionName);
            
            // Use session_id strings instead of database IDs
            $sessionIds = array_map(function($session) { return $session['session_id']; }, $userSessions);
            
            $messages = $collection->find(
                ['session_id' => ['$in' => $sessionIds]],
                ['sort' => ['created_at' => 1]]
            );
            
            $result = [];
            foreach ($messages as $message) {
                // Find the session this message belongs to
                $session = null;
                foreach ($userSessions as $s) {
                    if ($s['session_id'] === $message['session_id']) {
                        $session = $s;
                        break;
                    }
                }
                
                $messageData = [
                    'id' => (string)$message['_id'],
                    'session_id' => $message['session_id'],
                    'sender_type' => $message['sender_type'],
                    'sender_id' => $message['sender_id'],
                    'message' => $message['message'],
                    'message_type' => $message['message_type'] ?? 'text',
                    'is_read' => $message['is_read'] ?? false,
                    'created_at' => $this->convertToMalaysiaTime($message['created_at']),
                    'chat_session_id' => $session ? $session['session_id'] : null,
                    'customer_name' => $session ? $session['customer_name'] : null,
                    'customer_fullname' => $session ? $session['customer_fullname'] : null
                ];
                
                // Add file data if present
                if (isset($message['file_data'])) {
                    $messageData['file_data'] = $message['file_data'];
                }
                
                $result[] = $messageData;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log('Failed to get user chat history from MongoDB: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark messages as read
     */
    public function markAsRead($sessionId, $senderType)
    {
        try {
            // Get session info to determine client username using session_id string
            $chatModel = new \App\Models\ChatModel();
            $session = $chatModel->getSessionBySessionId($sessionId);
            
            if (!$session) {
                return false;
            }
            
            $clientUsername = $this->extractClientUsernameFromSession($session);
            $collectionName = $this->getCollectionName($clientUsername);
            $collection = $this->database->selectCollection($collectionName);
            
            $result = $collection->updateMany(
                [
                    'session_id' => $sessionId, // Use session_id string
                    'sender_type' => ['$ne' => $senderType]
                ],
                ['$set' => ['is_read' => true]]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) {
            error_log('Failed to mark messages as read in MongoDB: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert MongoDB UTCDateTime to Malaysia time string
     */
    private function convertToMalaysiaTime($mongoDate)
    {
        return $mongoDate->toDateTime()->setTimezone(new \DateTimeZone('Asia/Kuala_Lumpur'))->format('Y-m-d H:i:s');
    }
    
    /**
     * Extract client username from message data
     */
    private function extractClientUsername($data)
    {
        // Try to get client username from session
        if (isset($data['session_id'])) {
            $chatModel = new \App\Models\ChatModel();
            
            // Determine if session_id is string or integer
            if (is_numeric($data['session_id']) && intval($data['session_id']) == $data['session_id']) {
                // It's a numeric ID, use find()
                $session = $chatModel->find($data['session_id']);
            } else {
                // It's a session_id string, use getSessionBySessionId()
                $session = $chatModel->getSessionBySessionId($data['session_id']);
            }
            
            if ($session) {
                return $this->extractClientUsernameFromSession($session);
            }
        }
        
        return null; // Will use 'unknown_messages' collection
    }
    
    /**
     * Extract client username from session data - matching backend logic
     */
    private function extractClientUsernameFromSession($session)
    {
        // First, check if session has API key (matching backend logic)
        if (!empty($session['api_key'])) {
            return $this->getClientUsernameFromApiKey($session['api_key']);
        }
        
        // If no API key, try to find the client by checking if external_username matches any known client
        if (!empty($session['external_username'])) {
            $clientUsername = $this->findClientByUsername($session['external_username']);
            if ($clientUsername) {
                return $clientUsername;
            }
        }
        
        // Fallback: check if external_username is already a known client pattern
        if (!empty($session['external_username'])) {
            // Check if messages exist in a collection named after this username
            $testCollectionName = strtolower($session['external_username']) . '_messages';
            $testCollection = $this->database->selectCollection($testCollectionName);
            if ($testCollection->estimatedDocumentCount() > 0) {
                return strtolower($session['external_username']);
            }
        }
        
        // Final fallback: use 'client1' if it's a known collection with messages
        $client1Collection = $this->database->selectCollection('client1_messages');
        if ($client1Collection->estimatedDocumentCount() > 0) {
            return 'client1';
        }
        
        return null; // Will use 'unknown_messages' collection
    }
    
    /**
     * Get client username from API key (matching backend logic)
     */
    private function getClientUsernameFromApiKey($apiKey)
    {
        try {
            $db = \Config\Database::connect();
            
            // First try to get from api_keys table (matching backend)
            $query = $db->query("
                SELECT c.username 
                FROM api_keys ak 
                JOIN clients c ON c.id = ak.client_id 
                WHERE ak.api_key = ?
            ", [$apiKey]);
            
            $result = $query->getRow();
            
            if ($result) {
                return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $result->username));
            }
        } catch (\Exception $e) {
            error_log('Failed to get client username from API key: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Try to find client by matching external username pattern
     */
    private function findClientByUsername($externalUsername)
    {
        try {
            $db = \Config\Database::connect();
            
            // Check if there's a client with similar username
            $query = $db->query("
                SELECT username 
                FROM clients 
                WHERE LOWER(username) LIKE ?
            ", ['%' . strtolower($externalUsername) . '%']);
            
            $result = $query->getRow();
            
            if ($result) {
                return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $result->username));
            }
        } catch (\Exception $e) {
            error_log('Failed to find client by username: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Get a message by its MongoDB ID
     */
    public function getMessageById($messageId)
    {
        try {
            // We need to check all collections since we don't know which one contains the message
            $collectionNames = $this->database->listCollectionNames();
            
            foreach ($collectionNames as $collectionName) {
                if (strpos($collectionName, '_messages') !== false) {
                    $collection = $this->database->selectCollection($collectionName);
                    
                    try {
                        $message = $collection->findOne(['_id' => new ObjectId($messageId)]);
                        
                        if ($message) {
                            return [
                                'id' => (string)$message['_id'],
                                'session_id' => $message['session_id'],
                                'sender_type' => $message['sender_type'],
                                'sender_id' => $message['sender_id'] ?? null,
                                'message' => $message['message'],
                                'message_type' => $message['message_type'] ?? 'text',
                                'is_read' => $message['is_read'] ?? false,
                                'created_at' => isset($message['created_at']) ? $this->convertToMalaysiaTime($message['created_at']) : null,
                                'file_data' => $message['file_data'] ?? null
                            ];
                        }
                    } catch (Exception $e) {
                        // Invalid ObjectId or collection error, continue searching
                        continue;
                    }
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log('Failed to get message by ID from MongoDB: ' . $e->getMessage());
            return null;
        }
    }
}
