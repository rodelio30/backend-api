<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// $routes->group('api/v1/clientzone', function($routes) {
//     $routes->post('login', 'Clientzone\AuthV2::login');
// });

// $routes->post('api/v1/clientzone/login', 'Clientzone\AuthV2::login');
// $routes->post('api/v1/clientzone/register', 'Clientzone\AuthV2::attempRegister');

$routes->group('api/v1/clientzone/widget', ['filter' => 'jwtAuth'], function($routes) {
    // $routes->get('widget/chat-widget', 'Clientzone\WidgetSettingsController::chatWidget');
    $routes->get('chat',           'Clientzone\WidgetSettingsController::getWidgetSettings');
    $routes->get('customization',  'Clientzone\WidgetSettingsController::customization');
    $routes->get('chat-page',      'Clientzone\WidgetSettingsController::chatPage');
    // $routes->get('language',       'Clientzone\WidgetSettingsController::language');
    // $routes->get('availability',   'Clientzone\WidgetSettingsController::availability');
    // $routes->get('welcome-screen', 'Clientzone\WidgetSettingsController::welcomeScreen');

    $routes->get('fetch', 'Clientzone\WidgetSettingsController::fetchSettings');
    $routes->post('update', 'Clientzone\WidgetSettingsController::updateSettings');

    // Widget Settings Language Management Routes
    $routes->group('language', function ($routes) {
        // Fetch widget config (language + phrases)
        $routes->get('config', 'Clientzone\WidgetLanguageController::getWidgetConfig');

        // Get available languages for client
        $routes->get('locale', 'Clientzone\WidgetLanguageController::getLanguages');

        // Save/update language phrases (Admin)
        $routes->post('save', 'Clientzone\WidgetLanguageController::saveLanguagePhrases');
    });

    $routes->group('eye-catcher', function ($routes) {
        $routes->get('/', 'Clientzone\WidgetEyeCatcherController::index');
        $routes->post('upload-image', 'Clientzone\WidgetEyeCatcherController::uploadImage');
        $routes->put('(:num)', 'Clientzone\WidgetEyeCatcherController::update/$1');
        $routes->delete('(:num)', 'Clientzone\WidgetEyeCatcherController::delete/$1');
    });

        $routes->group('availability', function ($routes) {
        $routes->get('', 'Clientzone\WidgetAvailabilityController::index');
        $routes->post('', 'Clientzone\WidgetAvailabilityController::save');
    });

    $routes->group('welcome', function ($routes) {
        $routes->get('', 'Clientzone\WidgetWelcomeController::index');
        $routes->post('', 'Clientzone\WidgetWelcomeController::save');
    });

    // Tags
    $routes->group('tags', function ($routes) {
        $routes->get('', 'Clientzone\WidgetTagController::index');
        $routes->post('', 'Clientzone\WidgetTagController::create');
        $routes->delete('(:num)', 'Clientzone\WidgetTagController::delete/$1');
    });
});

