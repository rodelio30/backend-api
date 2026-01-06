<?php

namespace App\Controllers\Clientzone;

use App\Controllers\BaseController;

class QueueController extends BaseController
{
    /**
     * Get client_id from JWT token (works for both clients and agents)
     */
    private function getClientIdFromToken()
    {
        $userData = $this->request->userData ?? null;
        
        if (!$userData) {
            return null;
        }
        
        // If it's an agent, use their client_id
        if (isset($userData['type']) && $userData['type'] === 'agent' && isset($userData['client_id'])) {
            return (int) $userData['client_id'];
        }
        
        // If it's a client, use their id as client_id
        if (isset($userData['type']) && $userData['type'] === 'client' && isset($userData['id'])) {
            return (int) $userData['id'];
        }
        
        // Fallback: if just 'id' exists, use it
        if (isset($userData['id'])) {
            return (int) $userData['id'];
        }
        
        return null;
    }
    
    /**
     * Get agent_id from JWT token (only for agents)
     */
    private function getAgentIdFromToken()
    {
        $userData = $this->request->userData ?? null;
        
        if (!$userData) {
            return null;
        }
        
        // If it's an agent, return their agent_id
        if (isset($userData['type']) && $userData['type'] === 'agent' && isset($userData['id'])) {
            return (int) $userData['id'];
        }
        
        return null;
    }
    
