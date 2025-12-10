<?php
// namespace App\Controllers;
namespace App\Controllers\ChatWidget;

use App\Controllers\BaseResourceController;

class WebhookController extends BaseResourceController
{
    public function handleIncoming($provider)
    {
        $payload = $this->request->getJSON(true);
        
        switch ($provider) {
            case 'slack':
                return $this->handleSlackWebhook($payload);
            case 'teams':
                return $this->handleTeamsWebhook($payload);
            case 'email':
                return $this->handleEmailWebhook($payload);
            default:
                return $this->jsonResponse(['error' => 'Unknown provider'], 400);
        }
    }
    
    private function handleSlackWebhook($payload)
    {
        // Handle Slack integration
        // Create chat session from Slack message
        if (isset($payload['event']['type']) && $payload['event']['type'] === 'message') {
            $customerName = $payload['event']['user'] ?? 'Slack User';
            $message = $payload['event']['text'] ?? '';
            
            // Create new chat session
            $sessionId = $this->generateSessionId();
            $chatData = [
                'session_id' => $sessionId,
                'customer_name' => $customerName,
                'customer_email' => null,
                'status' => 'waiting'
            ];
            
            $chatId = $this->chatCWModel->insert($chatData);
            
            // Add initial message
            $messageData = [
                'session_id' => $chatId,
                'sender_type' => 'customer',
                'message' => $message,
                'message_type' => 'text'
            ];
            
            $this->messageCWModel->insert($messageData);
            
            return $this->jsonResponse(['success' => true, 'session_id' => $sessionId]);
        }
        
        return $this->jsonResponse(['success' => true]);
    }
    
    public function statusUpdate()
    {
        // Handle status updates from external systems
        $sessionId = $this->request->getPost('session_id');
        $status = $this->request->getPost('status');
        $message = $this->request->getPost('message');
        
        if ($sessionId && $status) {
            $this->chatCWModel->where('session_id', $sessionId)
                          ->set(['status' => $status])
                          ->update();
            
            if ($message) {
                $session = $this->chatCWModel->getSessionBySessionId($sessionId);
                if ($session) {
                    $this->messageCWModel->insert([
                        'session_id' => $session['id'],
                        'sender_type' => 'agent',
                        'message' => $message,
                        'message_type' => 'system'
                    ]);
                }
            }
        }
        
        return $this->jsonResponse(['success' => true]);
    }
    
    private function handleTeamsWebhook($payload)
    {
        // Handle Microsoft Teams integration
        // Implementation for Teams webhook
        return $this->jsonResponse(['success' => true]);
    }
    
    private function handleEmailWebhook($payload)
    {
        // Handle email integration
        // Implementation for email webhook
        return $this->jsonResponse(['success' => true]);
    }
}