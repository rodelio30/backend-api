<?php
// namespace App\Controllers;
namespace App\Controllers\Clientzone;

use App\Controllers\BaseResourceController;

class NotificationController extends BaseResourceController
{
    public function poll()
    {
        if (!$this->session->get('user_id') && !$this->session->get('chat_session_id')) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $lastCheck = $this->request->getGet('last_check');
        $userType = $this->request->getGet('user_type');
        $sessionId = $this->request->getGet('session_id');
        
        $notifications = [];
        
        if ($userType === 'agent') {
            $notifications = $this->getAgentNotifications($lastCheck);
        } else {
            $notifications = $this->getCustomerNotifications($sessionId, $lastCheck);
        }
        
        return $this->jsonResponse([
            'notifications' => $notifications,
            'timestamp' => time()
        ]);
    }
    
    private function getAgentNotifications($lastCheck)
    {
        $notifications = [];
        
        // New sessions waiting
        $waitingSessions = $this->userCWModel->where('status', 'waiting')
                                          ->where('created_at >', date('Y-m-d H:i:s', $lastCheck))
                                          ->findAll();
        
        foreach ($waitingSessions as $session) {
            $notifications[] = [
                'type' => 'new_chat',
                'message' => "New chat from {$session['customer_name']}",
                'session_id' => $session['session_id'],
                'timestamp' => strtotime($session['created_at'])
            ];
        }
        
        return $notifications;
    }
    
    private function getCustomerNotifications($sessionId, $lastCheck)
    {
        $notifications = [];
        
        if ($sessionId) {
            $session = $this->userCWModel->getSessionBySessionId($sessionId);
            if ($session) {
                // Check for new agent messages
                $newMessages = $this->messageCWModel->where('session_id', $session['id'])
                                                 ->where('sender_type', 'agent')
                                                 ->where('created_at >', date('Y-m-d H:i:s', $lastCheck))
                                                 ->findAll();
                
                foreach ($newMessages as $message) {
                    $notifications[] = [
                        'type' => 'new_message',
                        'message' => 'New message from agent',
                        'content' => $message['message'],
                        'timestamp' => strtotime($message['created_at'])
                    ];
                }
            }
        }
        
        return $notifications;
    }
}