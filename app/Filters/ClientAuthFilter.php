<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ClientAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
{
    $authHeader = $request->getHeaderLine('Authorization');

    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        return service('response')->setStatusCode(401)->setJSON([
            'status' => 'error',
            'message' => 'Token missing'
        ]);
    }

    // Extract token
    $token = substr($authHeader, 7);

    $secretKey = getenv('JWT_SECRET'); 

    try {
        $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secretKey, 'HS256'));

        // Attach user data to request
        $request->userData = (array) $decoded;

    } catch (\Exception $e) {
        return service('response')->setStatusCode(401)->setJSON([
            'status' => 'error',
            'message' => 'Invalid token',
            'error' => $e->getMessage()
        ]);
    }
}

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
