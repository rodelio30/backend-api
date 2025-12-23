<?php

namespace App\Controllers\ChatWidget;

use App\Controllers\GeneralCW;
use App\Models\ApiKeyCWModel;
use App\Models\ClientWidgetSettingCWModel;
use Exception;

// class ChatController extends GeneralCW
class ChatController extends GeneralCW
{
    protected ApiKeyCWModel $apiKeyCWModel;
    protected ClientWidgetSettingCWModel $clientWidgetSettingCWModel;
    protected array $behaviorDefaults = [
        'disable_sound_notification' => false,
        'disable_agent_typing_notification' => false,
        'hide_widget_on_mobile' => false,
        'maximize_on_click' => false,
    ];

    public function __construct()
    {
        $this->apiKeyCWModel = new ApiKeyCWModel();
        $this->clientWidgetSettingCWModel = new ClientWidgetSettingCWModel();
    }


    public function index()
    {
        // Handle iframe integration parameters first
        $isIframe = $this->request->getGet('iframe') === '1';
        $isFullscreen = $this->request->getGet('fullscreen') === '1';
        $apiKey = $this->sanitizeInput($this->request->getGet('api_key'));
        $externalUsername = $this->sanitizeInput($this->request->getGet('external_username'));
        $externalFullname = $this->sanitizeInput($this->request->getGet('external_fullname'));
        $externalSystemId = $this->sanitizeInput($this->request->getGet('external_system_id'));
        $customerPhone = $this->sanitizeInput($this->request->getGet('customer_phone'));
        // Normalize phone to digits only
        if ($customerPhone) {
            $customerPhone = preg_replace('/\D/', '', $customerPhone);
        }
        $externalEmail = $this->sanitizeInput($this->request->getGet('external_email'));
        $userRole = $this->sanitizeInput($this->request->getGet('user_role')) ?: 'anonymous';
        
        $sessionId = $this->session->get('chat_session_id');
        $validSession = null;
        
        // Check if session is still valid (exists and not closed)
        if ($sessionId) {
            $chatSession = $this->chatCWModel->getSessionBySessionId($sessionId);
            if ($chatSession && $chatSession['status'] !== 'closed') {
                // For logged users, also verify the session belongs to them
                if ($userRole === 'loggedUser' && ($externalUsername || $externalFullname)) {
                    $sessionBelongsToUser = false;
                    
                    // Check if session matches the user's identity
                    if (($externalUsername && $chatSession['external_username'] === $externalUsername) ||
                        ($externalFullname && $chatSession['external_fullname'] === $externalFullname)) {
                        $sessionBelongsToUser = true;
                    }
                    
                    // Also check external_system_id for extra verification
                    if ($externalSystemId && $chatSession['external_system_id'] === $externalSystemId) {
                        $sessionBelongsToUser = true;
                    }
                    
                    if ($sessionBelongsToUser) {
                        $validSession = $sessionId;
                    } else {
                        // Session doesn't belong to this user, clear it
                        $this->session->remove('chat_session_id');
                    }
                } else {
                    // For anonymous users or when no identity is provided, session is valid
                    $validSession = $sessionId;
                }
            } else {
                // Clear invalid session from PHP session
                $this->session->remove('chat_session_id');
            }
        }
        
        // Validate API key for iframe integrations
        if ($isIframe && $apiKey) {
            $apiKeyCWModel = new \App\Models\ApiKeyCWModel();
            $domain = $this->request->getServer('HTTP_REFERER') ? parse_url($this->request->getServer('HTTP_REFERER'), PHP_URL_HOST) : null;
            $validation = $apiKeyCWModel->validateApiKey($apiKey, $domain);
            
            if (!$validation['valid']) {
                // Show error page for invalid API key
                return view('errors/api_key_invalid', ['error' => $validation['error']]);
            }
        }
        
        // Smart session management for logged users
        $autoSessionError = null;
        if ($userRole === 'loggedUser' && !$validSession && ($externalUsername || $externalFullname)) {
            try {
                // First, try to find a resumable session
                $resumableSession = $this->findResumableSession($externalUsername, $externalFullname, $externalSystemId);
                
                if ($resumableSession) {
                    // Resume existing session
                    $validSession = $resumableSession['session_id'];
                    
                    // Update PHP session to track this resumed session
                    $this->session->set('chat_session_id', $validSession);
                    $this->session->set('user_role', 'loggedUser');
                } else {
                    // No resumable session found, create new one
                    $autoSessionResult = $this->autoCreateSessionForLoggedUser(
                        $externalUsername, 
                        $externalFullname, 
                        $externalSystemId, 
                        $apiKey,
                        $customerPhone,
                        $externalEmail
                    );
                    
                    if ($autoSessionResult['success']) {
                        $validSession = $autoSessionResult['session_id'];
                    } else {
                        $autoSessionError = $autoSessionResult['error'];
                    }
                }
            } catch (Exception $e) {
                $autoSessionError = 'Failed to start chat session automatically. Please try again.';
            }
        }
        
        // Get widget settings from API key if available
        $widgetColor = null;
        $widgetName = 'Customer Support'; // Default fallback
        $behaviorOptions = $this->behaviorDefaults;
        if ($apiKey) {
            try {
                $apiKeyRecord = $this->ApiKeyCWModel
                    ->where('api_key', $apiKey)
                    ->where('status', 'active')
                    ->first();

                if ($apiKeyRecord) {
                    $settings = $this->clientWidgetSettingCWModel->getByClientId((int) $apiKeyRecord['client_id']);
                    if (!empty($settings)) {
                        $mergedSettings = $this->mergeWidgetSettingsWithDefaults($settings);
                        $widgetColor = $mergedSettings['widget_color'] ?? $widgetColor;
                        $widgetName = $mergedSettings['widget_name'] ?? $widgetName;
                        $behaviorOptions = $mergedSettings['behavior_options'] ?? $behaviorOptions;
                    }
                }
            } catch (Exception $e) {
                // Silently fail, use defaults
            }
        }

        $data = [
            'title' => 'Customer Support Chat',
            'session_id' => $validSession,
            'is_iframe' => $isIframe,
            'is_fullscreen' => $isFullscreen,
            'api_key' => $apiKey,
            'external_username' => $externalUsername,
            'external_fullname' => $externalFullname,
            'external_system_id' => $externalSystemId,
            'customer_phone' => $customerPhone,
            'external_email' => $externalEmail,
            'user_role' => $userRole,
            'auto_session_error' => $autoSessionError,
            'widget_color' => $widgetColor,
            'widget_name' => $widgetName,
            'behavior_options' => $behaviorOptions
        ];
        
        return view('chat/customer', $data);
    }
    
