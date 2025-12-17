<?php

namespace App\Models;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class MongoMessageModel
{
    protected Client $client;
    protected Database $database;
    protected array $config;
    protected string $fallbackCollection = 'unknown_messages';

    public function __construct()
    {
        $this->config = config('Database')->getMongoDB();
        $this->connect();
    }

    /**
     * Establish MongoDB connection
     */
    private function connect(): void
    {
        $uri = sprintf(
            'mongodb://%s:%s@%s:%d/%s',
            $this->config['username'],
            $this->config['password'],
            $this->config['hostname'],
            $this->config['port'],
            $this->config['database']
        );

        $this->client = new Client($uri, $this->config['options'] ?? []);
        $this->database = $this->client->selectDatabase($this->config['database']);
    }

    /**
     * Sanitize username for collection name
     */
    private function sanitizeUsername(string $username): string
    {
        // Remove special characters and convert to lowercase
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
        return strtolower($sanitized);
    }

    /**
     * Get client username from API key
     */
    private function getClientUsername(string $apiKey): string
    {
        $db = \Config\Database::connect();
        
        // First try to get from api_keys table
        $query = $db->query("
            SELECT c.username 
            FROM api_keys ak 
            JOIN clients c ON c.id = ak.client_id 
            WHERE ak.api_key = ?
        ", [$apiKey]);
        
        $result = $query->getRow();
        
        if ($result) {
            return $this->sanitizeUsername($result->username);
        }
        
        return 'unknown';
    }

    /**
     * Get collection name for a session - with fallback strategy
     */
    private function getCollectionName(array $sessionData): string
    {
        // First, try API key lookup (original strategy)
        if (isset($sessionData['api_key'])) {
            $clientUsername = $this->getClientUsername($sessionData['api_key']);
            if ($clientUsername !== 'unknown') {
                return $clientUsername . '_messages';
            }
        }
        
        // If API key lookup failed, try fallback strategies
        // Check if client1_messages collection exists with messages
        $client1Collection = $this->database->selectCollection('client1_messages');
        if ($client1Collection->estimatedDocumentCount() > 0) {
            return 'client1_messages';
        }
        
        // Check if any username-based collection exists with messages
        if (!empty($sessionData['external_username'])) {
            $testCollectionName = strtolower($sessionData['external_username']) . '_messages';
            $testCollection = $this->database->selectCollection($testCollectionName);
            if ($testCollection->estimatedDocumentCount() > 0) {
                return $testCollectionName;
            }
        }
        
        return $this->fallbackCollection;
    }
    
    /**
     * Get collection name using session ID and session data - improved fallback strategy  
     */
    public function getCollectionNameForSession(string $sessionId, array $sessionData): string
    {
        // First, search all collections for this session_id to find where messages actually exist
        try {
            $collections = $this->database->listCollections();
            foreach ($collections as $collectionInfo) {
                $collectionName = $collectionInfo->getName();
                if (substr($collectionName, -9) === '_messages') {
                    $collection = $this->database->selectCollection($collectionName);
                    if ($collection->countDocuments(['session_id' => $sessionId]) > 0) {
                        return $collectionName;
                    }
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Failed to search collections for session: ' . $e->getMessage());
        }
        
        // If no messages found for this session_id, use standard logic for new messages
        // Try API key first
        if (!empty($sessionData['api_key'])) {
            $clientUsername = $this->getClientUsername($sessionData['api_key']);
            if ($clientUsername !== 'unknown') {
                return $clientUsername . '_messages';
            }
        }
        
        // Try client1_messages (most common collection)
        $client1Collection = $this->database->selectCollection('client1_messages');
        if ($client1Collection->estimatedDocumentCount() > 0) {
            return 'client1_messages';
        }
        
        // Try external username based collection
        if (!empty($sessionData['external_username'])) {
            $usernameCollection = strtolower($sessionData['external_username']) . '_messages';
            return $usernameCollection;
        }
        
        return $this->fallbackCollection;
    }

    /**
     * Get collection by session data
     */
    private function getCollection(array $sessionData): Collection
    {
        $collectionName = $this->getCollectionName($sessionData);
        $collection = $this->database->selectCollection($collectionName);
        
        // Ensure indexes exist for new collections
        $this->ensureIndexes($collection);
        
        return $collection;
    }

    /**
     * Ensure proper indexes exist on collection
     */
    private function ensureIndexes(Collection $collection): void
    {
        try {
            // Check if indexes already exist
            $indexes = $collection->listIndexes();
            $indexNames = [];
            foreach ($indexes as $index) {
                $indexNames[] = $index->getName();
            }
            
            // Create session_id index if it doesn't exist
            if (!in_array('session_id_1', $indexNames)) {
                $collection->createIndex(['session_id' => 1]);
            }
            
            // Create created_at index if it doesn't exist
            if (!in_array('created_at_1', $indexNames)) {
                $collection->createIndex(['created_at' => 1]);
            }
            
            // Create compound index for session queries
            if (!in_array('session_id_1_created_at_1', $indexNames)) {
                $collection->createIndex(['session_id' => 1, 'created_at' => 1]);
            }
            
            // Create sender_type index
            if (!in_array('sender_type_1', $indexNames)) {
                $collection->createIndex(['sender_type' => 1]);
            }
            
        } catch (\Exception $e) {
            // Log error but don't fail - indexes are performance optimization
            log_message('error', 'MongoDB index creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Insert a new message
     */
    public function insertMessage(array $messageData): ?string
    {
        try {
            // Get session data to determine collection
            $sessionData = $this->getSessionData($messageData['session_id']);
            if (!$sessionData) {
                log_message('error', 'Session not found for message insert: ' . $messageData['session_id']);
                return null;
            }
            
            // Prepare document
            $document = [
                'session_id' => $messageData['session_id'],
                'mysql_session_id' => $sessionData['id'],
                'sender_type' => $messageData['sender_type'],
                'sender_id' => $messageData['sender_id'] ?? null,
                'sender_user_type' => $messageData['sender_user_type'] ?? null,
                'message' => $messageData['message'],
                'message_type' => $messageData['message_type'] ?? 'text',
                'file_path' => $messageData['file_path'] ?? null,
                'file_name' => $messageData['file_name'] ?? null,
                'is_read' => $messageData['is_read'] ?? false,
                'created_at' => new UTCDateTime(),
                'client_username' => $this->getClientUsername($sessionData['api_key']),
                'api_key' => $sessionData['api_key']
            ];
            
            // Add file data if present
            if (isset($messageData['file_data'])) {
                $document['file_data'] = $messageData['file_data'];
            }
            
            $collection = $this->getCollection($sessionData);
            $result = $collection->insertOne($document);
            
            return (string) $result->getInsertedId();
            
        } catch (\Exception $e) {
            log_message('error', 'MongoDB message insert failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get session messages
     */
    public function getSessionMessages(string $sessionId): array
    {
        try {
            $sessionData = $this->getSessionData($sessionId);
            if (!$sessionData) {
                return [];
            }
            
            // Use improved collection resolution
            $collectionName = $this->getCollectionNameForSession($sessionId, $sessionData);
            $collection = $this->database->selectCollection($collectionName);
            
            $cursor = $collection->find(
                ['session_id' => $sessionId],
                [
                    'sort' => ['created_at' => 1],
                    'projection' => [
                        '_id' => 1,
                        'session_id' => 1,
                        'sender_type' => 1,
                        'sender_id' => 1,
                        'sender_user_type' => 1,
                        'message' => 1,
                        'message_type' => 1,
                        'file_path' => 1,
                        'file_name' => 1,
                        'file_data' => 1,
                        'is_read' => 1,
                        'created_at' => 1
                    ]
                ]
            );
            
            $messages = [];
            foreach ($cursor as $document) {
                // Convert BSON document to array properly
                $message = [];
                foreach ($document as $key => $value) {
                    $message[$key] = $value;
                }
                
                // Convert MongoDB date to string
                if (isset($message['created_at']) && $message['created_at'] instanceof \MongoDB\BSON\UTCDateTime) {
                    // Convert UTC to Malaysia time (GMT+8)
                    $utcDateTime = $message['created_at']->toDateTime();
                    $malaysiaTz = new \DateTimeZone('Asia/Kuala_Lumpur');
                    $utcDateTime->setTimezone($malaysiaTz);
                    $message['timestamp'] = $utcDateTime->format('Y-m-d H:i:s');
                    $message['created_at'] = $message['timestamp'];
                }
                
                // Add sender name logic (similar to original MessageModel)
                $message['sender_name'] = $this->getSenderName($message);
                
                // Ensure both _id and id fields are available for JavaScript compatibility
                if (isset($message['_id'])) {
                    $originalId = $message['_id'];
                    
                    // Handle different ObjectId types more robustly
                    if ($originalId instanceof \MongoDB\BSON\ObjectId) {
                        $message['_id'] = (string)$originalId;
                    } elseif (is_object($originalId) && method_exists($originalId, '__toString')) {
                        $message['_id'] = $originalId->__toString();
                    } else {
                        $message['_id'] = (string)$originalId;
                    }
                    
                    $message['id'] = $message['_id']; // Set id to the string version
                    
                }
                
                // Include file data if present and convert relative paths to full URLs
                if (isset($message['file_data']) && is_array($message['file_data'])) {
                    // Use external file server URLs since files are stored in centralized storage
                    if (isset($message['file_data']['file_path'])) {
                        $message['file_data']['file_url'] = 'https://api-taapin.danhar.cc/livechat/default/chat/' . $message['file_data']['file_path'];
                    }
                    
                    if (isset($message['file_data']['thumbnail_path'])) {
                        $message['file_data']['thumbnail_url'] = 'https://api-taapin.danhar.cc/livechat/default/thumbs/' . $message['file_data']['thumbnail_path'];
                    }
                }
                
                $messages[] = $message;
            }
            
            
            return $messages;
            
        } catch (\Exception $e) {
            log_message('error', 'MongoDB message retrieval failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get session messages for backend (excluding system messages except customer left)
     */
    public function getSessionMessagesForBackend(string $sessionId): array
    {
        try {
            $sessionData = $this->getSessionData($sessionId);
            if (!$sessionData) {
                return [];
            }
            
            // Use improved collection resolution
            $collectionName = $this->getCollectionNameForSession($sessionId, $sessionData);
            $collection = $this->database->selectCollection($collectionName);
            
            $cursor = $collection->find(
                [
                    'session_id' => $sessionId,
                    '$or' => [
                        ['sender_type' => ['$ne' => 'system']],
                        ['message' => ['$regex' => 'left the chat']]
                    ]
                ],
                [
                    'sort' => ['created_at' => 1]
                ]
            );
            
            $messages = [];
            foreach ($cursor as $document) {
                // Convert BSON document to array properly
                $message = [];
                foreach ($document as $key => $value) {
                    $message[$key] = $value;
                }
                
                // Convert MongoDB date to string
                if (isset($message['created_at']) && $message['created_at'] instanceof \MongoDB\BSON\UTCDateTime) {
                    // Convert UTC to Malaysia time (GMT+8)
                    $utcDateTime = $message['created_at']->toDateTime();
                    $malaysiaTz = new \DateTimeZone('Asia/Kuala_Lumpur');
                    $utcDateTime->setTimezone($malaysiaTz);
                    $message['timestamp'] = $utcDateTime->format('Y-m-d H:i:s');
                    $message['created_at'] = $message['timestamp'];
                }
                
                $message['sender_name'] = $this->getSenderName($message);
                
                // Ensure both _id and id fields are available for JavaScript compatibility
                if (isset($message['_id'])) {
                    $originalId = $message['_id'];
                    
                    // Handle different ObjectId types more robustly
                    if ($originalId instanceof \MongoDB\BSON\ObjectId) {
                        $message['_id'] = (string)$originalId;
                    } elseif (is_object($originalId) && method_exists($originalId, '__toString')) {
                        $message['_id'] = $originalId->__toString();
                    } else {
                        $message['_id'] = (string)$originalId;
                    }
                    
                    $message['id'] = $message['_id']; // Set id to the string version
                    
                }
                
                // Include file data if present and convert relative paths to full URLs
                if (isset($message['file_data']) && is_array($message['file_data'])) {
                    // Use external file server URLs since files are stored in centralized storage
                    if (isset($message['file_data']['file_path'])) {
                        $message['file_data']['file_url'] = 'https://api-taapin.danhar.cc/livechat/default/chat/' . $message['file_data']['file_path'];
                    }
                    
                    if (isset($message['file_data']['thumbnail_path'])) {
                        $message['file_data']['thumbnail_url'] = 'https://api-taapin.danhar.cc/livechat/default/thumbs/' . $message['file_data']['thumbnail_path'];
                    }
                }
                
                $messages[] = $message;
            }
            
            return $messages;
            
        } catch (\Exception $e) {
            log_message('error', 'MongoDB backend message retrieval failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(string $sessionId, string $senderType): bool
    {
        try {
            $sessionData = $this->getSessionData($sessionId);
            if (!$sessionData) {
                return false;
            }
            
            $collection = $this->getCollection($sessionData);
            
            $result = $collection->updateMany(
                [
                    'session_id' => $sessionId,
                    'sender_type' => ['$ne' => $senderType]
                ],
                ['$set' => ['is_read' => true]]
            );
            
            return $result->getModifiedCount() >= 0;
            
        } catch (\Exception $e) {
            log_message('error', 'MongoDB mark as read failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get session data from MySQL
     */
    private function getSessionData(string $sessionId): ?array
    {
        $db = \Config\Database::connect();
        $query = $db->query('SELECT * FROM chat_sessions WHERE session_id = ?', [$sessionId]);
        $result = $query->getRowArray();
        
        return $result ?: null;
    }

    /**
     * Get sender name based on sender data
     */
    private function getSenderName(array $message): string
    {
        if ($message['sender_type'] === 'customer') {
            return 'Customer';
        }
        
        if ($message['sender_type'] === 'agent' && isset($message['sender_id'])) {
            $db = \Config\Database::connect();
            
            switch ($message['sender_user_type']) {
                case 'client':
                    $query = $db->query('SELECT username FROM clients WHERE id = ?', [$message['sender_id']]);
                    break;
                case 'agent':
                    $query = $db->query('SELECT username FROM agents WHERE id = ?', [$message['sender_id']]);
                    break;
                case 'admin':
                    $query = $db->query('SELECT username FROM users WHERE id = ?', [$message['sender_id']]);
                    break;
                default:
                    // Try all tables for null sender_user_type
                    $query = $db->query('
                        SELECT username FROM clients WHERE id = ? 
                        UNION 
                        SELECT username FROM agents WHERE id = ? 
                        UNION 
                        SELECT username FROM users WHERE id = ?
                    ', [$message['sender_id'], $message['sender_id'], $message['sender_id']]);
                    break;
            }
            
            if (isset($query)) {
                $result = $query->getRow();
                if ($result) {
                    return $result->username;
                }
            }
        }
        
        return 'Agent';
    }

    /**
     * Get session messages with 30-day history for logged users in backend interface
     * For logged users, includes messages from all their sessions in the past 30 days
     */
    public function getSessionMessagesWithHistoryForBackend(string $sessionId): array
    {
        try {
            $sessionData = $this->getSessionData($sessionId);
            if (!$sessionData) {
                return [];
            }
            
            // Check if this is a logged user session
            if ($sessionData['user_role'] !== 'loggedUser') {
                // For non-logged users, return regular backend messages
                return $this->getSessionMessagesForBackend($sessionId);
            }
            
            // For logged users, get all their sessions from the past 30 days
            $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
            $db = \Config\Database::connect();
            
            // Build customer identification criteria
            $customerSessions = [];
            
            // Primary identification: external_username + external_system_id
            if (!empty($sessionData['external_username']) && !empty($sessionData['external_system_id'])) {
                $query = $db->query("
                    SELECT * FROM chat_sessions 
                    WHERE external_username = ? 
                    AND external_system_id = ? 
                    AND user_role = 'loggedUser'
                    AND created_at >= ?
                    ORDER BY created_at ASC
                ", [
                    $sessionData['external_username'],
                    $sessionData['external_system_id'],
                    $thirtyDaysAgo
                ]);
                $customerSessions = $query->getResultArray();
            }
            
            // Fallback identification: customer_email (if primary didn't yield results)
            if (empty($customerSessions) && !empty($sessionData['customer_email'])) {
                $query = $db->query("
                    SELECT * FROM chat_sessions 
                    WHERE customer_email = ? 
                    AND user_role = 'loggedUser'
                    AND created_at >= ?
                    ORDER BY created_at ASC
                ", [
                    $sessionData['customer_email'],
                    $thirtyDaysAgo
                ]);
                $customerSessions = $query->getResultArray();
            }
            
            // If no customer sessions found, return regular messages
            if (empty($customerSessions)) {
                return $this->getSessionMessagesForBackend($sessionId);
            }
            
            // Get all messages from these sessions across potentially multiple collections
            $allMessages = [];
            $processedSessions = [];
            
            foreach ($customerSessions as $session) {
                // Skip duplicate sessions
                if (in_array($session['session_id'], $processedSessions)) {
                    continue;
                }
                $processedSessions[] = $session['session_id'];
                
                try {
                    // Use improved collection resolution
                    $collectionName = $this->getCollectionNameForSession($session['session_id'], $session);
                    $collection = $this->database->selectCollection($collectionName);
                    
                    $cursor = $collection->find(
                        [
                            'session_id' => $session['session_id'],
                            '$or' => [
                                ['sender_type' => ['$ne' => 'system']],
                                ['message' => ['$regex' => 'left the chat']]
                            ]
                        ],
                        [
                            'sort' => ['created_at' => 1]
                        ]
                    );
                    
                    foreach ($cursor as $document) {
                        // Convert BSON document to array properly
                        $message = [];
                        foreach ($document as $key => $value) {
                            $message[$key] = $value;
                        }
                        
                        // Convert MongoDB date to string with proper timezone (Malaysia GMT+8)
                        if (isset($message['created_at']) && $message['created_at'] instanceof \MongoDB\BSON\UTCDateTime) {
                            // Convert UTC to Malaysia time (GMT+8)
                            $utcDateTime = $message['created_at']->toDateTime();
                            $malaysiaTz = new \DateTimeZone('Asia/Kuala_Lumpur');
                            $utcDateTime->setTimezone($malaysiaTz);
                            $message['timestamp'] = $utcDateTime->format('Y-m-d H:i:s');
                            $message['created_at'] = $message['timestamp'];
                        }
                        
                        // Add session info for history context
                        $message['chat_session_id'] = $session['session_id'];
                        $message['session_created_at'] = $session['created_at'];
                        
                        $message['sender_name'] = $this->getSenderName($message);
                        $allMessages[] = $message;
                    }
                } catch (\Exception $e) {
                    // Log error but continue with other sessions
                    log_message('error', 'Error retrieving messages for session ' . $session['session_id'] . ': ' . $e->getMessage());
                }
            }
            
            // Sort all messages by created_at
            usort($allMessages, function($a, $b) {
                return strtotime($a['created_at']) - strtotime($b['created_at']);
            });
            
            return $allMessages;
            
        } catch (\Exception $e) {
            log_message('error', 'MongoDB history message retrieval failed: ' . $e->getMessage());
            return $this->getSessionMessagesForBackend($sessionId);
        }
    }

    /**
     * Get last message info for a session
     */
    public function getLastMessageInfo(string $sessionId): array
    {
        try {
            // Get session by session_id string
            $db = \Config\Database::connect();
            $query = $db->query('SELECT * FROM chat_sessions WHERE session_id = ?', [$sessionId]);
            $sessionData = $query->getRowArray();
            
            if (!$sessionData) {
                return [
                    'display_text' => 'No messages yet',
                    'is_waiting' => false
                ];
            }
            
            $collectionName = $this->getCollectionNameForSession($sessionId, $sessionData);
            $collection = $this->database->selectCollection($collectionName);
            
            $lastMessage = $collection->findOne(
                ['session_id' => $sessionId],
                ['sort' => ['created_at' => -1]]
            );
            
            if (!$lastMessage) {
                return [
                    'display_text' => 'No messages yet',
                    'is_waiting' => false
                ];
            }
            
            // Convert BSON document to array properly
            $messageArray = [];
            if ($lastMessage) {
                foreach ($lastMessage as $key => $value) {
                    $messageArray[$key] = $value;
                }
            }
            
            if ($messageArray['sender_type'] === 'customer') {
                $message = $messageArray['message'];
                if (strlen($message) > 50) {
                    $message = substr($message, 0, 47) . '...';
                }
                return [
                    'display_text' => $message,
                    'is_waiting' => false
                ];
            } else {
                return [
                    'display_text' => 'Waiting for reply',
                    'is_waiting' => true
                ];
            }
            
        } catch (\Exception $e) {
            log_message('error', 'MongoDB last message info failed: ' . $e->getMessage());
            return [
                'display_text' => 'No messages yet',
                'is_waiting' => false
            ];
        }
    }
    
    /**
     * Get a message by its MongoDB ID
     */
    public function getMessageById($messageId): ?array
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
                            // Convert BSON document to array properly
                            $messageArray = [];
                            foreach ($message as $key => $value) {
                                $messageArray[$key] = $value;
                            }
                            
                            // Convert MongoDB date to string
                            if (isset($messageArray['created_at']) && $messageArray['created_at'] instanceof \MongoDB\BSON\UTCDateTime) {
                                $utcDateTime = $messageArray['created_at']->toDateTime();
                                $malaysiaTz = new \DateTimeZone('Asia/Kuala_Lumpur');
                                $utcDateTime->setTimezone($malaysiaTz);
                                $messageArray['timestamp'] = $utcDateTime->format('Y-m-d H:i:s');
                                $messageArray['created_at'] = $messageArray['timestamp'];
                            }
                            
                            // Convert ObjectId to string properly
                            $messageId = $messageArray['_id'];
                            if ($messageId instanceof \MongoDB\BSON\ObjectId) {
                                $messageId = (string)$messageId;
                            } elseif (is_object($messageId) && method_exists($messageId, '__toString')) {
                                $messageId = $messageId->__toString();
                            } else {
                                $messageId = (string)$messageId;
                            }
                            
                            // Convert relative paths to full URLs for file_data
                            $fileData = $messageArray['file_data'] ?? null;
                            if ($fileData && is_array($fileData)) {
                                $baseUrl = base_url();
                                
                                if (isset($fileData['file_path'])) {
                                    $fileData['file_url'] = $baseUrl . 'client/download-file/' . $messageId;
                                }
                                
                                if (isset($fileData['thumbnail_path'])) {
                                    $fileData['thumbnail_url'] = $baseUrl . 'client/thumbnail/' . $messageId;
                                }
                            }
                            
                            return [
                                'id' => $messageId,
                                'session_id' => $messageArray['session_id'],
                                'sender_type' => $messageArray['sender_type'],
                                'sender_id' => $messageArray['sender_id'] ?? null,
                                'sender_user_type' => $messageArray['sender_user_type'] ?? null,
                                'message' => $messageArray['message'],
                                'message_type' => $messageArray['message_type'] ?? 'text',
                                'is_read' => $messageArray['is_read'] ?? false,
                                'created_at' => $messageArray['created_at'],
                                'file_data' => $fileData
                            ];
                        }
                    } catch (\Exception $e) {
                        // Invalid ObjectId or collection error, continue searching
                        continue;
                    }
                }
            }
            
            return null;
        } catch (\Exception $e) {
            log_message('error', 'Failed to get message by ID from MongoDB: ' . $e->getMessage());
            return null;
        }
    }

    public function countCustomerMessagesToday(string $clientUsername): int
{
    try {
        log_message('debug', 'MongoDB connected to DB: ' . $this->database->getDatabaseName());
        // 🔒 SAFE: Direct collection access (NO listCollections)
        $collectionName = strtolower($clientUsername) . '_messages';
        $collection = $this->database->selectCollection($collectionName);

        // Start of today (UTC)
        $todayStart = new \MongoDB\BSON\UTCDateTime(
            strtotime(date('Y-m-d 00:00:00')) * 1000
        );

        // Count customer messages created today
        $count = $collection->countDocuments([
            'sender_type' => 'customer',
            'created_at'  => [
                '$gte' => $todayStart
            ]
        ]);

        return (int) $count;

    } catch (\MongoDB\Driver\Exception\Exception $e) {
        log_message(
            'error',
            'MongoDB countCustomerMessagesToday failed: ' . $e->getMessage()
        );
        return 0;
    }
}

}
