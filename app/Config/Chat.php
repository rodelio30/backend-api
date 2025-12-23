<?php

// app/Config/Chat.php - Chat system configuration
namespace Config;

use CodeIgniter\Config\BaseConfig;

class Chat extends BaseConfig
{
    // Session settings for anonymous users
    public int $anonymousSessionTimeout = 1800; // 30 minutes (waiting sessions)
    public int $anonymousInactiveSessionTimeout = 3600; // 1 hour (active sessions)
    public bool $autoCloseInactiveSessions = true; // Role-based cleanup enabled
    
    // Session settings for logged users
    public int $loggedUserSessionTimeout = 0; // Never timeout (0 = disabled)
    public int $loggedUserResumableWindow = 86400; // 24 hours for resumable sessions
    public bool $loggedUserSessionsNeverExpire = true; // Logged users never lose sessions
    
    // Agent settings
    public int $defaultMaxConcurrentChats = 5;
    public array $agentStatuses = ['available', 'busy', 'away'];

    
    // Business hours (24-hour format)
    public string $businessHoursStart = '09:00';
    public string $businessHoursEnd = '17:00';
    public array $businessDays = [1, 2, 3, 4, 5]; // Monday to Friday
    public string $timezone = 'Asia/Kuala_Lumpur';
    
    // WebSocket settings  
    // public string $websocketHost = 'ws.kopisugar.cc';
    // public string $websocketHostFallback = '103.205.208.104:39147';
    // public int $websocketPort = 39147;
    // public int $heartbeatInterval = 30; // seconds
    // public int $reconnectAttempts = 5;
    // public string $websocketHost = 'api-domain.danhar.cc';
    // public string $websocketHost = 'localhost:8000';
    public string $websocketHost = '10.10.197.2';
    public string $websocketHostFallback = '185.135.72.72:8443';
    public int $websocketPort = 8443;
    public int $heartbeatInterval = 30; // seconds
    public int $reconnectAttempts = 5;
}