    public function startSession()
    {
        // Handle both new format (customer_name + chat_topic) and legacy format (name for topic)
        $customerNameInput = $this->sanitizeInput($this->request->getPost('customer_name'));
        $topicInput = $this->sanitizeInput($this->request->getPost('chat_topic'));
        $legacyNameInput = $this->sanitizeInput($this->request->getPost('name')); // For backwards compatibility
        $email = $this->sanitizeInput($this->request->getPost('email'));
        $customerPhone = $this->sanitizeInput($this->request->getPost('customer_phone'));

        // Normalize phone to digits only
        if ($customerPhone) {
            $customerPhone = preg_replace('/\D/', '', $customerPhone);
        }
        
        // Role-based parameters
        $userRole = $this->sanitizeInput($this->request->getPost('user_role')) ?: 'anonymous';
        $externalUsername = $this->sanitizeInput($this->request->getPost('external_username'));
        $externalFullname = $this->sanitizeInput($this->request->getPost('external_fullname'));
        $externalSystemId = $this->sanitizeInput($this->request->getPost('external_system_id'));
        
        // API key for iframe integrations (can come from POST or session)
        $apiKey = $this->sanitizeInput($this->request->getPost('api_key')) ?: 
                  $this->sanitizeInput($this->request->getGet('api_key'));

        
        // Validate API key and get client_id if provided
        $clientId = null;
        if ($apiKey) {
            $apiKeyCWModel = new \App\Models\ApiKeyCWModel();
            $domain = $this->request->getServer('HTTP_REFERER') ? parse_url($this->request->getServer('HTTP_REFERER'), PHP_URL_HOST) : null;
            
            $validation = $apiKeyCWModel->validateApiKey($apiKey);
            
            if (!$validation['valid']) {
                return $this->jsonResponse(['error' => $validation['error']], 400);
            }
            
            // Get client_id directly from the API key data
            $keyData = $validation['key_data'];
            
            // The API key model should already have the client_id from the validation
            if (isset($keyData['client_id']) && $keyData['client_id']) {
                $clientId = (int)$keyData['client_id'];
            }
        }
        
        // CRITICAL FIX: Assign default client_id if none found
        // This ensures sessions appear in bo-livechat dashboard
        if ($clientId === null) {
            // Use a default client_id (typically the first/main client)
            // You may want to modify this logic based on your setup
            $apiKeyCWModel = $apiKeyCWModel ?? new \App\Models\ApiKeyCWModel();
            $defaultClient = $apiKeyCWModel->select('client_id')
                                       ->where('status', 'active')
                                       ->orderBy('id', 'ASC')
                                       ->first();
            if ($defaultClient) {
                $clientId = (int)$defaultClient['client_id'];
            }
        }
        
        // Validate role exists
        if (!$this->userRoleModel->getRoleByName($userRole)) {
            return $this->jsonResponse(['error' => 'Invalid user role specified'], 400);
        }
        
        // Check if role can access chat
        if (!$this->userRoleModel->canAccessChat($userRole)) {
            return $this->jsonResponse(['error' => 'This role is not allowed to access chat'], 403);
        }
        
        // Determine topic (required)
        $topic = $topicInput ?: $legacyNameInput;
        if (empty($topic)) {
            return $this->jsonResponse(['error' => 'Please describe what you need help with'], 400);
        }
        
        $sessionId = $this->generateSessionId();
        
        // Determine customer name based on role
        $customerName = 'Anonymous';
        $customerFullName = 'Anonymous';
        
        if ($userRole === 'loggedUser') {
            // For logged users, prioritize external user info
            if (!empty($externalFullname)) {
                $customerName = $externalFullname;
                $customerFullName = $externalFullname;
            } elseif (!empty($externalUsername)) {
                $customerName = $externalUsername;
                $customerFullName = $externalUsername;
            } elseif (!empty($customerNameInput)) {
                $customerName = $customerNameInput;
                $customerFullName = $customerNameInput;
            }
        } else {
            // For anonymous users, use provided name or extract from email
            if (!empty($customerNameInput)) {
                $customerName = $customerNameInput;
                $customerFullName = $customerNameInput;
            } elseif (!empty($email)) {
                // Try to extract name from email (part before @)
                $emailParts = explode('@', $email);
                if (!empty($emailParts[0])) {
                    $customerName = ucfirst($emailParts[0]);
                    $customerFullName = ucfirst($emailParts[0]);
                }
            }
        }
        
        // Additional context for logged users
        $additionalContext = [];
        if ($userRole === 'loggedUser') {
            $additionalContext = [
                'member_type' => 'verified_user',
                'login_method' => 'external_system',
                'session_source' => 'manual_form_submission' // vs auto-created
            ];
            
        }
        
        $data = [
            'session_id' => $sessionId,
            'customer_name' => $customerName,
            'customer_fullname' => $customerFullName,
            'chat_topic' => $topic,
            'customer_email' => $email,
            'customer_phone' => $customerPhone,
            'user_role' => $userRole,
            'external_username' => $externalUsername,
            'external_fullname' => $externalFullname,
            'external_system_id' => $externalSystemId,
            'api_key' => $apiKey,
            'client_id' => $clientId,
            'status' => 'waiting'
        ];
        
        // Merge additional context if available
        if (!empty($additionalContext)) {
            // Store additional context in a JSON field or use for logging
        }
        
        $chatId = $this->chatCWModel->insert($data);
        
        if ($chatId) {
            $this->session->set('chat_session_id', $sessionId);
            $this->session->set('user_role', $userRole);
            return $this->jsonResponse([
                'success' => true,
                'session_id' => $sessionId,
                'chat_id' => $chatId,
                'user_role' => $userRole
            ]);
        }
        
        return $this->jsonResponse(['error' => 'Failed to start chat session'], 500);
    }
    
