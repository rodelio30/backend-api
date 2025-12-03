<?php

namespace App\Controllers\Clientzone;

use App\Controllers\BaseResourceController;

class AuthV2 extends BaseResourceController // <--- EXTEND BaseResourceController
{
    protected $format = 'json';

    public function __construct()
    {
        helper('jwt');
    }


    public function login()
    {
        $data = $this->request->getJSON(true);
    
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Username and password are required'
            ], 400);
        }

        return $this->attemptClientLogin($username, $password);
    }

    private function attemptClientLogin($username, $password)
    {
        // Check user in clients table
        $client = $this->clientModel->getByUsername($username);

        // 🔹 USER NOT FOUND
        if (!$client || empty($client['id'])) {
            return $this->respond([
                'status' => 'error',
                'message' => 'User does not exist'
            ], 401);
        } 

        // 🔹 WRONG PASSWORD
        if (!password_verify($password, $client['password'])) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Invalid password'
            ], 401);
        }


        if ($client && password_verify($password, $client['password'])) {

            // Generate token (replace with JWT later)
            // $token = base64_encode($client['id'] . '|' . time());
            // 🔹 JWT TOKEN PAYLOAD
            $payload = [
                'id'       => $client['id'],
                'username' => $client['username'],
                'email'    => $client['email'],
                'type'    => 'client'
            ];

            // 🔹 GENERATE TOKEN
            $token = generateJWT($payload);
            return $this->respond([
                'status' => 'success',
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id'       => $client['id'],
                    'username' => $client['username'],
                    'email'    => $client['email'],
                    'name'     => $client['full_name'] ?? '',
                    'type'     => 'client'
                ]
            ], 200);

        }

        return $this->respond([
            'status' => 'error',
            'message' => 'Invalid username or password'
        ], 401);
    }
}
