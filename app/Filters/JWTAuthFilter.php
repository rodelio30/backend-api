<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('jwt');

        // $header = $request->getHeaderLine('Authorization');

        // if (!$header || !str_starts_with($header, 'Bearer ')) {
        //     return service('response')
        //             ->setStatusCode(401)
        //             ->setJSON(['message' => 'Missing Authorization header']);
        // }


        // $token = str_replace('Bearer ', '', $header);

        $authHeader = $request->getHeaderLine('Authorization');
        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return Services::response()->setJSON(['error' => 'Token missing'])->setStatusCode(401);
        }
        
        $token = substr($authHeader, 7); // Remove "Bearer "

        try {
            $decoded = decodeJWTV2($token);

            // Store the decoded token for controller access
            $request->clientToken = $decoded;

            // Extract only the payload data – NOT the full token object
            // $request->userData = (array) $decoded->data;
            // Add the userData payload for easy access in controllers
            if (isset($decoded->data)) {
                $request->userData = (array) $decoded->data;
            }
            
            return $request;

        } catch (\Exception $e) {
            return service('response')
                    ->setStatusCode(401)
                    ->setJSON([
                        'status' => 'error',
                        'type' => 'auth',
                        'message' => 'Invalid token',
                        'error' => ENVIRONMENT === 'development' ? $e->getMessage() : null
                        // 'error' => $e->getMessage()
                    ]);
        }

        // $authorization = $request->getHeaderLine('Authorization');

        // if (!$authorization || !str_starts_with($authorization, 'Bearer ')) {
        //     return Services::response()
        //         ->setJSON([
        //             'status' => 'error',
        //             'message' => 'Missing or invalid Authorization header'
        //         ])
        //         ->setStatusCode(401);
        // }

        // $token = substr($authorization, 7);
        // $secret = getenv('JWT_SECRET');

        // try {
        //     // $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        //     // // store decoded data so controllers can access it
        //     // $request->userData = (array) $decoded->data;

        //     $decoded = decodeJWTV2($token);

        //     // Store the decoded token for controller access
        //     $request->clientToken = $decoded;
        // } catch (\Exception $e) {
        //     return Services::response()
        //         ->setJSON([
        //             'status' => 'error',
        //             'message' => 'Invalid or expired token'
        //         ])
        //         ->setStatusCode(401);
        // }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Not used for now
    }
}
