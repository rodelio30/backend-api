<?php

namespace App\Controllers\Clientzone;

use App\Controllers\General;

class WidgetTagController extends General
{
    public function __construct()
    {
        helper('jwt');
    }

    /**
     * List Tags
     */
    public function index()
    {
        if (!$this->isClientAuthenticated()) {
            return $this->respondUnauthorized();
        }

        $clientId = $this->getTokenClientId();

        $tags = $this->tagModel->getByClient($clientId);

        return $this->respond([
            'status' => 'success',
            'data' => $tags
        ]);
    }

    /**
     * Create Tag
     */
    public function create()
    {
        if (!$this->isClientAuthenticated()) {
            return $this->respondUnauthorized();
        }

        $clientId = $this->getTokenClientId();
        $userName = $this->getTokenUserName();
        $userType = $this->getTokenUserType();

        $request  = $this->request->getJSON(true);

        $tag         = strtolower(trim($request['tag'] ?? ''));
        // $tag  = $this->santizeInput($request['tag'] ?? '');
        // $author_type = $this->sanitizeInput($request['author_type'] ?? null);

        if (!$tag) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Tag is required'
            ], 422);
        }

        if ($this->tagModel->exists($clientId, $tag)) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Tag already exists'
            ], 409);
        }

        $this->tagModel->insert([
            'client_id'   => $clientId,
            'tag'         => $tag,
            'author_type' => $userType ?? 'Client',
            'author_name' => $userName ?? 'Client'
        ]);

        return $this->respond([
            'status' => 'success',
            'message' => 'Tag created successfully'
        ]);
    }

    /**
     * Delete Tag
     */
    public function delete($id = null)
    {
        if (!$this->isClientAuthenticated()) {
            return $this->respondUnauthorized();
        }

        $clientId = $this->getTokenClientId();

        $tag = $this->tagModel->where([
            'id' => $id,
            'client_id' => $clientId
        ])->first();

        if (!$tag) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Tag not found'
            ], 404);
        }

        $this->tagModel->delete($id);

        return $this->respond([
            'status' => 'success',
            'message' => 'Tag deleted successfully'
        ]);
    }
}
