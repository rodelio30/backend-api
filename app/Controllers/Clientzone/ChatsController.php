<?php

namespace App\Controllers\Clientzone;

use App\Controllers\General;
use CodeIgniter\HTTP\ResponseInterface;

class ChatsController extends General
{
    public function __construct()
    {
        helper('jwt');
    }

    /**
     * Get Waiting Chats (Left Panel)
     */
    public function index(): ResponseInterface
    {
        if (!$this->isClientAuthenticated()) {
            return $this->respondUnauthorized();
        }

        $clientId = $this->getTokenClientId();

        // Get API keys for this client
        $apiKeys = $this->apiKeyModel
            ->select('api_key')
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->findAll();

        $apiKeyValues = array_column($apiKeys, 'api_key');

        // Get waiting chat sessions
        $waitingSessions = $this->chatModel
            ->where('client_id', $clientId)
            ->where('status', 'waiting')
            ->orderBy('created_at', 'ASC') // FIFO queue
            ->findAll();


         // Format response for UI
        $waiting = [];
        foreach ($waitingSessions as $session) {

            // 🔹 Get last message preview (Mongo)
            $lastMessage = $this->mongoMessageModel
                ->getLastMessageInfo($session['session_id']);

            $waiting[] = [
                'session_id'    => $session['session_id'],
                'customer_name' => $session['customer_name'] ?: 'Guest',
                'initials'      => strtoupper(substr($session['customer_name'] ?? 'G', 0, 2)),
                'topic'         => $session['chat_topic'] ?? 'Support',
                'waiting_since' => $session['created_at'],
                'waiting_time'  => $this->formatWaitingTime($session['created_at']),
                'last_message'  => $lastMessage['display_text'] ?? 'No messages yet',
                'api_key'       => $session['api_key'] ?? null,
            ];
        }

        // Get active chat sessions
        $activeSessions = $this->chatModel
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->orderBy('updated_at', 'DESC')
        ->findAll();

        $active = [];

        foreach ($activeSessions as $session) {

            // Get last message preview from MongoDB
            $lastMessage = $this->mongoMessageModel
                ->getLastMessageInfo($session['session_id']);

            $active[] = [
                'session_id'     => $session['session_id'],
                'customer_name'  => $session['customer_name'] ?: 'Guest',
                'initials'       => strtoupper(substr($session['customer_name'] ?? 'G', 0, 2)),
                'last_message'   => $lastMessage['display_text'] ?? 'No messages yet',
                'last_message_at'=> $session['updated_at'],
                'has_unread'     => $lastMessage['is_waiting'] ?? false,
                'agent_id'       => $session['agent_id'],
            ];
        }

        return $this->respond([
            'status' => 'success',
            'data' => [
                'waiting' => $waiting,
                'active'  => $active,
                'counts'  => [
                    'waiting' => count($waiting),
                    'active'  => count($active)
                ]
            ]
        ]);
    }