    /**
     * GET /api/v1/clientzone/queue
     * Get list of customers waiting in queue
     */
    public function getQueue()
    {
        $clientId = $this->getClientIdFromToken();
        
        if (!$clientId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid token'
            ], 401);
        }
        
        try {
            // Get waiting sessions for this client
            $sessions = $this->chatModel->select('chat_sessions.*')
                ->where('chat_sessions.client_id', $clientId)
                ->where('chat_sessions.status', 'waiting')
                ->orderBy('chat_sessions.queue_priority', 'ASC')
                ->orderBy('chat_sessions.created_at', 'ASC')
                ->findAll();
            
            $queueList = [];
            $position = 1;
            
            foreach ($sessions as $session) {
                // Calculate wait time
                $createdAt = strtotime($session['created_at']);
                $waitTimeSeconds = time() - $createdAt;
                $waitTimeMinutes = floor($waitTimeSeconds / 60);
                $waitTimeFormatted = $this->formatWaitTime($waitTimeSeconds);
                
                // Get customer display name
                $customerName = $this->getCustomerDisplayName($session);
                
                // Get last message from MongoDB
                $lastMessage = $this->getLastMessage($session['session_id']);
                
                $queueList[] = [
                    'session_id' => $session['session_id'],
                    'position' => $position,
                    'customer_name' => $customerName,
                    'customer_email' => $session['customer_email'] ?? null,
                    'customer_phone' => $session['customer_phone'] ?? null,
                    'chat_topic' => $session['chat_topic'] ?? null,
                    'wait_time_seconds' => $waitTimeSeconds,
                    'wait_time_minutes' => $waitTimeMinutes,
                    'wait_time_formatted' => $waitTimeFormatted,
                    'created_at' => $session['created_at'],
                    'last_message' => $lastMessage,
                    'queue_priority' => $session['queue_priority'] ?? null,
                    'user_role' => $session['user_role'] ?? 'anonymous'
                ];
                
                $position++;
            }
            
            return $this->jsonResponse([
                'status' => 'success',
                'data' => [
                    'queue' => $queueList,
                    'total_waiting' => count($queueList)
                ]
            ], 200);
            
        } catch (\Exception $e) {
            log_message('error', 'Queue fetch error: ' . $e->getMessage());
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to fetch queue',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /api/v1/clientzone/queue/stats
     * Get queue statistics
     */
    public function getQueueStats()
    {
        $clientId = $this->getClientIdFromToken();
        
        if (!$clientId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid token'
            ], 401);
        }
        
        try {
            $todayStart = date('Y-m-d 00:00:00');
            
            // 1. Total customers waiting
            $totalWaiting = $this->chatModel
                ->where('client_id', $clientId)
                ->where('status', 'waiting')
                ->countAllResults();
            
            // 2. Average wait time (for current waiting customers)
            $waitingSessions = $this->chatModel
                ->select('created_at')
                ->where('client_id', $clientId)
                ->where('status', 'waiting')
                ->findAll();
            
            $averageWaitTime = 0;
            $longestWaitTime = 0;
            
            if (count($waitingSessions) > 0) {
                $totalWaitSeconds = 0;
                foreach ($waitingSessions as $session) {
                    $waitTime = time() - strtotime($session['created_at']);
                    $totalWaitSeconds += $waitTime;
                    if ($waitTime > $longestWaitTime) {
                        $longestWaitTime = $waitTime;
                    }
                }
                $averageWaitTime = floor($totalWaitSeconds / count($waitingSessions));
            }
            
            // 3. Total served today (sessions that were waiting and then became active today)
            $totalServedToday = $this->chatModel
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->where('accepted_at IS NOT NULL')
                ->where('accepted_at >=', $todayStart)
                ->countAllResults();
            
            // 4. Abandoned chats (sessions that went from waiting to closed without being active)
            $abandonedChats = $this->chatModel
                ->where('client_id', $clientId)
                ->where('status', 'closed')
                ->where('agent_id IS NULL')
                ->where('accepted_at IS NULL')
                ->where('created_at >=', $todayStart)
                ->countAllResults();
            
            return $this->jsonResponse([
                'status' => 'success',
                'data' => [
                    'total_waiting' => $totalWaiting,
                    'average_wait_time' => [
                        'seconds' => $averageWaitTime,
                        'minutes' => floor($averageWaitTime / 60),
                        'formatted' => $this->formatWaitTime($averageWaitTime)
                    ],
                    'longest_wait_time' => [
                        'seconds' => $longestWaitTime,
                        'minutes' => floor($longestWaitTime / 60),
                        'formatted' => $this->formatWaitTime($longestWaitTime)
                    ],
                    'total_served_today' => $totalServedToday,
                    'abandoned_chats_today' => $abandonedChats
                ]
            ], 200);
            
        } catch (\Exception $e) {
            log_message('error', 'Queue stats error: ' . $e->getMessage());
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to fetch queue statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /api/v1/clientzone/queue/assign
     * Manually assign a customer from queue to an agent
     * Body: { "session_id": "...", "agent_id": 123 }
     */
    public function assignToAgent()
    {
        $clientId = $this->getClientIdFromToken();
        $currentAgentId = $this->getAgentIdFromToken();
        
        if (!$clientId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid token'
            ], 401);
        }
        
        $data = $this->request->getJSON(true);
        $sessionId = $data['session_id'] ?? null;
        $agentId = $data['agent_id'] ?? $currentAgentId;
        
        if (!$sessionId || !$agentId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'session_id and agent_id are required'
            ], 400);
        }
        
        try {
            // Verify the session belongs to this client and is waiting
            $session = $this->chatModel
                ->where('session_id', $sessionId)
                ->where('client_id', $clientId)
                ->first();
            
            if (!$session) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Session not found or access denied'
                ], 404);
            }
            
            if ($session['status'] !== 'waiting') {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Session is not in waiting status'
                ], 400);
            }
            
            // Verify agent belongs to this client
            $agent = $this->agentModel->where('id', $agentId)
                ->where('client_id', $clientId)
                ->first();
            
            if (!$agent) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Agent not found or does not belong to this client'
                ], 404);
            }
            
            // Assign the agent
            $updated = $this->chatModel->where('session_id', $sessionId)
                ->set([
                    'agent_id' => $agentId,
                    'status' => 'active',
                    'accepted_at' => date('Y-m-d H:i:s'),
                    // 'accepted_by' => $agentId
                    'accepted_by' => $agent['username']
                ])
                ->update();
            
            if ($updated) {
                return $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Customer successfully assigned to agent',
                    'data' => [
                        'session_id' => $sessionId,
                        'agent_id' => $agentId,
                        'agent_name' => $agent['username']
                    ]
                ], 200);
            } else {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Failed to assign agent'
                ], 500);
            }
            
        } catch (\Exception $e) {
            log_message('error', 'Queue assign error: ' . $e->getMessage());
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to assign agent',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * PUT /api/v1/clientzone/queue/priority
     * Change customer priority in queue
     * Body: { "session_id": "...", "priority": 1 }
     */
    public function changePriority()
    {
        $clientId = $this->getClientIdFromToken();
        
        if (!$clientId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid token'
            ], 401);
        }
        
        $data = $this->request->getJSON(true);
        $sessionId = $data['session_id'] ?? null;
        $priority = $data['priority'] ?? null;
        
        if (!$sessionId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'session_id is required'
            ], 400);
        }
        
        if ($priority !== null && (!is_numeric($priority) || $priority < 0)) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'priority must be a non-negative number'
            ], 400);
        }
        
        try {
            // Verify the session belongs to this client and is waiting
            $session = $this->chatModel
                ->where('session_id', $sessionId)
                ->where('client_id', $clientId)
                ->first();
            
            if (!$session) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Session not found or access denied'
                ], 404);
            }
            
            if ($session['status'] !== 'waiting') {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Can only change priority for waiting sessions'
                ], 400);
            }
            
            // Update priority
            $updated = $this->chatModel->where('session_id', $sessionId)
                ->set(['queue_priority' => $priority])
                ->update();
            
            if ($updated) {
                return $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Queue priority updated successfully',
                    'data' => [
                        'session_id' => $sessionId,
                        'priority' => $priority
                    ]
                ], 200);
            } else {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Failed to update priority'
                ], 500);
            }
            
        } catch (\Exception $e) {
            log_message('error', 'Queue priority error: ' . $e->getMessage());
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to update priority',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * DELETE /api/v1/clientzone/queue/:sessionId
     * Remove customer from queue (close session)
     */
    public function removeFromQueue($sessionId = null)
    {
        $clientId = $this->getClientIdFromToken();
        
        if (!$clientId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid token'
            ], 401);
        }
        
        if (!$sessionId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'session_id is required'
            ], 400);
        }
        
        try {
            // Verify the session belongs to this client and is waiting
            $session = $this->chatModel
                ->where('session_id', $sessionId)
                ->where('client_id', $clientId)
                ->first();
            
            if (!$session) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Session not found or access denied'
                ], 404);
            }
            
            if ($session['status'] !== 'waiting') {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Can only remove sessions that are in waiting status'
                ], 400);
            }
            
            // Close the session
            $updated = $this->chatModel->where('session_id', $sessionId)
                ->set([
                    'status' => 'closed',
                    'closed_at' => date('Y-m-d H:i:s')
                ])
                ->update();
            
            if ($updated) {
                return $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Customer removed from queue successfully',
                    'data' => [
                        'session_id' => $sessionId
                    ]
                ], 200);
            } else {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Failed to remove from queue'
                ], 500);
            }
            
        } catch (\Exception $e) {
            log_message('error', 'Queue remove error: ' . $e->getMessage());
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to remove from queue',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * PUT /api/v1/clientzone/queue/transfer
     * Transfer customer to a different agent
     * Body: { "session_id": "...", "agent_id": 123 }
     */
    public function transferCustomer()
    {
        $clientId = $this->getClientIdFromToken();
        
        if (!$clientId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid token'
            ], 401);
        }
        
        $data = $this->request->getJSON(true);
        $sessionId = $data['session_id'] ?? null;
        $newAgentId = $data['agent_id'] ?? null;
        
        if (!$sessionId || !$newAgentId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'session_id and agent_id are required'
            ], 400);
        }
        
        try {
            // Verify the session belongs to this client and is active
            $session = $this->chatModel
                ->where('session_id', $sessionId)
                ->where('client_id', $clientId)
                ->first();
            
            if (!$session) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Session not found or access denied'
                ], 404);
            }
            
            if ($session['status'] !== 'active') {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Can only transfer active sessions'
                ], 400);
            }
            
            // Verify new agent belongs to this client
            $newAgent = $this->agentModel->where('id', $newAgentId)
                ->where('client_id', $clientId)
                ->first();
            
            if (!$newAgent) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Agent not found or does not belong to this client'
                ], 404);
            }
            
            $oldAgentId = $session['agent_id'];
            
            // Transfer to new agent
            $updated = $this->chatModel->where('session_id', $sessionId)
                ->set(['agent_id' => $newAgentId])
                ->update();
            
            if ($updated) {
                return $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Customer transferred successfully',
                    'data' => [
                        'session_id' => $sessionId,
                        'old_agent_id' => $oldAgentId,
                        'new_agent_id' => $newAgentId,
                        'new_agent_name' => $newAgent['username']
                    ]
                ], 200);
            } else {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Failed to transfer customer'
                ], 500);
            }
            
        } catch (\Exception $e) {
            log_message('error', 'Queue transfer error: ' . $e->getMessage());
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to transfer customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /api/v1/clientzone/queue/:sessionId/details
     * Get detailed information about a customer before accepting
     */
    public function getCustomerDetails($sessionId = null)
    {
        $clientId = $this->getClientIdFromToken();
        
        if (!$clientId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid token'
            ], 401);
        }
        
        if (!$sessionId) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'session_id is required'
            ], 400);
        }
        
        try {
            // Get session details
            $session = $this->chatModel
                ->where('session_id', $sessionId)
                ->where('client_id', $clientId)
                ->first();
            
            if (!$session) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Session not found or access denied'
                ], 404);
            }
            
            // Get customer display name
            $customerName = $this->getCustomerDisplayName($session);
            
            // Calculate wait time
            $createdAt = strtotime($session['created_at']);
            $waitTimeSeconds = time() - $createdAt;
            
            // Get last few messages from MongoDB
            $messages = $this->getRecentMessages($sessionId, 10);
            
            // Get customer history (previous sessions if logged user)
            $customerHistory = [];
            if ($session['external_username'] || $session['external_system_id']) {
                $customerHistory = $this->getCustomerHistory($session, $clientId);
            }
            
            return $this->jsonResponse([
                'status' => 'success',
                'data' => [
                    'session_id' => $sessionId,
                    'customer_name' => $customerName,
                    'customer_email' => $session['customer_email'] ?? null,
                    'customer_phone' => $session['customer_phone'] ?? null,
                    'chat_topic' => $session['chat_topic'] ?? null,
                    'user_role' => $session['user_role'] ?? 'anonymous',
                    'external_username' => $session['external_username'] ?? null,
                    'external_fullname' => $session['external_fullname'] ?? null,
                    'external_system_id' => $session['external_system_id'] ?? null,
                    'status' => $session['status'],
                    'created_at' => $session['created_at'],
                    'wait_time_seconds' => $waitTimeSeconds,
                    'wait_time_formatted' => $this->formatWaitTime($waitTimeSeconds),
                    'recent_messages' => $messages,
                    'customer_history' => $customerHistory,
                    'queue_priority' => $session['queue_priority'] ?? null
                ]
            ], 200);
            
        } catch (\Exception $e) {
            log_message('error', 'Queue customer details error: ' . $e->getMessage());
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to fetch customer details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Helper: Get customer display name
     */
    private function getCustomerDisplayName($session)
    {
        if (!empty($session['external_fullname']) && trim($session['external_fullname']) !== '') {
            return trim($session['external_fullname']);
        }
        
        if (!empty($session['customer_fullname']) && trim($session['customer_fullname']) !== '') {
            return trim($session['customer_fullname']);
        }
        
        if (!empty($session['customer_name']) && trim($session['customer_name']) !== '') {
            return trim($session['customer_name']);
        }
        
        return 'Anonymous';
    }
    
    /**
     * Helper: Get last message from MongoDB
     */
    private function getLastMessage($sessionId)
    {
        try {
            $mongoModel = new \App\Models\MongoMessageCWModel();
            $lastMessageInfo = $mongoModel->getLastMessageInfo($sessionId);
            
            if ($lastMessageInfo) {
                return [
                    'content' => $lastMessageInfo['content'] ?? '',
                    'sender_type' => $lastMessageInfo['sender_type'] ?? '',
                    'sent_at' => $lastMessageInfo['sent_at'] ?? null
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Failed to fetch last message: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Helper: Get recent messages from MongoDB
     */
    private function getRecentMessages($sessionId, $limit = 10)
    {
        try {
            $mongoModel = new \App\Models\MongoMessageCWModel();
            return $mongoModel->getRecentMessages($sessionId, $limit);
        } catch (\Exception $e) {
            log_message('error', 'Failed to fetch recent messages: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Helper: Get customer history
     */
    private function getCustomerHistory($session, $clientId)
    {
        try {
            $query = $this->chatModel
                ->where('client_id', $clientId)
                ->where('session_id !=', $session['session_id']);
            
            if (!empty($session['external_username'])) {
                $query->where('external_username', $session['external_username']);
            } elseif (!empty($session['external_system_id'])) {
                $query->where('external_system_id', $session['external_system_id']);
            } else {
                return [];
            }
            
            $history = $query->orderBy('created_at', 'DESC')
                ->limit(5)
                ->findAll();
            
            $result = [];
            foreach ($history as $h) {
                $result[] = [
                    'session_id' => $h['session_id'],
                    'status' => $h['status'],
                    'created_at' => $h['created_at'],
                    'closed_at' => $h['closed_at'] ?? null
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            log_message('error', 'Failed to fetch customer history: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Helper: Format wait time in human-readable format
     */
    private function formatWaitTime($seconds)
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return $minutes . ' min' . ($remainingSeconds > 0 ? ' ' . $remainingSeconds . ' sec' : '');
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ($minutes > 0 ? ' ' . $minutes . ' min' : '');
        }
    }
}

