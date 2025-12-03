<?php

namespace App\Controllers\Clientzone;

// Extend BaseResourceController to get the session service
use App\Controllers\BaseResourceController; 

class Profile extends BaseResourceController
{
    protected $format = 'json';

    public function index()
    {
        // The filter has already ensured the session exists.
        // We can safely retrieve the logged-in user's data from the session.
        $userId = $this->session->get('client_user_id');
        $username = $this->session->get('client_username');

        // In a real application, you would use $userId to fetch fresh data from the database.
        
        return $this->respond([
            'status' => 'success',
            'message' => 'Client profile data retrieved successfully',
            'profile_data' => [
                'user_id' => $userId,
                'username' => $username,
                'status' => 'Session Active'
            ]
        ]);
    }
}