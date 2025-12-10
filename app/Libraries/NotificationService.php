<?php

namespace App\Libraries;

class NotificationService
{
    private $config;
    private $email;
    
    public function __construct()
    {
        $this->config = config('Chat');
        $this->email = \Config\Services::email();
    }
    
    public function notifyNewChat($sessionId)
    {
        if ($this->config->enableEmailNotifications) {
            $this->sendNewChatEmail($sessionId);
        }
        
        if ($this->config->enableSlackIntegration) {
            $this->sendSlackNotification($sessionId);
        }
    }
    
    public function notifyAgentAssigned($sessionId, $agentId)
    {
        // Send notification to customer
        $this->sendCustomerNotification($sessionId, 'agent_assigned');
    }
    
    public function notifySessionClosed($sessionId)
    {
        // Send satisfaction survey
        $this->sendSatisfactionSurvey($sessionId);
    }
    
    private function sendNewChatEmail($sessionId)
    {
        $chatCWModel = new \App\Models\ChatCWModel();
        $session = $chatCWModel->getSessionBySessionId($sessionId);
        
        if (!$session) return;
        
        $this->email->setTo('support@example.com');
        $this->email->setSubject('New Chat Session - ' . $session['customer_name']);
        $this->email->setMessage("
            A new chat session has been started by {$session['customer_name']}.
            
            Customer Email: {$session['customer_email']}
            Session ID: {$sessionId}
            Started: {$session['created_at']}
            
            Please assign an agent to handle this chat.
        ");
        
        $this->email->send();
    }
    
    private function sendSlackNotification($sessionId)
    {
        // Implement Slack webhook notification
        $slackWebhook = env('SLACK_WEBHOOK_URL');
        if (!$slackWebhook) return;
        
        $chatCWModel = new \App\Models\ChatCWModel();
        $session = $chatCWModel->getSessionBySessionId($sessionId);
        
        if (!$session) return;
        
        $payload = [
            'text' => 'New Chat Session',
            'attachments' => [
                [
                    'color' => 'good',
                    'fields' => [
                        [
                            'title' => 'Customer',
                            'value' => $session['customer_name'],
                            'short' => true
                        ],
                        [
                            'title' => 'Session ID',
                            'value' => $sessionId,
                            'short' => true
                        ]
                    ]
                ]
            ]
        ];
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $slackWebhook);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curl);
        curl_close($curl);
    }
    
    private function sendSatisfactionSurvey($sessionId)
    {
        $chatCWModel = new \App\Models\ChatCWModel();
        $session = $chatCWModel->getSessionBySessionId($sessionId);
        
        if (!$session['customer_email']) return;
        
        $surveyUrl = base_url("survey/{$sessionId}");
        
        $this->email->setTo($session['customer_email']);
        $this->email->setSubject('How was your chat experience?');
        $this->email->setMessage("
            Hi {$session['customer_name']},
            
            Thank you for contacting our support team. We'd love to hear about your experience.
            
            Please take a moment to rate your chat session: {$surveyUrl}
            
            Your feedback helps us improve our service.
            
            Best regards,
            Support Team
        ");
        
        $this->email->send();
    }
}