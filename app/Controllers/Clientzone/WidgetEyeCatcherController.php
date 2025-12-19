<?php

namespace App\Controllers\Clientzone;

use CodeIgniter\HTTP\ResponseInterface;
use App\Controllers\General;

class WidgetEyeCatcherController extends General
{
    protected $format = 'json';
    protected $eyeCatcherModel;

    public function __construct()
    {
        helper('jwt');
    }

    /**
     * Get all eye-catcher images for client
     */
    public function index(): ResponseInterface
    {
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Token data missing'
            ]);
        }

        $payload  = (array) $tokenObject->data;
        $clientId = $payload['id'] ?? null;

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid client token'
            ]);
        }

        $items = $this->eyeCatcherModel
            ->where('client_id', $clientId)
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        return $this->respond([
            'status' => 'success',
            'data'   => $items
        ]);
    }

    /**
     * Upload eye-catcher image (Base64 JSON) and save to DB
     */
    public function uploadImage(): ResponseInterface
    {
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]);
        }

        $payload  = (array) $tokenObject->data;
        $clientId = $payload['id'] ?? null;

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid client token'
            ]);
        }

        $request = $this->request->getJSON(true);

        if (
            empty($request['image_base64']) ||
            empty($request['file_name'])
        ) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Invalid payload'
            ]);
        }

        /**
         * Decode Base64
         */
        $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $request['image_base64']);
        $imageData = base64_decode($base64);

        if ($imageData === false) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Base64 decode failed'
            ]);
        }

        /**
         * Save file to filesystem (CORRECT)
         */
        $uploadPath = FCPATH . 'uploads/eye-catchers/' . $clientId;

        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $extension = strtolower(pathinfo($request['file_name'], PATHINFO_EXTENSION));
        $fileName  = uniqid('eye_', true) . '.' . $extension;

        file_put_contents($uploadPath . '/' . $fileName, $imageData);

        /**
         * Public URL (store in DB)
         */
        $imageUrl = '/uploads/eye-catchers/' . $clientId . '/' . $fileName;

        /**
         * Save to DB
         */
        $data = [
            'client_id'        => $clientId,
            'image_url'        => $imageUrl,
            'sort_order'       => $request['sort_order'] ?? 1,
            'interval_seconds' => $request['interval_seconds'] ?? 3,
            'is_active'        => 1,
        ];

        $insertId = $this->eyeCatcherModel->insert($data);

        return $this->respond([
            'status'  => 'success',
            'message' => 'Eye-catcher uploaded and saved',
            'data' => [
                'id' => $insertId,
                'image_url' => $imageUrl,
                'sort_order' => $data['sort_order'],
                'interval_seconds' => $data['interval_seconds'],
                'is_active' => 1
            ]
        ]);
    }

    // public function update($id): ResponseInterface
    public function update($id = null)
    {
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]);
        }

        $payload  = (array) $tokenObject->data;
        $clientId = $payload['id'] ?? null;

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid client token'
            ]);
        }

        $eyeCatcher = $this->eyeCatcherModel->find($id);

        if (!$eyeCatcher || $eyeCatcher['client_id'] != $clientId) {
            return $this->response->setStatusCode(403)->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ]);
        }

        $request = $this->request->getJSON(true);

        $data = [
            'sort_order'       => $request['sort_order'] ?? $eyeCatcher['sort_order'],
            'interval_seconds' => $request['interval_seconds'] ?? $eyeCatcher['interval_seconds'],
            'is_active'        => $request['is_active'] ?? $eyeCatcher['is_active'],
        ];

        $this->eyeCatcherModel->update($id, $data);

        return $this->respond([
            'status'  => 'success',
            'message' => 'Eye-catcher updated',
            'data'    => $data
        ]);
    }
    // public function delete($id): ResponseInterface
    public function delete($id = null)
    {
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]);
        }

        $payload  = (array) $tokenObject->data;
        $clientId = $payload['id'] ?? null;

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid client token'
            ]);
        }

        $eyeCatcher = $this->eyeCatcherModel->find($id);

        if (!$eyeCatcher || $eyeCatcher['client_id'] != $clientId) {
            return $this->response->setStatusCode(403)->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ]);
        }

        /**
         * Delete file from filesystem
         * image_url is relative: /uploads/eye-catchers/...
         */
        $filePath = FCPATH . ltrim($eyeCatcher['image_url'], '/');

        if (is_file($filePath)) {
            unlink($filePath);
        }

        $this->eyeCatcherModel->delete($id);

        return $this->respond([
            'status'  => 'success',
            'message' => 'Eye-catcher deleted'
        ]);
    }
}
