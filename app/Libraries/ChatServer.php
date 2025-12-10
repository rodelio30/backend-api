<?php

namespace App\Libraries;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Loop;
use MongoDB\Client as MongoClient;
use MongoDB\BSON\UTCDateTime;

class ChatServer implements MessageComponentInterface
{
    protected $clients;
    protected $sessions;
    protected $pdo;
    protected $cleanupTimer;
    protected $mongoClient;
    protected $mongoDatabase;
    protected $mongoConfig;
    
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->sessions = [];
        
        // Initialize direct PDO connection for MySQL (sessions)
        $this->connectDatabase();
        
        // Initialize MongoDB connection for messages
        $this->connectMongoDB();
    }
    
    private function connectDatabase()
    {
        try {
            $this->pdo = new \PDO(
                // 'mysql:host=localhost;dbname=livechat;charset=utf8mb4',
                // 'livechat',
                // '768705b7c4cd2',
                'mysql:host=185.135.72.72;dbname=livechat_test;charset=utf8mb4',
                'livechat_test',
                'aXzz75r4GhKWRrKX',
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_PERSISTENT => true,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800"
                ]
            );
        } catch (\PDOException $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
            $this->pdo = null;
        }
    }
    
    private function connectMongoDB()
    {
        try {
            $this->mongoConfig = [
                // 'hostname' => '127.0.0.1',
                // 'port' => 27017,
                // 'username' => 'livechat_messages',
                // 'password' => 'Y845akkHeYzFC8y5',
                // 'database' => 'livechat_messages'
                'hostname' => '103.205.208.104',
                'port' => 27017,
                'username' => 'root',
                'password' => 'XASvWsPkDxnDKpBD',
                'database' => 'livechat_messages'
            ];
            
            $uri = sprintf(
                'mongodb://%s:%s@%s:%d/%s',
                $this->mongoConfig['username'],
                $this->mongoConfig['password'],
                $this->mongoConfig['hostname'],
                $this->mongoConfig['port'],
                $this->mongoConfig['database']
            );
            
            $this->mongoClient = new MongoClient($uri, [
                'connectTimeoutMS' => 5000,
                'socketTimeoutMS' => 10000,
            ]);
            
            $this->mongoDatabase = $this->mongoClient->selectDatabase($this->mongoConfig['database']);
            
            echo "MongoDB connection established\n";
        } catch (\Exception $e) {
            echo "MongoDB connection failed: " . $e->getMessage() . "\n";
            $this->mongoClient = null;
            $this->mongoDatabase = null;
        }
    }
    
    private function ensureDatabaseConnection()
    {
        if (!$this->pdo) {
            $this->connectDatabase();
            return;
        }
        
        try {
            // Test the connection
            $this->pdo->query('SELECT 1');
        } catch (\PDOException $e) {
            echo "Database connection lost, reconnecting...\n";
            $this->connectDatabase();
        }
        
        // Also ensure MongoDB connection
        if (!$this->mongoClient || !$this->mongoDatabase) {
            $this->connectMongoDB();
        }
    }
    
    /**
     * Get client username from API key for MongoDB collection routing
     */
    private function getClientUsernameFromSession($sessionData)
    {
        if (!isset($sessionData['api_key'])) {
            return 'unknown';
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.username 
                FROM api_keys ak 
                JOIN clients c ON c.id = ak.client_id 
                WHERE ak.api_key = ?
            ");
            $stmt->execute([$sessionData['api_key']]);
            $result = $stmt->fetch();
            
            if ($result) {
                // Sanitize username for collection name
                $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $result['username']);
                return strtolower($sanitized);
            }
        } catch (\Exception $e) {
            echo "Error getting client username: " . $e->getMessage() . "\n";
        }
        
        return 'unknown';
    }
    
    /**
     * Get MongoDB collection for a session
     */
    private function getMongoCollection($sessionData)
    {
        if (!$this->mongoDatabase) {
            return null;
        }
        
        $clientUsername = $this->getClientUsernameFromSession($sessionData);
        $collectionName = $clientUsername === 'unknown' ? 'unknown_messages' : $clientUsername . '_messages';
        
        return $this->mongoDatabase->selectCollection($collectionName);
    }
    
    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
    }
    
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        
        switch ($data['type']) {
            case 'register':
                $this->registerConnection($from, $data);
                break;
                
            case 'message':
                $this->handleMessage($from, $data);
                break;
                
            case 'file_message':
                $this->handleFileMessage($from, $data);
                break;
                
            case 'typing':
                $this->handleTyping($from, $data);
                break;
                
            case 'assign_agent':
                $this->handleAgentAssignment($from, $data);
                break;
                
            case 'close_session':
                $this->handleSessionClose($from, $data);
                break;
                

        }
    }
    
    protected function registerConnection($conn, $data)
    {
        $sessionId = $data['session_id'];
        $userType = $data['user_type'] ?? 'customer';
        
        // Validate session ID
        if (empty($sessionId)) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Invalid session ID'
            ]));
            return;
        }
        
        if (!isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId] = [];
        }
        
        $this->sessions[$sessionId][$conn->resourceId] = [
            'connection' => $conn,
            'type' => $userType,
            'user_id' => $data['user_id'] ?? null
        ];
        
        
        // Send connection success
        $conn->send(json_encode([
            'type' => 'connected',
            'message' => 'Successfully connected to chat'
        ]));
        
        // If agent, send waiting sessions
        if ($userType === 'agent') {
            $this->sendWaitingSessions($conn);
        }
    }
    
    protected function handleMessage($from, $data)
    {
        $sessionId = $data['session_id'];
        $message = $data['message'];
        $senderType = $data['sender_type'];
        $senderId = $data['sender_id'] ?? null;
        
        // Get chat session using PDO
        $this->ensureDatabaseConnection();
        if (!$this->pdo) {
            return;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM chat_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $chatSession = $stmt->fetch();
        
        if (!$chatSession) {
            return;
        }
        
        // Determine sender_user_type based on user_type parameter
        $senderUserType = null;
        if ($senderType === 'agent' && isset($data['user_type'])) {
            switch ($data['user_type']) {
                case 'client':
                    $senderUserType = 'client';
                    break;
                case 'agent':
                    $senderUserType = 'agent';
                    break;
                default:
                    $senderUserType = 'admin';
                    break;
            }
        }
        
        // Save message to MongoDB
        $collection = $this->getMongoCollection($chatSession);
        if (!$collection) {
            echo "Failed to get MongoDB collection for session {$sessionId}\n";
            return;
        }
        
        $clientUsername = $this->getClientUsernameFromSession($chatSession);
        $utcDateTime = new UTCDateTime();
        
        $document = [
            'session_id' => $sessionId,
            'mysql_session_id' => $chatSession['id'],
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'sender_user_type' => $senderUserType,
            'message' => $message,
            'message_type' => 'text',
            'file_path' => null,
            'file_name' => null,
            'is_read' => false,
            'created_at' => $utcDateTime,
            'client_username' => $clientUsername,
            'api_key' => $chatSession['api_key']
        ];
        
        try {
            $result = $collection->insertOne($document);
            $messageId = (string) $result->getInsertedId();
            
            // Convert UTC to Malaysia time (GMT+8) for consistent display
            $utcDateTimeObj = $utcDateTime->toDateTime();
            $malaysiaTz = new \DateTimeZone('Asia/Kuala_Lumpur');
            $utcDateTimeObj->setTimezone($malaysiaTz);
            $timestamp = $utcDateTimeObj->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            echo "Failed to save message to MongoDB: " . $e->getMessage() . "\n";
            return;
        }
        
        // Get sender name based on sender type and ID
        $senderName = null;
        if ($senderType === 'customer') {
            $senderName = $chatSession['customer_name'] ?: 'Customer';
        } elseif ($senderType === 'agent' && $senderId) {
            // Get user_type from the message data to determine which table to check
            $userType = $data['user_type'] ?? null;
            
            // Fix: When user_type is 'client', check clients table first
            // When user_type is 'agent', check agents table first
            
            if ($userType === 'client') {
                // For client users, check clients table first
                $stmt = $this->pdo->prepare("SELECT username FROM clients WHERE id = ?");
                $stmt->execute([$senderId]);
                $senderName = $stmt->fetchColumn();
                
                if (!$senderName) {
                    // Fallback: try agents table
                    $stmt = $this->pdo->prepare("SELECT username FROM agents WHERE id = ?");
                    $stmt->execute([$senderId]);
                    $senderName = $stmt->fetchColumn();
                }
                
                if (!$senderName) {
                    // Final fallback: try users table (admin)
                    $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$senderId]);
                    $senderName = $stmt->fetchColumn();
                }
            } elseif ($userType === 'agent') {
                // Direct agent user, check agents table first
                $stmt = $this->pdo->prepare("SELECT username FROM agents WHERE id = ?");
                $stmt->execute([$senderId]);
                $senderName = $stmt->fetchColumn();
                
                if (!$senderName) {
                    // Fallback: try clients table
                    $stmt = $this->pdo->prepare("SELECT username FROM clients WHERE id = ?");
                    $stmt->execute([$senderId]);
                    $senderName = $stmt->fetchColumn();
                }
                
                if (!$senderName) {
                    // Fallback: try users table (admin)
                    $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$senderId]);
                    $senderName = $stmt->fetchColumn();
                }
            } else {
                // Default: admin users, check users table first
                $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$senderId]);
                $senderName = $stmt->fetchColumn();
                
                if (!$senderName) {
                    // Fallback: try agents table
                    $stmt = $this->pdo->prepare("SELECT username FROM agents WHERE id = ?");
                    $stmt->execute([$senderId]);
                    $senderName = $stmt->fetchColumn();
                }
                
                if (!$senderName) {
                    // Fallback: try clients table
                    $stmt = $this->pdo->prepare("SELECT username FROM clients WHERE id = ?");
                    $stmt->execute([$senderId]);
                    $senderName = $stmt->fetchColumn();
                }
            }
            
            $senderName = $senderName ?: 'Agent';
        }
        
        // Prepare response
        $response = [
            'type' => 'message',
            'id' => $messageId,
            'session_id' => $sessionId,
            'sender_type' => $senderType,
            'message' => $message,
            'timestamp' => $timestamp,
            'sender_name' => $senderName
        ];
        
        // Send to all connections in this session
        if (isset($this->sessions[$sessionId])) {
            foreach ($this->sessions[$sessionId] as $client) {
                $client['connection']->send(json_encode($response));
            }
        }
        
        // Check for automated keyword responses (only for customer messages when no agent is assigned)
        if ($senderType === 'customer' && $chatSession['status'] === 'waiting' && $chatSession['agent_id'] === null) {
            $this->checkAndSendAutomatedReply($sessionId, $message, $chatSession);
        }
        
        // Notify other agents if customer is waiting
        if ($senderType === 'customer' && $chatSession['status'] === 'waiting') {
            $this->notifyAgents($sessionId, 'new_message');
        }
    }
    
    protected function handleFileMessage($from, $data)
    {
        $sessionId = $data['session_id'];
        $messageId = $data['id'];
        $message = $data['message'];
        $senderType = $data['sender_type'];
        $senderName = $data['sender_name'];
        $fileData = $data['file_data'];
        $timestamp = $data['timestamp'];
        
        // Prepare file message response for WebSocket broadcast
        $response = [
            'type' => 'message', // Use 'message' type so existing clients handle it
            'id' => $messageId,
            'session_id' => $sessionId,
            'sender_type' => $senderType,
            'sender_name' => $senderName,
            'message' => $message,
            'message_type' => $data['message_type'],
            'file_data' => $fileData,
            'timestamp' => $timestamp
        ];
        
        // Send to all connections in this session
        if (isset($this->sessions[$sessionId])) {
            foreach ($this->sessions[$sessionId] as $client) {
                $client['connection']->send(json_encode($response));
            }
        }
        
        // Notify other agents if customer sent a file and is waiting
        if ($senderType === 'customer') {
            $this->notifyAgents($sessionId, 'new_file_message');
        }
    }
    
    protected function handleTyping($from, $data)
    {
        $sessionId = $data['session_id'];
        $isTyping = $data['is_typing'];
        $userType = $data['user_type'];
        
        $response = [
            'type' => 'typing',
            'session_id' => $sessionId,
            'user_type' => $userType,
            'is_typing' => $isTyping
        ];
        
        // Send to all other connections in this session
        if (isset($this->sessions[$sessionId])) {
            foreach ($this->sessions[$sessionId] as $resourceId => $client) {
                if ($resourceId !== $from->resourceId) {
                    $client['connection']->send(json_encode($response));
                }
            }
        }
    }
    
    protected function handleAgentAssignment($from, $data)
    {
        $sessionId = $data['session_id'];
        $agentId = $data['agent_id'];
        
        $this->ensureDatabaseConnection();
        if (!$this->pdo) {
            return;
        }
        
        // Update database using PDO
        $stmt = $this->pdo->prepare("
            UPDATE chat_sessions 
            SET agent_id = ?, status = 'active' 
            WHERE session_id = ?
        ");
        $stmt->execute([$agentId, $sessionId]);
        
        // Get the session data to get agent name
        $stmt = $this->pdo->prepare("SELECT * FROM chat_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $chatSession = $stmt->fetch();
        
        if (!$chatSession) {
            return;
        }
        
        // Get agent name from appropriate table based on agent_id
        $agentName = 'Agent';
        
        // Try different user tables to find the agent name
        $userTables = ['users', 'agents', 'clients'];
        foreach ($userTables as $table) {
            $stmt = $this->pdo->prepare("SELECT username FROM {$table} WHERE id = ?");
            $stmt->execute([$agentId]);
            $name = $stmt->fetchColumn();
            if ($name) {
                $agentName = $name;
                break;
            }
        }
        
        // Insert the system message into MongoDB
        $systemMessage = 'An agent has joined the chat';
        $collection = $this->getMongoCollection($chatSession);
        
        if ($collection) {
            $clientUsername = $this->getClientUsernameFromSession($chatSession);
            $utcDateTime = new UTCDateTime();
            
            $document = [
                'session_id' => $sessionId,
                'mysql_session_id' => $chatSession['id'],
                'sender_type' => 'system',
                'sender_id' => null,
                'sender_user_type' => null,
                'message' => $systemMessage,
                'message_type' => 'system',
                'file_path' => null,
                'file_name' => null,
                'is_read' => false,
                'created_at' => $utcDateTime,
                'client_username' => $clientUsername,
                'api_key' => $chatSession['api_key']
            ];
            
            try {
                $result = $collection->insertOne($document);
                $messageId = (string) $result->getInsertedId();
                
                // Convert UTC to Malaysia time (GMT+8) for consistent display
                $utcDateTimeObj = $utcDateTime->toDateTime();
                $malaysiaTz = new \DateTimeZone('Asia/Kuala_Lumpur');
                $utcDateTimeObj->setTimezone($malaysiaTz);
                $timestamp = $utcDateTimeObj->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                echo "Failed to save system message to MongoDB: " . $e->getMessage() . "\n";
                return;
            }
        } else {
            echo "Failed to get MongoDB collection for system message\n";
            return;
        }
        
        if ($messageId) {
            
            // Send system message via WebSocket with proper message format
            $messageResponse = [
                'type' => 'message',
                'id' => $messageId,
                'session_id' => $sessionId,
                'sender_type' => 'system',
                'message_type' => 'system',
                'message' => $systemMessage,
                'timestamp' => $timestamp,
                'created_at' => $timestamp
            ];
            
            
            // Send the system message to all connections in this session
            if (isset($this->sessions[$sessionId])) {
                foreach ($this->sessions[$sessionId] as $client) {
                    $client['connection']->send(json_encode($messageResponse));
                }
            }
        }
        
        // Update waiting sessions for all agents
        $this->broadcastToAgents(['type' => 'update_sessions']);
    }
    
    protected function handleSessionClose($from, $data)
    {
        $sessionId = $data['session_id'];
        
        $this->ensureDatabaseConnection();
        if (!$this->pdo) {
            return;
        }
        
        // Update database using PDO
        $stmt = $this->pdo->prepare("
            UPDATE chat_sessions 
            SET status = 'closed', closed_at = NOW() 
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        
        // Notify all connections in session
        $response = [
            'type' => 'session_closed',
            'session_id' => $sessionId,
            'message' => 'Chat session has been closed'
        ];
        
        if (isset($this->sessions[$sessionId])) {
            foreach ($this->sessions[$sessionId] as $client) {
                $client['connection']->send(json_encode($response));
            }
            
            // Remove session
            unset($this->sessions[$sessionId]);
        }
        
        // Update sessions for all agents
        $this->broadcastToAgents(['type' => 'update_sessions']);
    }
    
    
    protected function sendWaitingSessions($conn)
    {
        $this->ensureDatabaseConnection();
        if (!$this->pdo) {
            return;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM chat_sessions 
            WHERE status = 'waiting' 
            ORDER BY created_at ASC
        ");
        $stmt->execute();
        $waitingSessions = $stmt->fetchAll();
        
        $conn->send(json_encode([
            'type' => 'waiting_sessions',
            'sessions' => $waitingSessions
        ]));
    }
    
    protected function notifyAgents($sessionId, $notificationType)
    {
        $notification = [
            'type' => $notificationType,
            'session_id' => $sessionId
        ];
        
        $this->broadcastToAgents($notification);
    }
    
    protected function broadcastToAgents($data)
    {
        foreach ($this->sessions as $sessionId => $clients) {
            foreach ($clients as $client) {
                if ($client['type'] === 'agent') {
                    $client['connection']->send(json_encode($data));
                }
            }
        }
    }
    
    public function onClose(ConnectionInterface $conn)
    {
        // Remove from all sessions
        foreach ($this->sessions as $sessionId => &$clients) {
            unset($clients[$conn->resourceId]);
            
            if (empty($clients)) {
                unset($this->sessions[$sessionId]);
            }
        }
        
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }


    

    

    
    protected function checkAndSendAutomatedReply($sessionId, $message, $chatSession)
    {
        try {
            // Ensure database connection
            $this->ensureDatabaseConnection();
            if (!$this->pdo) {
                return;
            }
            
            // Convert message to lowercase for case-insensitive matching
            $messageToCheck = strtolower($message);
            
            // Check if keywords_responses table exists
            $tableCheckStmt = $this->pdo->prepare("SHOW TABLES LIKE 'keywords_responses'");
            $tableCheckStmt->execute();
            if (!$tableCheckStmt->fetch()) {
                return;
            }
            
            // Check for keyword matches - handle both specific client and general responses
            $clientId = $chatSession['client_id'];
            
            if ($clientId) {
                // Get responses for specific client
                $stmt = $this->pdo->prepare("
                    SELECT keyword, response 
                    FROM keywords_responses 
                    WHERE client_id = ? AND is_active = 1
                ");
                $stmt->execute([$clientId]);
                $keywordResponses = $stmt->fetchAll();
            } else {
                // If no client_id, try to get a default set of responses
                // First, try to determine client_id from API key in the session
                if (!empty($chatSession['api_key'])) {
                    $apiKeyStmt = $this->pdo->prepare("SELECT client_id FROM api_keys WHERE api_key = ? AND status = 'active'");
                    $apiKeyStmt->execute([$chatSession['api_key']]);
                    $apiKeyResult = $apiKeyStmt->fetch();
                    
                    if ($apiKeyResult && $apiKeyResult['client_id']) {
                        $clientId = $apiKeyResult['client_id'];
                        
                        // Update the session with the client_id for future use
                        $updateStmt = $this->pdo->prepare("UPDATE chat_sessions SET client_id = ? WHERE id = ?");
                        $updateStmt->execute([$clientId, $chatSession['id']]);
                        
                        // Get responses for this client
                        $stmt = $this->pdo->prepare("
                            SELECT keyword, response 
                            FROM keywords_responses 
                            WHERE client_id = ? AND is_active = 1
                        ");
                        $stmt->execute([$clientId]);
                        $keywordResponses = $stmt->fetchAll();
                    } else {
                        $keywordResponses = [];
                    }
                } else {
                    $keywordResponses = [];
                }
            }
            
            foreach ($keywordResponses as $keywordResponse) {
                $keyword = strtolower($keywordResponse['keyword']);
                
                // Check if the message contains the keyword
                if (strpos($messageToCheck, $keyword) !== false) {
                    // Save automated response to MongoDB as agent
                    $collection = $this->getMongoCollection($chatSession);
                    
                    if ($collection) {
                        $clientUsername = $this->getClientUsernameFromSession($chatSession);
                        $utcDateTime = new UTCDateTime();
                        
                        $document = [
                            'session_id' => $sessionId,
                            'mysql_session_id' => $chatSession['id'],
                            'sender_type' => 'agent',
                            'sender_id' => null,
                            'sender_user_type' => 'autobot',
                            'message' => $keywordResponse['response'],
                            'message_type' => 'text',
                            'file_path' => null,
                            'file_name' => null,
                            'is_read' => false,
                            'created_at' => $utcDateTime,
                            'client_username' => $clientUsername,
                            'api_key' => $chatSession['api_key']
                        ];
                        
                        try {
                            $result = $collection->insertOne($document);
                            $autoMessageId = (string) $result->getInsertedId();
                            $timestamp = $utcDateTime->toDateTime()->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            echo "Failed to save automated response to MongoDB: " . $e->getMessage() . "\n";
                            continue; // Skip to next keyword
                        }
                    } else {
                        echo "Failed to get MongoDB collection for automated response\n";
                        continue; // Skip to next keyword
                    }
                    
                    // Prepare automated response
                    $autoResponse = [
                        'type' => 'message',
                        'id' => $autoMessageId,
                        'session_id' => $sessionId,
                        'sender_type' => 'agent',
                        'message' => '[AutoBot] ' . $keywordResponse['response'],
                        'timestamp' => $timestamp,
                        'sender_name' => 'AutoBot'
                    ];
                    
                    // Send automated response to all connections in session
                    if (isset($this->sessions[$sessionId])) {
                        foreach ($this->sessions[$sessionId] as $client) {
                            $client['connection']->send(json_encode($autoResponse));
                        }
                    }
                    
                    // Only send one automated reply per message to avoid spam
                    break;
                }
            }
        } catch (\Exception $e) {
            // Silently handle errors to prevent server crashes
        }
    }
    
    protected function handleBulkMessage($from, $data)
    {
        // Send message to multiple sessions
        $message = $data['message'];
        $sessionIds = $data['session_ids'];
        $senderType = $data['sender_type'];
        
        foreach ($sessionIds as $sessionId) {
            if (isset($this->sessions[$sessionId])) {
                $response = [
                    'type' => 'system_message',
                    'message' => $message,
                    'sender_type' => $senderType
                ];
                
                foreach ($this->sessions[$sessionId] as $client) {
                    $client['connection']->send(json_encode($response));
                }
            }
        }
    }
    
    /**
     * Role-based session cleanup - only cleans up anonymous user sessions
     * Logged users ('loggedUser') sessions are NEVER automatically cleaned up
     */
    protected function cleanupInactiveSessions()
    {
        try {
            $this->ensureDatabaseConnection();
            if (!$this->pdo) {
                return;
            }
            
            // Configuration for session timeouts (30 minutes for anonymous users)
            $sessionTimeoutMinutes = 30;
            $inactiveTimeoutMinutes = 60;
            $timeThreshold = date('Y-m-d H:i:s', strtotime("-{$sessionTimeoutMinutes} minutes"));
            $inactiveThreshold = date('Y-m-d H:i:s', strtotime("-{$inactiveTimeoutMinutes} minutes"));
            
            // CRITICAL: Only cleanup anonymous user sessions that are inactive
            // NEVER cleanup 'loggedUser' sessions - they should persist indefinitely
            $stmt = $this->pdo->prepare("
                SELECT id, session_id, customer_name, user_role, updated_at
                FROM chat_sessions 
                WHERE user_role = 'anonymous' 
                  AND status IN ('waiting', 'active')
                  AND (
                    (status = 'waiting' AND created_at < ?) OR
                    (status = 'active' AND updated_at < ?)
                  )
            ");
            
            $stmt->execute([$timeThreshold, $inactiveThreshold]);
            $inactiveSessions = $stmt->fetchAll();
            
            // Double-check: Filter out any logged user sessions as an extra safety measure
            $inactiveSessions = array_filter($inactiveSessions, function($session) {
                return $session['user_role'] === 'anonymous';
            });
            
            echo "Found " . count($inactiveSessions) . " inactive anonymous sessions to cleanup\n";
            
            // Count total logged user sessions for monitoring
            $loggedUserStmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM chat_sessions 
                WHERE user_role = 'loggedUser' AND status IN ('waiting', 'active')
            ");
            $loggedUserStmt->execute();
            $loggedUserCount = $loggedUserStmt->fetchColumn();
            echo "Active logged user sessions (protected from cleanup): " . $loggedUserCount . "\n";
            
            foreach ($inactiveSessions as $session) {
                // Final safety check before closing
                if ($session['user_role'] !== 'anonymous') {
                    echo "WARNING: Skipped closing session {$session['session_id']} - not anonymous user (role: {$session['user_role']})\n";
                    continue;
                }
                $this->closeInactiveSession($session);
            }
            
        } catch (\Exception $e) {
            echo "Error during session cleanup: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Close an inactive anonymous session
     */
    protected function closeInactiveSession($session)
    {
        try {
            // Update session status to closed
            $stmt = $this->pdo->prepare("
                UPDATE chat_sessions 
                SET status = 'closed', closed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$session['id']]);
            
            // Add system message about timeout to MongoDB
            $timeoutMessage = 'Session automatically closed due to inactivity';
            
            // Get session data for MongoDB collection routing
            $sessionStmt = $this->pdo->prepare("SELECT * FROM chat_sessions WHERE id = ?");
            $sessionStmt->execute([$session['id']]);
            $chatSession = $sessionStmt->fetch();
            
            if ($chatSession) {
                $collection = $this->getMongoCollection($chatSession);
                
                if ($collection) {
                    $clientUsername = $this->getClientUsernameFromSession($chatSession);
                    $utcDateTime = new UTCDateTime();
                    
                    $document = [
                        'session_id' => $session['session_id'],
                        'mysql_session_id' => $session['id'],
                        'sender_type' => 'system',
                        'sender_id' => null,
                        'sender_user_type' => null,
                        'message' => $timeoutMessage,
                        'message_type' => 'system',
                        'file_path' => null,
                        'file_name' => null,
                        'is_read' => false,
                        'created_at' => $utcDateTime,
                        'client_username' => $clientUsername,
                        'api_key' => $chatSession['api_key']
                    ];
                    
                    try {
                        $collection->insertOne($document);
                    } catch (\Exception $e) {
                        echo "Failed to save timeout message to MongoDB: " . $e->getMessage() . "\n";
                    }
                }
            }
            
            // Notify WebSocket clients if they're still connected
            if (isset($this->sessions[$session['session_id']])) {
                $response = [
                    'type' => 'session_closed',
                    'session_id' => $session['session_id'],
                    'message' => $timeoutMessage,
                    'reason' => 'timeout'
                ];
                
                foreach ($this->sessions[$session['session_id']] as $client) {
                    $client['connection']->send(json_encode($response));
                }
                
                // Remove from active WebSocket sessions
                unset($this->sessions[$session['session_id']]);
            }
            
            echo "Closed inactive session: {$session['session_id']} (user: {$session['customer_name']}, role: {$session['user_role']})\n";
            
        } catch (\Exception $e) {
            echo "Error closing session {$session['session_id']}: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Start periodic cleanup task
     * This should be called when the WebSocket server starts
     */
    public function startPeriodicCleanup()
    {
        // Run cleanup every 10 minutes
        $this->cleanupTimer = Loop::get()->addPeriodicTimer(600, function() {
            echo "Running periodic session cleanup...\n";
            $this->cleanupInactiveSessions();
        });
        
        // Also run an initial cleanup
        echo "Running initial session cleanup...\n";
        $this->cleanupInactiveSessions();
    }
}
