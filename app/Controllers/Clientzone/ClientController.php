<?php

namespace App\Controllers\Clientzone;

use App\Controllers\General;
use App\Controllers\BaseResourceController;

// class ClientController extends BaseController
class ClientController extends General
{
    public function dashboard()
    {
        // if (!$this->isClientAuthenticated()) {
        //     return redirect()->to(getDomainSpecificUrl('login', 'client'));
        // }
        
        // $clientId = service('request')->clientId;
        // $currentUser = $this->getCurrentClientUser();

        $currentUser = $this->getCurrentClientUser();
        $clientId = $this->getClientId();
        
        
        // Get client's API keys
        $apiKeys = $this->apiKeyModel->where('client_id', $clientId)->findAll();
        
        // Get chat sessions data for this client
        $sessions = $this->chatModel->where('client_id', $clientId)->findAll();
        
        // Count sessions by status
        $totalSessions = count($sessions);
        $activeSessions = 0;
        $waitingSessions = 0;
        $closedSessions = 0;
        
        foreach ($sessions as $session) {
            switch ($session['status']) {
                case 'active':
                    $activeSessions++;
                    break;
                case 'waiting':
                    $waitingSessions++;
                    break;
                case 'closed':
                    $closedSessions++;
                    break;
            }
        }
        
        // Get agents count (only for clients, not agents)
        $agentsCount = 0;
        $hasAgents = false;
        if ($this->isClientUser()) {
            $agentsCount = $this->agentModel->where('client_id', $clientId)->countAllResults();
            $hasAgents = $agentsCount > 0;
        }
        
        $data = [
            'title' => $this->isClientUser()
                ? trans('Client.dashboard.title_client', 'Client Dashboard')
                : trans('Client.dashboard.title_agent', 'Agent Dashboard'),
            'user' => $currentUser,
            'totalApiKeys' => count($apiKeys),
            'activeApiKeys' => count(array_filter($apiKeys, fn($key) => $key['status'] === 'active')),
            'totalSessions' => $totalSessions,
            'activeSessions' => $activeSessions,
            'waitingSessions' => $waitingSessions,
            'closedSessions' => $closedSessions,
            'agentsCount' => $agentsCount,
            'api_keys' => $apiKeys,
            'hasAgents' => $hasAgents,
            'showAgentModal' => $this->isClientUser() && !$hasAgents
        ];
        
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $data
        ]);

    }
}
