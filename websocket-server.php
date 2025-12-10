<?php

require 'vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Libraries\ChatServer;
use React\Socket\Server;
use React\Socket\SecureServer;

// Use the correct event loop method
$loop = \React\EventLoop\Loop::get();

// SSL options
$sslOptions = [
    'local_cert' => '/www/server/panel/vhost/cert/backend-domain.cc/fullchain.pem',
    'local_pk' => '/www/server/panel/vhost/cert/backend-domain.cc/privkey.pem',
    'verify_peer' => false,
    'allow_self_signed' => true,
    'verify_peer_name' => false
];

try {
    // Create secure server with proper event loop
    $tcpServer = new Server('0.0.0.0:39147', $loop);
    $secureServer = new SecureServer($tcpServer, $loop, $sslOptions);
    
    // Create ChatServer instance
    $chatServer = new ChatServer();
    
    $server = new IoServer(
        new HttpServer(
            new WsServer(
                $chatServer
            )
        ),
        $secureServer,
        $loop
    );
    
    // Start role-based session cleanup (anonymous users only)
    $chatServer->startPeriodicCleanup();
    
    echo "Secure WebSocket server started on port 39147 (wss://)\n";
    echo "Role-based session cleanup enabled (anonymous users: 30min timeout, logged users: never timeout)\n";
    $loop->run();  // Use $loop->run() instead of $server->run()
    
} catch (Exception $e) {
    echo "SSL setup failed, falling back to regular WebSocket on port 39146\n";
    echo "Error: " . $e->getMessage() . "\n";
    
    // Fallback to regular WebSocket
    $chatServerFallback = new ChatServer();
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                $chatServerFallback
            )
        ),
        39146
    );
    
    // Start role-based session cleanup for fallback server too
    $chatServerFallback->startPeriodicCleanup();
    
    echo "Regular WebSocket server started on port 39146\n";
    echo "Role-based session cleanup enabled (anonymous users: 30min timeout, logged users: never timeout)\n";
    $server->run();
}