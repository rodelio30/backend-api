<?php
namespace App\Controllers\Clientzone;

use Google\Client as GoogleClient;
use App\Models\ClientModel;
use App\Models\ApiKeyModel;
use App\Controllers\BaseResourceController;

class GoogleAuthController extends BaseResourceController
{
    protected $googleClient;
    protected $clientModel;

    public function __construct()
    {
        helper('jwt');

        $this->clientModel = new ClientModel();

        // Setup Google OAuth Client
        $this->googleClient = new GoogleClient();
        $this->googleClient->setClientId(getenv('google.client_id'));
    }

    /**
     * API Login using Google ID Token (MOBILE / POSTMAN / FETCH API)
     */
    public function googleSignIn()
    {
        $json = $this->request->getJSON(true);
        $idToken = $json['id_token'] ?? null;

        if (!$idToken) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Missing Google ID Token'
            ])->setStatusCode(400);
        }

        // Validate ID token
        $payload = $this->googleClient->verifyIdToken($idToken);

        if (!$payload) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid Google token'
            ])->setStatusCode(401);
        }

        // Extract Google user info
        $email = $payload['email'];
        $fullName = $payload['name'] ?? '';
        $username = explode('@', $email)[0];

        // Check if user exists
        $client = $this->clientModel->getByEmail($email);

        // If client exists -> login
        if ($client) {
            // Prevent logging in via Google if account is local
            if ($client['oauth_provider'] === 'local') {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'This email is registered using password login. Please sign in with your email and password.'
                ])->setStatusCode(403);
            }

            //  Allow login only if provider is google
            if ($client['oauth_provider'] !== 'google') {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'This account was registered using a different login method.'
                ])->setStatusCode(403);
            }

            $jwtPayload = [
                // 'client_user_id' => $client['id'],
                // 'client_username' => $client['username'],
                // 'client_email' => $client['email'],
                // 'user_type' => 'client'
                'id'       => $client['id'],
                'username' => $client['username'],
                'email'    => $client['email'],
                'name'     => $client['full_name'] ?? '',
                'type'     => 'client'
            ];

            $token = generateJWT($jwtPayload);

            return $this->respond([
                'status' => 'success',
                'message' => 'Account Exist! Google login successful',
                'token' => $token,
                'client' => [
                    'id' => $client['id'],
                    'username' => $client['username'],
                    'email' => $client['email']
                ]
            ]);
        }

        // Create new client account
        $defaultLocale = $this->localeService->getDefaultLocale();

        // Auto-register
        $data = [
            'full_name'        => $fullName,
            'username'         => $username,
            'email'            => $email,
            'password'         => null,
            'status'           => 'active',
            'preferred_locale' => $defaultLocale,
            'oauth_provider'   => 'google'
        ];

        $this->clientModel->setValidationRule('password', 'permit_empty');
    
        $clientId = $this->clientModel->insert($data);

        if (!$clientId) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Failed to create client',
                // 'errors' => $this->clientModel->errors(),
                // 'db_error' => $this->clientModel->db->error()
            ]);
        }

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

        if ($apiKeyCreated) {
            // Automatically log the user in
            $payload = [
                'client_user_id' => $clientId,
                'client_username' => $username,
                'client_email' => $email,
                'user_type' => 'client',
                'api_key' => $apiKeyData['api_key']
            ];

            // 🔹 GENERATE TOKEN
            $token = generateJWT($payload);

            return $this->respond([
                'status' => 'success',
                'message' => 'Google sign-in successful',
                'token' => $token,
                'client' => [
                    'id' => $clientId,
                    'username' => $username,
                    'email'    => $email 
                ],
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
}
