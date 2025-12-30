<?php

namespace App\Controllers\Clientzone;

use App\Controllers\General;

class WidgetWelcomeController extends General
{

    public function __construct()
    {
        helper('jwt');
    }

    /**
     * Get Welcome Settings (Client)
     */
    public function index()
    {
        if (!$this->isClientAuthenticated()) {
            return $this->jsonResponse([
                'status' => 'error',
                'error' => 'Unauthorized'
            ], 401);
        }

        $clientId = $this->getTokenClientId();

        if (!$clientId) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $data = $this->welcomeModel->getByClientId($clientId);

        return $this->respond([
            'status' => 'success',
            'data' => $data
        ]);
    }

  
    /**
     * Save / Update Welcome Settings (with logo upload)
     */
    public function save()
    {

    if (!$this->isClientAuthenticated()) {
        return $this->respond([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }

    $clientId = $this->getTokenClientId();

    if (!$clientId) {
        return $this->respond([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }

    $request = $this->request->getJSON(true);

    $show_logo    = (int) ($request['show_logo'] ?? 0);
    $welcome_text = $this->sanitizeInput($request['welcome_text'] ?? null);

    if (!$welcome_text) {
        return $this->respond([
            'status' => 'error',
            'message' => 'Welcome text is required'
        ], 422);
    }

    /**
     * Handle Logo Upload (Optional)
     */
    $logoUrl = null;

    if (!empty($request['image_base64']) && !empty($request['file_name'])) {

        $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $request['image_base64']);
        $imageData = base64_decode($base64);

        if ($imageData === false) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Base64 decode failed'
            ], 422);
        }

        /**
         * Save file
         */
        $uploadPath = FCPATH . 'uploads/welcome-logos/' . $clientId;

        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $extension = strtolower(pathinfo($request['file_name'], PATHINFO_EXTENSION));
        $fileName  = uniqid('welcome_', true) . '.' . $extension;

        file_put_contents($uploadPath . '/' . $fileName, $imageData);

        /**
         * Public URL
         */
        $logoUrl = '/uploads/welcome-logos/' . $clientId . '/' . $fileName;
    }

    /**
     * Prepare DB data
     */
    $data = [
        'client_id'    => $clientId,
        'show_logo'    => $show_logo,
        'welcome_text' => $welcome_text,
    ];

    // Only update logo_url if new image uploaded
    if ($logoUrl !== null) {
        $data['logo_url'] = $logoUrl;
    }

    $this->welcomeModel->saveByClient($data);

    return $this->respond([
        'status' => 'success',
        'message' => 'Welcome settings saved successfully',
        'data' => [
            'logo_url' => $logoUrl
        ]
    ]);
}

}
