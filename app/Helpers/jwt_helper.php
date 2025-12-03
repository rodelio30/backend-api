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
