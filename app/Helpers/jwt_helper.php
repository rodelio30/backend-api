<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generateJWT($payload)
{
    $key = getenv('JWT_SECRET');
    $issuedAt = time();
    $expire = $issuedAt + (60 * 60 * 24); // 24 hours

    $token = [
        'iat' => $issuedAt,
        'exp' => $expire,
        'data' => $payload
    ];

    return JWT::encode($token, $key, 'HS256');
}

function decodeJWT($token)
{
    $key = getenv('JWT_SECRET');

    return JWT::decode($token, new Key($key, 'HS256'));
}

function decodeJWTV2(string $token): object
{
    $key = getenv('JWT_SECRET');

    if (!$key) {
        throw new Exception('JWT_SECRET is missing from environment variables');
    }

    return JWT::decode($token, new Key($key, 'HS256'));
}