    /**
     * Accept Chat Session
     */
    public function accept(string $sessionId): ResponseInterface
    {
        if (!$this->isClientAuthenticated()) {
            return $this->respondUnauthorized();
        }

        if (!$sessionId) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Session ID is required'
            ], 400);
        }

        $userType = $this->getTokenUserType();
        $userId     = $this->getTokenClientId();   // agent_id OR client_id
        $username   = $this->getTokenUsername() ?? ucfirst($userType);

        $updated = false;

        switch ($userType) {
            case 'agent':
                $updated = $this->chatModel->assignAgent(
                    $sessionId,
                    $userId,
                    $username
                );
                break;

            case 'client':
                $updated = $this->chatModel->assignClient(
                    $sessionId,
                    $userId,
                    $username
                );
                break;

            default:
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Invalid user type'
                ], 403);
        }

        if (!$updated) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Failed to accept session or session already assigned'
            ], 409);
        }

        return $this->respond([
            'status'     => 'success',
            'message'    => 'Session accepted successfully',
            'session_id' => $sessionId
        ]);
    }

    /**
     * Get Chat Messages
     */
    public function messages(string $sessionId)
    {
        if (!$this->isClientAuthenticated()) {
            return $this->respondUnauthorized();
        }

        $clientId = $this->getTokenClientId();

        $session = $this->chatCWModel
            ->where('session_id', $sessionId)
            ->where('client_id', $clientId)
            ->first();

        if (!$session) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Session not found'
            ]);
        }

        $mongoModel = new \App\Models\MongoMessageCWModel();
        $messages = $mongoModel->getSessionMessages($sessionId);

        return $this->respond([
            'status' => 'success',
            'data' => $messages
        ]);
    }

    /**
     * Send Message (Agent/Admin)
     */
    public function sendMessage(string $sessionId)
    {
        if (!$this->isClientAuthenticated()) {
            return $this->respondUnauthorized();
        }

        $data = $this->request->getJSON(true);

        $sessionId   = $this->sanitizeInput($data['session_id'] ?? null);
        $message     = $this->sanitizeInput($data['message'] ?? null);
        $messageType = $this->sanitizeInput($data['message_type'] ?? 'text');

        if (!$sessionId || !$message) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'Session ID and message are required'
            ]);
        }

        // Verify session ownership
        $session = $this->chatModel
            ->where('session_id', $sessionId)
            ->where('client_id', $clientId)
            ->whereIn('status', ['waiting', 'active'])
            ->first();

        if (!$session) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Chat session not found or access denied'
            ]);
        }

        // Store message in MongoDB
        $mongoModel = new \App\Models\MongoMessageModel();
        $senderId = $this->request->clientToken->data->user_id
                    ?? $this->request->clientToken->data->id
                    ?? null;

        $messageId = $mongoModel->insertMessage([
            'session_id'     => $sessionId,
            'sender_type'    => 'agent',
            'sender_id'      => $senderId,
            'sender_name'    => 'Agent',
            'message'        => $message,
            'message_type'   => $messageType,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s')
        ]);

        if (!$messageId) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Failed to send message'
            ]);
        }

        // Update session last activity
        try {
            $this->chatModel->update($session['id'], [
                'updated_at' => date('Y-m-d H:i:s'),
                'status'     => 'active'
            ]);
        } catch (\Exception $e) {
            // Ignore timestamp update errors
        }

        return $this->respond([
            'status'     => 'success',
            'message_id' => $messageId
        ]);
    }


    /**
     * Get Session / Customer Details (Right Panel)
     */
    // public function show(string $sessionId): ResponseInterface
    public function sessionDetails(string $sessionId)
    {
        if (!$this->isClientAuthenticated()) {
            return $this->respondUnauthorized();
        }

        $clientId = $this->getTokenClientId();

        $session = $this->chatModel->getSessionDetails(
            $sessionId,
            $clientId
        );

        if (!$session) {
            return $this->respondNotFound('Chat session not found');
        }

        return $this->respond([
            'status' => 'success',
            'data'   => $session
        ]);
    }

    /**
     * Close Chat Session
     */
    public function close(string $sessionId): ResponseInterface
    {
        if (!$this->isClientAuthenticated()) {
            return $this->respondUnauthorized();
        }

        $clientId = $this->getTokenClientId();
        $agent    = $this->getCurrentClientUser();

        $closed = $this->chatModel->closeSession(
            $sessionId,
            $clientId,
            $agent['id']
        );

        if (!$closed) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Unable to close chat'
            ], 400);
        }

        return $this->respond([
            'status'  => 'success',
            'message' => 'Chat closed'
        ]);
    }

    private function formatWaitingTime(string $createdAt): string
    {
        $created = strtotime($createdAt);
        $now = time();
        $diff = $now - $created;

        if ($diff < 60) {
            return 'just now';
        }

        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }

        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }

        return floor($diff / 86400) . 'd ago';
    }
}
