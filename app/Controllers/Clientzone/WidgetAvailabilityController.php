<?php

namespace App\Controllers\Clientzone;

use App\Controllers\General;
use App\Models\ClientWidgetAvailabilityModel;

class WidgetAvailabilityController extends General
{
    // protected $format = 'json';
    protected $availabilityModel;

    public function __construct()
    {
        helper('jwt');
        $this->availabilityModel = new ClientWidgetAvailabilityModel();
    }

    /**
     * Get availability settings
     */
    public function index()
    {
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]);
        }

        $clientId = $tokenObject->data->id ?? null;

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid client token'
            ]);
        }

        $settings = $this->availabilityModel->getByClientId($clientId);

        // Default values if not yet saved
        if (!$settings) {
            $settings = [
                'allow_chat'   => 1,
                'enable_queue' => 1,
                'max_queue_size' => null,
            ];
        }

        return $this->respond([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    /**
     * Save availability settings
     */
    public function save()
    {
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]);
        }

        $clientId = $tokenObject->data->id ?? null;

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid client token'
            ]);
        }

        $payload = $this->request->getJSON(true);

        $data = [
            'client_id'    => $clientId,
            // 'allow_chat'   => 1, // ALWAYS enabled
            'allow_chat'   => (int) ($payload['allow_chat'] ?? 1),
            'enable_queue' => (int) ($payload['enable_queue'] ?? 1),
            'max_queue_size' => $payload['max_queue_size'] ?? null,
        ];

        $existing = $this->availabilityModel->getByClientId($clientId);

        if ($existing) {
            $this->availabilityModel
                ->where('client_id', $clientId)
                ->set($data)
                ->update();
        } else {
            $this->availabilityModel->insert($data);
        }

        return $this->respond([
            'status' => 'success',
            'message' => 'Availability settings saved'
        ]);
    }
}
