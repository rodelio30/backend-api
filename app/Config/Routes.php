<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// $routes->group('api/v1/clientzone', function($routes) {
//     $routes->post('login', 'Clientzone\AuthV2::login');
// });

$routes->post('api/v1/clientzone/login', 'Clientzone\AuthV2::login');

$routes->group('api/v1/clientzone', ['filter' => 'clientAuth'], function($routes) {
    $routes->get('dashboard', 'Clientzone\ClientController::dashboard');
});

$routes->get('test-mongo', 'TestMongoController::index');


// Chat routes (Customer side)
$routes->get('/chat', 'ChatWidget\ChatController::index');
$routes->post('/chat/start-session', 'ChatWidget\ChatController::startSession');
$routes->post('/chat/assign-agent', 'ChatWidget\ChatController::assignAgent');

$routes->get('/chat/messages/(:segment)', 'ChatWidget\ChatController::getMessages/$1');
$routes->get('/chat/messages-with-history/(:segment)', 'ChatWidget\ChatController::getMessagesWithHistory/$1');
$routes->get('/chat/chat-history', 'ChatWidget\ChatController::getChatHistory');
$routes->post('/chat/close-session', 'ChatWidget\ChatController::closeSession');
$routes->get('/chat/check-session-status/(:segment)', 'ChatWidget\ChatController::checkSessionStatus/$1');
$routes->post('/chat/end-customer-session', 'ChatWidget\ChatController::endCustomerSession');
$routes->post('/chat/rate-session', 'ChatWidget\ChatController::rateSession');
$routes->post('/chat/canned-response', 'ChatWidget\ChatController::sendCannedResponse');
$routes->get('/chat/customer-history/(:segment)', 'ChatWidget\ChatController::getCustomerHistory/$1');
$routes->get('/chat/quick-actions', 'ChatWidget\ChatController::getQuickActions');
$routes->get('/agent/workload', 'ChatWidget\ChatController::getAgentWorkload');
$routes->get('/chat/test-mongodb', 'ChatWidget\ChatController::testMongoDB');

// File upload routes
$routes->post('/chat/upload-file', 'ChatWidget\ChatController::uploadFile');
$routes->get('/chat/download-file/(:segment)', 'ChatWidget\ChatController::downloadFile/$1');
$routes->get('/chat/thumbnail/(:segment)', 'ChatWidget\ChatController::getThumbnail/$1');

// Real-time notifications (for WebSocket fallback)
$routes->group('api/notifications', function($routes) {
    $routes->get('poll', 'ChatWidget\NotificationController::poll');
    $routes->post('mark-read', 'ChatWidget\NotificationController::markRead');
});

// Webhook routes (for third-party integrations)
$routes->group('webhook', function($routes) {
    $routes->post('incoming/(:segment)', 'ChatWidget\WebhookController::handleIncoming/$1');
    $routes->post('status-update', 'ChatWidget\WebhookController::statusUpdate');
});

// API routes for WebSocket fallback (optional)
$routes->group('api', function($routes) {
    $routes->post('chat/send-message', 'ChatWidget\ChatController::sendMessage');
    $routes->get('chat/check-status/(:segment)', 'ChatWidget\ChatController::checkStatus/$1');
    
    // CORS preflight OPTIONS route for getChatroomLink (must come before match route)
    $routes->options('getChatroomLink', function() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(200);
        exit();
    });
    
    // Frontend integration route for getting chatroom links
    $routes->match(['get', 'post'], 'getChatroomLink', 'ChatWidget\ChatController::getChatroomLink');
    
    // CORS preflight OPTIONS routes for widget API
    $routes->options('widget/validate', function() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(200);
        exit();
    });
    $routes->options('widget/validate-session', function() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(200);
        exit();
    });
    $routes->options('widget/log-message', function() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(200);
        exit();
    });
    $routes->options('widget/config', function() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(200);
        exit();
    });
    
    // Widget API validation routes (no auth filter - public API)
    $routes->post('widget/validate', 'ChatWidget\WidgetAuthController::validateWidget');
    $routes->post('widget/validate-session', 'ChatWidget\WidgetAuthController::validateChatStart');
    $routes->post('widget/log-message', 'ChatWidget\WidgetAuthController::logMessageSent');
    $routes->get('widget/config', 'ChatWidget\WidgetConfigController::publicConfig');
});