$routes->group('api/v1/clientzone/', ['filter' => 'jwtAuth'], function ($routes) {
    $routes->get('dashboard', 'Clientzone\ClientController::dashboard');
    
    // Queue Management Routes
    $routes->group('queue', function ($routes) {
        // Get queue list
        $routes->get('/', 'Clientzone\QueueController::getQueue');
        
        // Get queue statistics
        $routes->get('stats', 'Clientzone\QueueController::getQueueStats');
        
        // Assign customer to agent
        $routes->post('assign', 'Clientzone\QueueController::assignToAgent');
        
        // Change customer priority
        $routes->put('priority', 'Clientzone\QueueController::changePriority');
        
        // Transfer customer to different agent
        $routes->put('transfer', 'Clientzone\QueueController::transferCustomer');
        
        // Remove customer from queue
        $routes->delete('(:segment)', 'Clientzone\QueueController::removeFromQueue/$1');
        
        // Get customer details
        $routes->get('(:segment)/details', 'Clientzone\QueueController::getCustomerDetails/$1');
    });
        // Canned Responses CRUD
    $routes->get('canned-responses-for-api-key', 'Clientzone\CannedResponseController::getCannedResponsesForApiKey');
    $routes->get('get-canned-response/(:segment)', 'Clientzone\CannedResponseController::getCannedResponse/$1');
    $routes->post('save-canned-response', 'Clientzone\CannedResponseController::saveCannedResponse');
    $routes->post('delete-canned-response', 'Clientzone\CannedResponseController::deleteCannedResponse');
    $routes->post('toggle-canned-response-status', 'Clientzone\CannedResponseController::toggleCannedResponseStatus');
    // Widget Availability (already correct)
    $routes->group('widget', function ($routes) {
        $routes->group('availability', function ($routes) {
            $routes->get('/', 'Clientzone\WidgetAvailabilityController::index');
            $routes->post('/', 'Clientzone\WidgetAvailabilityController::save');
        });
    });

    // Agent Management (Client only)
    $routes->group('agents', function ($routes) {
        $routes->get('', 'Clientzone\AgentsController::getAgents');
        $routes->post('first', 'Clientzone\AgentsController::createFirstAgent');
        $routes->post('create', 'Clientzone\AgentsController::createAgent');
        $routes->post('update', 'Clientzone\AgentsController::updateAgent');
        $routes->post('delete', 'Clientzone\AgentsController::deleteAgent');
    });

    $routes->group('forms/pre-chat', function ($routes) {
        $routes->get('', 'Clientzone\PreChatFormController::getForm');

        $routes->put('status', 'Clientzone\PreChatFormController::updateStatus');
        $routes->put('campaign', 'Clientzone\PreChatFormController::updateCampaignVisibility');

        $routes->post('elements', 'Clientzone\PreChatFormController::addElement');
        $routes->put('elements', 'Clientzone\PreChatFormController::updateElement');
        $routes->delete('elements', 'Clientzone\PreChatFormController::deleteElement');
        $routes->put('elements/sort', 'Clientzone\PreChatFormController::sortElements');
    });

    // Keyword Responses routes (clients only)
    $routes->group('keyword-response', function ($routes) {
        $routes->get('', 'Clientzone\KeywordResponseController::keywordResponses');
        $routes->get('get/(:segment)', 'Clientzone\KeywordResponseController::getKeywordResponse/$1');
        $routes->post('save', 'Clientzone\KeywordResponseController::saveKeywordResponse');
        $routes->post('delete', 'Clientzone\KeywordResponseController::deleteKeywordResponse');
    });
});

    // CANNED RESPONSE API Action execution (used by live chat)
    $routes->post('api/canned-response-action', 'CannedResponseActionController::execute');
    $routes->options('api/canned-response-action', 'CannedResponseActionController::execute');

$routes->group('api/v1/clientzone', function($routes) {
    $routes->post('login', 'Clientzone\AuthV2::login');
    $routes->post('register', 'Clientzone\AuthV2::register');

    // API endpoint for login using Google ID Token
    $routes->POST('google-signin', 'Clientzone\GoogleAuthController::googleSignIn');


    $routes->group('ws/widget', function($routes) {
        // $routes->post('chat/send-message', 'ChatController::sendMessage');
        // $routes->get('chat/check-status/(:segment)', 'ChatController::checkStatus/$1');
        
        // Client lookup API (for frontend integration)
        // $routes->post('client/get-id-by-email', 'ClientController::getClientIdByEmail');
        
        // Widget API validation routes (no auth filter - public API)
        // $routes->post('widget/validate', 'WidgetAuthController::validateWidget');
        // $routes->post('widget/validate-session', 'WidgetAuthController::validateChatStart');
        // $routes->post('widget/log-message', 'WidgetAuthController::logMessageSent');
        $routes->get('config', 'Clientzone\WidgetSettingsController::publicConfig');
    });
});

$routes->get('test-mongo', 'TestMongoController::index');


// Chat API routes (Clientzone - Authenticated)
$routes->group('api/v1/clientzone/chat', ['filter' => 'jwtAuth'], function ($routes) {
    $routes->get('sessions', 'ChatWidget\ChatController::apiGetChatSessions');
    $routes->post('send-message', 'ChatWidget\ChatController::apiSendMessage');
    $routes->get('messages/(:segment)', 'ChatWidget\ChatController::apiGetMessages/$1');
    $routes->post('close-session', 'ChatWidget\ChatController::apiCloseSession');
    $routes->get('agent-workload', 'ChatWidget\ChatController::apiGetAgentWorkload');
});

// Public APIs
$routes->group('api/v1/public/chat', function ($routes) {
    $routes->post('start', 'ChatWidget\ChatController::publicStartSession');
    $routes->post('send-message', 'ChatWidget\ChatController::publicSendMessage');
    $routes->get('messages/(:segment)', 'ChatWidget\ChatController::publicGetMessages/$1');
    $routes->post('close', 'ChatWidget\ChatController::publicCloseSession');
});

// Widget (API Key Auth)
$routes->group('api/v1/widget', function ($routes) {
    $routes->post('/chat/start-session', 'ChatWidget\ChatController::startWidgetSession');
    $routes->get('config', 'ChatWidget\WidgetConfigController::publicConfig');
});

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