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
        $authorization = $request->getHeaderLine('Authorization');

        if (!$authorization || !str_starts_with($authorization, 'Bearer ')) {
            return Services::response()
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Missing or invalid Authorization header'
                ])
                ->setStatusCode(401);
        }

        $token = substr($authorization, 7);
        $secret = getenv('JWT_SECRET');

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            // store decoded data so controllers can access it
            $request->userData = (array) $decoded->data;
        } catch (\Exception $e) {
            return Services::response()
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid or expired token'
                ])
                ->setStatusCode(401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Not used for now
    }
}
