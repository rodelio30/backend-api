<?php

namespace App\Controllers\Clientzone;

use App\Controllers\General;
use CodeIgniter\HTTP\ResponseInterface;

class ClientController extends General
{
    protected $format = 'json';

    public function __construct()
    {
        helper('jwt');
    }

    public function dashboard(): ResponseInterface
    {
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 'error',
                'message' => 'Token data missing (filter did not pass data)'
            ]);
        }

        $payload = (array) $tokenObject->data;

        $clientId   = $payload['id'] ?? null;
        $clientName = $payload['username'] ?? null;
        $currentUser = $this->getCurrentClientUser();

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 'error',
                'message' => 'Invalid token payload'
            ]);
        }

        /**
         * ================================
         * DASHBOARD METRICS
         * ================================
         */

        // 🔹 Agents
        $totalAgents = $this->agentModel
            ->where('client_id', $clientId)
            ->countAllResults();

        $loggedInAgents = $this->chatModel
            ->getLoggedInAgentsToday($clientId);

        // 🔹 Customers
        $totalCustomers = $this->chatModel
            ->getTotalUniqueCustomers($clientId);

        $onlineCustomersToday = $this->chatModel
            ->getTodayOnlineCustomersFromMongo($clientName);


        // 🔹 Chats
        $chatStats = $this->chatModel
            ->getDashboardChatStatsByClient($clientId);

        /**
         * ================================
         * RESPONSE
         * ================================
         */
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => [
                'agents' => [
                    'total'            => $totalAgents,
                    'logged_in_today'  => $loggedInAgents
                ],
                'customers' => [
                    'total'        => $totalCustomers,
                    'online_today' => $onlineCustomersToday
                ],
                'chats' => [
                    'ongoing'        => $chatStats['ongoing'],
                    'today_new'      => $chatStats['today_new'],
                    'today_queued'   => $chatStats['today_queued'],
                    'last_7_days'    => $chatStats['last_7_days']
                ]
                ],
        ]);
    }
}