    /**
     * Upload file for customers
     */
    public function uploadFile()
    {
        $sessionId = $this->request->getPost('session_id');
        $uploadedFile = $this->request->getFile('file');
        
        if (!$sessionId || !$uploadedFile || !$uploadedFile->isValid()) {
            return $this->jsonResponse(['error' => 'Session ID and valid file are required'], 400);
        }
        
        // Get the chat session
        $chatSession = $this->chatCWModel->getSessionBySessionId($sessionId);
        if (!$chatSession) {
            return $this->jsonResponse(['error' => 'Chat session not found'], 404);
        }
        
        // Check if session is active or waiting
        if (!in_array($chatSession['status'], ['active', 'waiting'])) {
            return $this->jsonResponse(['error' => 'Cannot send file to closed session'], 400);
        }
        
        try {
            // Use FileCompressionService to handle file upload and compression
            $compressionService = new \App\Services\FileCompressionService();
            $processResult = $compressionService->processFile($uploadedFile, $sessionId);
            
            if (!$processResult['success']) {
                return $this->jsonResponse(['error' => $processResult['error']], 400);
            }
            
            $fileData = $processResult['file_data'];
            
            // Check if this is a voice message
            $isVoiceMessage = $this->request->getPost('is_voice_message') === '1';
            if ($isVoiceMessage) {
                $fileData['file_type'] = 'voice';
            }
            
            // Create message text - empty for file uploads to avoid "sent a file" text
            $messageText = "";
            
            // Store message in MongoDB with file data
            $mongoModel = new \App\Models\MongoMessageCWModel();
            $messageData = [
                'session_id' => $chatSession['session_id'],
                'sender_type' => 'customer',
                'sender_id' => null,
                'sender_name' => $chatSession['customer_name'] ?: 'Customer',
                'sender_user_type' => null,
                'message' => $messageText,
                'message_type' => $fileData['file_type'],
                'file_data' => $fileData,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $messageId = $mongoModel->insertMessage($messageData);
            
            if ($messageId) {
                // Update session timestamp
                try {
                    $this->chatCWModel->update($chatSession['id'], ['updated_at' => date('Y-m-d H:i:s')]);
                } catch (\Exception $e) {
                    // Ignore timestamp update errors
                }
                
                return $this->jsonResponse([
                    'success' => true,
                    'message_id' => $messageId,
                    'session_id' => $sessionId,
                    'file_data' => $fileData,
                    'message' => 'File uploaded successfully'
                ]);
            }
            
            return $this->jsonResponse(['error' => 'Failed to send file message'], 500);
            
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => 'File upload failed: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Download file by message ID
     */
    public function downloadFile($messageId)
    {
        if (!$messageId) {
            error_log("Download failed: No message ID provided");
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        
        try {
            error_log("Attempting to download file for message ID: {$messageId}");
            
            // Get message from MongoDB
            $mongoModel = new \App\Models\MongoMessageCWModel();
            $message = $mongoModel->getMessageById($messageId);
            
            if (!$message) {
                error_log("Download failed: Message not found in MongoDB for ID: {$messageId}");
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
            
            if (!isset($message['file_data'])) {
                error_log("Download failed: No file_data in message for ID: {$messageId}");
                error_log("Message structure: " . json_encode($message));
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
            
            $fileData = $message['file_data'];
            $filePath = '/www/wwwroot/files/livechat/default/chat/' . $fileData['file_path'];
            
            error_log("Looking for file at path: {$filePath}");
            
            if (!file_exists($filePath)) {
                error_log("Download failed: File does not exist at path: {$filePath}");
                error_log("File data: " . json_encode($fileData));
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
            
            error_log("File found, serving download: {$fileData['original_name']}");
            
            // Force download
            $this->response->setHeader('Content-Type', $fileData['mime_type'])
                          ->setHeader('Content-Disposition', 'attachment; filename="' . $fileData['original_name'] . '"')
                          ->setHeader('Content-Length', (string) filesize($filePath))
                          ->setBody(file_get_contents($filePath));
            
            return $this->response;
            
        } catch (\Exception $e) {
            error_log("Download exception for message ID {$messageId}: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }
    
    /**
     * Get file thumbnail by message ID
     */
    public function getThumbnail($messageId)
    {
        if (!$messageId) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        
        try {
            // Get message from MongoDB
            $mongoModel = new \App\Models\MongoMessageCWModel();
            $message = $mongoModel->getMessageById($messageId);
            
            if (!$message || !isset($message['file_data']) || !$message['file_data']['thumbnail_path']) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
            
            $fileData = $message['file_data'];
            $thumbnailPath = '/www/wwwroot/files/livechat/default/thumbs/' . $fileData['thumbnail_path'];
            
            if (!file_exists($thumbnailPath)) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
            
            // Serve thumbnail
            $this->response->setHeader('Content-Type', 'image/jpeg')
                          ->setHeader('Content-Length', (string) filesize($thumbnailPath))
                          ->setBody(file_get_contents($thumbnailPath));
            
            return $this->response;
            
        } catch (\Exception $e) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }
    
    public function assignAgent()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $sessionId = $this->request->getPost('session_id');
        $agentId = $this->session->get('user_id');
        
        $updated = $this->chatCWModel->assignAgent($sessionId, $agentId);
        
        if ($updated) {
            return $this->jsonResponse(['success' => true]);
        }
        
        return $this->jsonResponse(['error' => 'Failed to assign agent'], 500);
    }
    
    public function getMessages($sessionId)
    {
        if (!$sessionId) {
            return $this->jsonResponse(['error' => 'Session ID is required'], 400);
        }
        
        try {
            // Use the correct MongoMessageCWModel class
            $mongoModel = new \App\Models\MongoMessageCWModel();
            $messages = $mongoModel->getSessionMessages($sessionId);
            return $this->jsonResponse([
                'success' => true,
                'messages' => $messages
            ]);
        } catch (\Exception $e) {
            error_log('Error getting messages: ' . $e->getMessage());
            return $this->jsonResponse(['error' => 'Failed to load messages'], 500);
        }
    }
    
    /**
     * Get messages with optional chat history for logged users
     */
    public function getMessagesWithHistory($sessionId)
    {
        // Get user identity from session or request parameters
        // Try PHP session first, but fall back to request parameters (important for new sessions)
        $userRole = $this->session->get('user_role') ?: $this->request->getGet('user_role');
        $externalUsername = $this->request->getGet('external_username');
        $externalFullname = $this->request->getGet('external_fullname');
        $externalSystemId = $this->request->getGet('external_system_id');
        
        // Default to current session messages only
        $includeHistory = false;
        
        // Only include history for logged users with valid identity
        if ($userRole === 'loggedUser' && ($externalUsername || $externalFullname)) {
            $includeHistory = true;
        }
        
        try {
            // Use the correct MongoMessageCWModel class
            $mongoModel = new \App\Models\MongoMessageCWModel();
            $result = $mongoModel->getSessionMessagesWithHistory(
                $sessionId, 
                $includeHistory, 
                $externalUsername, 
                $externalFullname, 
                $externalSystemId
            );
            
            // Check if result contains debug info
            if (is_array($result) && isset($result['messages']) && isset($result['debug'])) {
                return $this->jsonResponse([
                    'messages' => $result['messages'],
                    'debug' => $result['debug']
                ]);
            }
            
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            error_log('Error getting messages with history: ' . $e->getMessage());
            // Fallback to basic messages on error
            try {
                $mongoModel = new \App\Models\MongoMessageCWModel();
                $result = $mongoModel->getSessionMessages($sessionId);
                return $this->jsonResponse($result);
            } catch (\Exception $fallbackError) {
                error_log('Fallback also failed: ' . $fallbackError->getMessage());
                return $this->jsonResponse(['error' => 'Failed to load messages'], 500);
            }
        }
    }
    
    /**
     * Get chat history for a logged user (30 days)
     */
    public function getChatHistory()
    {
        $externalUsername = $this->sanitizeInput($this->request->getGet('external_username'));
        $externalFullname = $this->sanitizeInput($this->request->getGet('external_fullname'));
        $externalSystemId = $this->sanitizeInput($this->request->getGet('external_system_id'));
        $currentSessionId = $this->sanitizeInput($this->request->getGet('current_session_id'));
        
        // Validate that we have some form of user identification
        if (!$externalUsername && !$externalFullname) {
            return $this->jsonResponse(['error' => 'User identification required for chat history'], 400);
        }
        
        try {
            // Use the correct MongoMessageCWModel class
            $mongoModel = new \App\Models\MongoMessageCWModel();
            $messages = $mongoModel->getUserChatHistory(
                $externalUsername, 
                $externalFullname, 
                $externalSystemId, 
                30, // 30 days back
                $currentSessionId // Exclude current session
            );
            
            return $this->jsonResponse([
                'success' => true,
                'messages' => $messages,
                'count' => count($messages)
            ]);
            
        } catch (Exception $e) {
            error_log('Error loading chat history: ' . $e->getMessage());
            return $this->jsonResponse(['error' => 'Failed to load chat history'], 500);
        }
    }
    
    /**
     * Test MongoDB connection - debugging endpoint
     */
    public function testMongoDB()
    {
        try {
            $mongoModel = new \App\Models\MongoMessageCWModel();
            
            return $this->jsonResponse([
                'success' => true,
                'message' => 'MongoDB connection successful',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    public function closeSession()
    {
        $sessionId = $this->request->getPost('session_id');
        
        $updated = $this->chatCWModel->closeSession($sessionId);
        
        if ($updated) {
            return $this->jsonResponse(['success' => true]);
        }
        
        return $this->jsonResponse(['error' => 'Failed to close session'], 500);
    }
    
    
    /**
     * Customer leaves session - CLOSES the session completely for both customer and admin
     */
    public function endCustomerSession()
    {
        $sessionId = $this->request->getPost('session_id');
        
        if (!$sessionId) {
            return $this->jsonResponse(['error' => 'Session ID is required'], 400);
        }
        
        // Get the chat session to verify it exists
        $chatSession = $this->chatCWModel->getSessionBySessionId($sessionId);
        
        if (!$chatSession) {
            // Check if session ID exists in PHP session vs what was passed
            $phpSessionId = $this->session->get('chat_session_id');
            return $this->jsonResponse(['error' => 'Session not found', 'debug' => ['requested' => $sessionId, 'php_session' => $phpSessionId]], 404);
        }
        
        // Check if session is already closed
        if ($chatSession['status'] === 'closed') {
            // Still clear PHP session and return success
            $this->session->remove('chat_session_id');
            return $this->jsonResponse([
                'success' => true, 
                'message' => 'Chat session was already closed',
                'customer_left' => true
            ]);
        }
        
        // Close the session completely when customer leaves
        $sessionClosed = $this->chatCWModel->closeSession($sessionId);
        
        if ($sessionClosed) {
            // Add a system message that customer left and session is closed
            $messageData = [
                'session_id' => $chatSession['id'],
                'sender_type' => 'agent',
                'sender_id' => null,
                'message' => 'Customer left the chat - Session closed',
                'message_type' => 'system'
            ];
            
            $messageInserted = $this->messageCWModel->insert($messageData);
            
            // Send WebSocket notification to close the session for all participants
            if ($messageInserted) {
                $this->notifySessionClosed($sessionId);
            }
        }
        
        // Clear the customer's PHP session so they can't access this chat anymore
        $this->session->remove('chat_session_id');
        
        return $this->jsonResponse([
            'success' => true, 
            'message' => 'You have left the chat. The session has been closed.',
            'customer_left' => true,
            'session_closed' => true
        ]);
    }
    
    public function checkSessionStatus($sessionId)
    {
        $session = $this->chatCWModel->getSessionBySessionId($sessionId);
        
        if ($session) {
            return $this->jsonResponse([
                'status' => $session['status'],
                'session_id' => $sessionId
            ]);
        }
        
        return $this->jsonResponse(['error' => 'Session not found'], 404);
    }



    // Get chat history for a customer
    public function getCustomerHistory($customerId)
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $history = $this->chatCWModel->select('chat_sessions.*, users.username as agent_name')
                                 ->join('users', 'users.id = chat_sessions.agent_id', 'left')
                                 ->where('customer_id', $customerId)
                                 ->orderBy('created_at', 'DESC')
                                 ->findAll();

        return $this->jsonResponse($history);
    }

    // Rate chat session
    public function rateSession()
    {
        $sessionId = $this->request->getPost('session_id');
        $rating = $this->request->getPost('rating');
        $feedback = $this->request->getPost('feedback');

        if (!$sessionId || !$rating || $rating < 1 || $rating > 5) {
            return $this->jsonResponse(['error' => 'Invalid rating data'], 400);
        }

        $updated = $this->chatCWModel->where('session_id', $sessionId)
                                  ->set([
                                      'rating' => $rating,
                                      'feedback' => $feedback
                                  ])
                                  ->update();

        if ($updated) {
            return $this->jsonResponse(['success' => true]);
        }

        return $this->jsonResponse(['error' => 'Failed to save rating'], 500);
    }

    

    // Send canned response - Proxy to backend
    public function sendCannedResponse()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Frontend no longer handles canned responses directly
        // This should be handled by the backend admin system
        return $this->jsonResponse([
            'error' => 'Canned responses are managed by the admin system (livechat-bo)',
            'redirect' => 'Please use the admin backend for canned response functionality'
        ], 501);
    }

    // Get agent workload
    public function getAgentWorkload()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $agents = $this->userCWModel->select('id, username, current_chats, max_concurrent_chats, status, is_online')
                                 ->whereIn('role', ['admin', 'support'])
                                 ->findAll();

        return $this->jsonResponse($agents);
    }
    
    // Get active keyword responses for quick actions
    public function getQuickActions()
    {
        // Set CORS headers if needed for cross-origin requests
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');
        
        try {
            // Get API key from query parameter or header
            $apiKey = $this->request->getGet('api_key') ?: $this->request->getHeaderLine('X-API-Key');
            
            $quickActions = [];
            
            if ($apiKey) {
                // Use apiKeyCWModel to validate the API key and get client_id
                $apiKeyCWModel = new \App\Models\ApiKeyCWModel();
                $validation = $apiKeyCWModel->validateApiKey($apiKey);
                
                if ($validation['valid']) {
                    $keyData = $validation['key_data'];
                    $clientId = $keyData['client_id'];
                    
                    // Get keyword responses for this specific client
                    $keywordResponses = $this->keywordResponseModel->getActiveResponsesForClient($clientId);
                    
                    // Transform the data to match the expected format for frontend
                    foreach ($keywordResponses as $response) {
                        $quickActions[] = [
                            'keyword' => $response['keyword'],
                            'display_name' => $response['keyword'], // Using keyword as display name
                            'response' => $response['response']
                        ];
                    }
                }
            }
            
        } catch (\Exception $e) {
            // Log the exception for debugging
            error_log('Exception in getQuickActions: ' . $e->getMessage());
            $quickActions = [];
        }

        return $this->jsonResponse($quickActions);
    }



    // Bulk close inactive sessions
    public function closeInactiveSessions()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Close sessions inactive for more than 30 minutes
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        
        $inactiveSessions = $this->chatCWModel->where('status', 'active')
                                           ->where('updated_at <', $cutoffTime)
                                           ->findAll();

        $closedCount = 0;
        foreach ($inactiveSessions as $session) {
            $this->chatCWModel->update($session['id'], [
                'status' => 'closed',
                'closed_at' => date('Y-m-d H:i:s')
            ]);
            $closedCount++;
        }

        return $this->jsonResponse([
            'success' => true,
            'closed_sessions' => $closedCount
        ]);
    }
    
    /**
     * Get available user roles
     */
    public function getRoles()
    {
        $roles = $this->userRoleModel->getActiveRoles();
        return $this->jsonResponse($roles);
    }
    
    /**
     * Get session with role information
     */
    public function getSessionWithRole($sessionId)
    {
        $session = $this->chatCWModel->select('chat_sessions.*, user_roles.role_description, user_roles.can_see_chat_history')
                                  ->join('user_roles', 'user_roles.role_name = chat_sessions.user_role', 'left')
                                  ->where('chat_sessions.session_id', $sessionId)
                                  ->first();
        
        if ($session) {
            return $this->jsonResponse($session);
        }
        
        return $this->jsonResponse(['error' => 'Session not found'], 404);
    }
    
    /**
     * Get chatroom link for frontend integration
     * This is the actual endpoint that generates chatroom links
     */
    public function getChatroomLink()
    {
        // Explicitly set CORS headers for this endpoint
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
        header('Access-Control-Max-Age: 86400');
        
        try {
            // Get request data (support both POST and GET)
            $userId = $this->sanitizeInput($this->request->getPost('user_id')) ?: 
                     $this->sanitizeInput($this->request->getGet('user_id'));
            
            $sessionInfo = $this->sanitizeInput($this->request->getPost('session_info')) ?: 
                          $this->sanitizeInput($this->request->getGet('session_info'));
            
            $apiKey = $this->sanitizeInput($this->request->getPost('api_key')) ?: 
                     $this->sanitizeInput($this->request->getGet('api_key'));
            
            // Get domain for API key validation (if API key provided)
            $domain = $this->request->getServer('HTTP_ORIGIN') ?: $this->request->getServer('HTTP_REFERER');
            if ($domain) {
                $parsedUrl = parse_url($domain);
                $domain = $parsedUrl['host'] ?? $domain;
            }
            
            // Basic validation - at least user_id should be provided
            if (empty($userId)) {
                $userId = 'anonymous_' . uniqid();
            }
            
            // For now, generate a simple chatroom link based on user_id
            // This creates a direct link to the livechat system with the user context
            $baseUrl = rtrim(config('App')->baseURL, '/');
            
            // Generate chatroom parameters
            $chatroomParams = [
                'user_id' => $userId,
                'session_info' => $sessionInfo,
                'timestamp' => time(),
                'iframe' => '1' // Enable iframe mode for external integration
            ];
            
            // Add API key to params if provided
            if ($apiKey) {
                $chatroomParams['api_key'] = $apiKey;
            }
            
            // Add role information if it can be inferred
            if (!empty($sessionInfo) && strpos($sessionInfo, 'logged_user') !== false) {
                $chatroomParams['user_role'] = 'loggedUser';
                // Parse user info from session_info if structured
                if (strpos($sessionInfo, '|') !== false) {
                    $parts = explode('|', $sessionInfo);
                    foreach ($parts as $part) {
                        if (strpos($part, 'name:') === 0) {
                            $chatroomParams['external_fullname'] = substr($part, 5);
                        } elseif (strpos($part, 'username:') === 0) {
                            $chatroomParams['external_username'] = substr($part, 9);
                        } elseif (strpos($part, 'id:') === 0) {
                            $chatroomParams['external_system_id'] = substr($part, 3);
                        }
                    }
                }
            } else {
                $chatroomParams['user_role'] = 'anonymous';
            }
            
            // Build the chatroom URL
            $chatroomLink = $baseUrl . '/?' . http_build_query($chatroomParams);
            
            // Return successful response
            return $this->jsonResponse([
                'success' => true,
                'chatroom_link' => $chatroomLink,
                'user_id' => $userId,
                'timestamp' => time()
            ]);
            
        } catch (Exception $e) {
            // Log error but return a working fallback response
            error_log("getChatroomLink Error: " . $e->getMessage());
            
            $baseUrl = rtrim(config('App')->baseURL, '/');
            $fallbackUserId = $userId ?? 'anonymous_' . uniqid();
            
            return $this->jsonResponse([
                'success' => true,
                'chatroom_link' => $baseUrl . '/?user_id=' . urlencode($fallbackUserId) . '&iframe=1',
                'user_id' => $fallbackUserId,
                'timestamp' => time(),
                'note' => 'Fallback response - system error handled'
            ]);
        }
    }
    /**
     * Send message via API
     */
    public function sendMessage()
    {
        if (!$this->isAuthenticated()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $sessionId = $this->request->getPost('session_id');
        $message = $this->request->getPost('message');
        $messageType = $this->request->getPost('message_type') ?: 'text';
        $agentId = $this->session->get('user_id');

        if (!$sessionId || !$message) {
            return $this->jsonResponse(['error' => 'Session ID and message are required'], 400);
        }

        // Get chat session
        $chatSession = $this->chatCWModel->getSessionBySessionId($sessionId);
        if (!$chatSession) {
            return $this->jsonResponse(['error' => 'Chat session not found'], 404);
        }

        $messageData = [
            'session_id' => $chatSession['id'],
            'sender_type' => 'agent',
            'sender_id' => $agentId,
            'message' => $message,
            'message_type' => $messageType
        ];

        console.log(result);

        $messageId = $this->messageCWModel->insert($messageData);

        if ($messageId) {
            return $this->jsonResponse([
                'success' => true,
                'message_id' => $messageId
            ]);
        }

        return $this->jsonResponse(['error' => 'Failed to send message'], 500);
    }

    /**
     * Check chat status via API
     */
    public function checkStatus($sessionId)
    {
        $session = $this->chatCWModel->getSessionBySessionId($sessionId);
        
        if ($session) {
            return $this->jsonResponse([
                'status' => $session['status'],
                'session_id' => $sessionId,
                'agent_id' => $session['agent_id'] ?? null
            ]);
        }
        
        return $this->jsonResponse(['error' => 'Session not found'], 404);
    }

    /**
     * Send WebSocket notification to agents when customer leaves
     */
    private function notifyAgentsOfCustomerLeft($sessionId, $messageId)
    {
        // For now, we'll rely on the real-time message loading when admin refreshes
        // or we can implement a server-sent events or polling mechanism
        // The system message is already saved to database and will show in chat history
        
        // Future enhancement: Implement proper WebSocket broadcasting here
        // For now, the admin will see the message when they reload the chat history
    }
    
    /**
     * Send WebSocket notification to close session for all participants
     */
    private function notifySessionClosed($sessionId)
    {
        // This method can be extended to send WebSocket notifications
        // Currently, the session close will be detected when admin checks session status
        // or when the database is queried for session updates
        
        // Future enhancement: Send WebSocket message to close session for all connected clients
        // For now, admins will see the session as closed when they refresh or check status
    }
    
    /**
     * Find resumable session for logged user
     */
    private function findResumableSession($externalUsername, $externalFullname, $externalSystemId)
    {
        if (!$externalUsername && !$externalFullname) {
            return null;
        }
        
        // Use the model method to find resumable session (24 hours for logged users)
        $chatConfig = new \Config\Chat();
        $resumableWindowMinutes = $chatConfig->loggedUserResumableWindow / 60; // Convert seconds to minutes
        $session = $this->chatCWModel->getResumableSessionForUser($externalUsername, $externalFullname, $externalSystemId, $resumableWindowMinutes);
        
        if ($session) {
            return $session;
        }
        
        return null;
    }
    
    /**
     * Auto-create chat session for logged users
     */
    private function autoCreateSessionForLoggedUser($externalUsername, $externalFullname, $externalSystemId, $apiKey, $customerPhone = null, $externalEmail = null)
    {
        // Validate role exists
        if (!$this->userRoleModel->getRoleByName('loggedUser')) {
            return ['success' => false, 'error' => 'Invalid user role specified'];
        }
        
        // Check if role can access chat
        if (!$this->userRoleModel->canAccessChat('loggedUser')) {
            return ['success' => false, 'error' => 'This role is not allowed to access chat'];
        }
        
        // Validate API key and get client_id if provided
        $clientId = null;
        if ($apiKey) {
            $apiKeyCWModel = new \App\Models\ApiKeyCWModel();
            $domain = $this->request->getServer('HTTP_REFERER') ? parse_url($this->request->getServer('HTTP_REFERER'), PHP_URL_HOST) : null;
            $validation = $apiKeyCWModel->validateApiKey($apiKey, $domain);
            
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }
            
            // Get client_id from API key data
            $keyData = $validation['key_data'];
            if (isset($keyData['client_id']) && $keyData['client_id']) {
                $clientId = (int)$keyData['client_id'];
            }
        }
        
        // CRITICAL FIX: Assign default client_id for auto-created sessions
        if ($clientId === null) {
            $apiKeyCWModel = $apiKeyCWModel ?? new \App\Models\ApiKeyCWModel();
            $defaultClient = $apiKeyCWModel->select('client_id')
                                       ->where('status', 'active')
                                       ->orderBy('id', 'ASC')
                                       ->first();
            if ($defaultClient) {
                $clientId = (int)$defaultClient['client_id'];
            }
        }
        
        $sessionId = $this->generateSessionId();
        
        // Determine customer name based on available information
        $customerName = 'Member';
        $customerFullName = 'Member';
        
        if (!empty($externalFullname)) {
            $customerName = $externalFullname;
            $customerFullName = $externalFullname;
        } elseif (!empty($externalUsername)) {
            $customerName = $externalUsername;
            $customerFullName = $externalUsername;
        }
        
        // Additional context for auto-created sessions
        $sessionContext = [
            'member_type' => 'verified_user',
            'login_method' => 'external_system',
            'session_source' => 'auto_created',
            'auto_topic' => 'Member Support'
        ];
        
        $data = [
            'session_id' => $sessionId,
            'customer_name' => $customerName,
            'customer_fullname' => $customerFullName,
            'chat_topic' => 'Member Support', // Default topic for logged users
            'customer_email' => $externalEmail, // Use external email if available
            'customer_phone' => $customerPhone,
            'user_role' => 'loggedUser',
            'external_username' => $externalUsername,
            'external_fullname' => $externalFullname,
            'external_system_id' => $externalSystemId,
            'api_key' => $apiKey,
            'client_id' => $clientId,
            'status' => 'waiting'
        ];
        
        $chatId = $this->chatCWModel->insert($data);
        
        if ($chatId) {
            $this->session->set('chat_session_id', $sessionId);
            $this->session->set('user_role', 'loggedUser');
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'chat_id' => $chatId,
                'user_role' => 'loggedUser'
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to start chat session'];
    }
    
    /**
     * Helper method to get client ID from backend API
     */
    private function getClientIdFromBackend($clientEmail)
    {
        try {
            // Backend API endpoint URL
            // $backendUrl = 'https://kiosk-chat.kopisugar.cc/api/client/get-id-by-email';
            $backendUrl = 'https://api-taapin.danhar.cc/api/client/get-id-by-email';
            
            $postData = [
                'email' => $clientEmail
            ];
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $backendUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                curl_close($ch);
                error_log('Client ID lookup failed: cURL error - ' . curl_error($ch));
                return null;
            }
            
            curl_close($ch);
            
            if ($httpCode === 200) {
                $responseData = json_decode($response, true);
                if ($responseData && $responseData['success'] && isset($responseData['client_id'])) {
                    return $responseData['client_id'];
                }
            }
            
            error_log("Client ID lookup failed: HTTP {$httpCode}, Response: {$response}");
            return null;
            
        } catch (Exception $e) {
            error_log('Client ID lookup exception: ' . $e->getMessage());
            return null;
        }
    }
    protected function mergeWidgetSettingsWithDefaults(array $settings): array
    {
        $merged = array_merge([
            'widget_name' => 'Customer Support',
            'widget_color' => null,
            'behavior_options' => $this->behaviorDefaults,
        ], $settings);

        if (!isset($merged['behavior_options']) || !is_array($merged['behavior_options'])) {
            $merged['behavior_options'] = $this->behaviorDefaults;
        } else {
            $merged['behavior_options'] = array_merge($this->behaviorDefaults, $merged['behavior_options']);
        }

        return $merged;
    }
}