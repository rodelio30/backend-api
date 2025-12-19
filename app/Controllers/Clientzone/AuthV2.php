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

        // USER NOT FOUND
        if (!$client || empty($client['id'])) {
            return $this->respond([
                'status' => 'error',
                'message' => 'User does not exist'
            ], 401);
        } 

        // WRONG PASSWORD
        if (!password_verify($password, $client['password'])) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Invalid password'
            ], 401);
        }


        if ($client && password_verify($password, $client['password'])) {

            // Generate token (replace with JWT later)
            // $token = base64_encode($client['id'] . '|' . time());
            // JWT TOKEN PAYLOAD
            $payload = [
                'id'       => $client['id'],
                'username' => $client['username'],
                'email'    => $client['email'],
                'type'    => 'client'
            ];

            // GENERATE TOKEN
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

    public function register()
    {
        $data = $this->request->getJSON(true);

        $fullname = trim(($data['firstname'] ?? '') . ' ' . ($data['lastname'] ?? ''));
        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $confirmPassword = $data['confirm_password'] ?? null;

        if (!$fullname || !$username || !$email || !$password || !$confirmPassword) {
            return $this->respond([
                'status' => 'error',
                'message' => 'All fields are required'
            ], 400);
        }

        // Check if passwords match
        if ($password !== $confirmPassword) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Passwords do not match'
            ], 400);
        }

        // Validate password strength
        if (!$this->validatePasswordStrength($password)) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Password must be at least 6 characters with one number, one uppercase and one lowercase letter'
            ], 400);
        }

        // Check if email already exists
        $existingEmail = $this->clientModel->getByEmail($email);
        if ($existingEmail) {
            return $this->respond([
                'status' => 'error',
                'message' => 'An account with this email already exists. Please log in instead.'
            ], 400);
        }
        
        // Check if username already exists
        $existingUsername = $this->clientModel->getByUsername($username);
        if ($existingUsername) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Username already exists. Please choose a different username.'
            ], 400);
        }

        return $this->attemptRegister($fullname, $username, $email, $password, $confirmPassword);
    }

    public function attemptRegister($fullname, $username, $email, $password, $confirmPassword)
    {
        // Create new client account
        $defaultLocale = $this->localeService->getDefaultLocale();

        $data = [
            'full_name' => $fullname,
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active',
            'preferred_locale' => $defaultLocale,
        ];
        
        $clientId = $this->clientModel->insert($data);
        
        if ($clientId) {
            // Auto-generate API key for the new client
            $apiKeyModel = new \App\Models\ApiKeyModel();
            
            $apiKeyData = [
                'client_id' => $clientId,
                'key_id' => $apiKeyModel->generateKeyId(),
                'api_key' => $apiKeyModel->generateApiKey(),
                'client_name' => $username,
                'client_email' => $email,
                'status' => 'active'
            ];
            
            $apiKeyCreated = $apiKeyModel->insert($apiKeyData);

            // return $this->respond([
            //     'status' => 'success',
            //     'message' => 'Registration successful',
            //     'client_id' => $clientId,
            //     'api_key' => $apiKeyData['api_key']
            // ]);
            
            if ($apiKeyCreated) {
                // Automatically log the user in
                $payload = [
                    'client_user_id' => $clientId,
                    'client_username' => $username,
                    'client_email' => $email,
                    'user_type' => 'client'
                ];

                // GENERATE TOKEN
                $token = generateJWT($payload);

                return $this->respond([
                    'status' => 'success',
                    'message' => 'Account created successfully! Welcome to your dashboard.',
                    'token' => $token,
                    'client_id' => $clientId,
                    'api_key' => $apiKeyData['api_key']
                ]);
            } else {
                $this->clientModel->delete($clientId);
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Failed to create account. Please try again.',
                ]);
            }
        }
        
        return $this->respond([
            'status' => 'error',
            'message' => 'Failed to create account. Please try again.',
        ]);
    }
    private function validatePasswordStrength($password)
    {
        // At least 6 characters
        if (strlen($password) < 6) {
            return false;
        }
        
        // Contains at least one number
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // Contains at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // Contains at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        return true;
    }
}
