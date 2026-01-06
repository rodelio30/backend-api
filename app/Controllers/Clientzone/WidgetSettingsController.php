<?php

namespace App\Controllers\Clientzone;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Controllers\General;

class WidgetSettingsController extends General
{

    protected $format = 'json';

    public function __construct()
    {
        helper('jwt');
    }

    public function getWidgetSettings()
    {

        // Token decoded in filter
        // $tokenObject = $this->request->clientToken;

        // Payload stored inside:  data → your payload


        // Validate token data exists
        $tokenObject = $this->request->clientToken ?? null;

        // log_message('debug', json_encode($tokenObject));
        // log_message('debug', 'JWT Filter Decoded Token: ' . json_encode($tokenObject));
        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Token data missing (filter did not pass data)'
            ]);
        }

        $payload = (array) $tokenObject->data;

        $clientId = $payload['id'] ?? null;
        $currentUser = $this->getCurrentClientUser();

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid or missing client ID in token.'
            ]);
        }

        // Fetch settings
        // $settings = $this->clientWidgetSettingModel->getByClientId($clientId);
        $settings = $this->mergeWithDefaults($this->clientWidgetSettingModel->getByClientId($clientId));
        // $settings = array_merge(
        //     $this->getDefaultWidgetSettings(),
        //     $this->clientWidgetSettingModel->getByClientId($clientId)
        // );
        $apiKey = $this->apiKeyModel->where('client_id', $clientId)->first();

        $data = [
            'title' => 'Chat Widget Installation',
            'bodyClass' => 'client-settings-page',
            'user' => $currentUser,
            'client_id' => $clientId,
            'apiKey' => $apiKey,
            'settings' => $settings,
            'widgetEmbedCode' => $this->buildEmbedSnippet($apiKey['api_key'] ?? null, $settings),
            'widgetScriptUrl' => rtrim($this->getWidgetFrontendBaseUrl(), '/') . '/assets/js/widget.js',
        ];

        return $this->respond([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    // public function chatWidget()
    // {
    //     $tokenObject = $this->request->clientToken ?? null;

    //     if (!$tokenObject) {
    //         return $this->response->setStatusCode(401)->setJSON([
    //             'status' => 'error',
    //             'message' => 'Token data missing (filter did not pass data)'
    //         ]);
    //     }

    //     $payload = (array) $tokenObject->data;

    //     $clientId = $payload['id'] ?? null;
    //     $currentUser = $this->getCurrentClientUser();

    //     if (!$clientId) {
    //         return $this->response->setStatusCode(401)->setJSON([
    //             'status' => 'error',
    //             'message' => 'Invalid or missing client ID in token.'
    //         ]);
    //     }

    //     // Fetch settings
    //     $settings = $this->mergeWithDefaults($this->clientWidgetSettingModel->getByClientId($clientId));
    //     $apiKey = $this->apiKeyModel->where('client_id', $clientId)->first();

    //     // my code above 

    //     $data = [
    //         'title' => 'Chat Widget Installation',
    //         'user' => $currentUser,
    //         'bodyClass' => 'client-settings-page',
    //         'sidebarContext' => $this->buildSettingsSidebarContext('channels.chat_widget'),
    //         'apiKey' => $apiKey,
    //         'directChatLink' => $this->resolveDirectChatLink($settings, $apiKey),
    //         'widgetEmbedCode' => $this->buildEmbedSnippet($apiKey['api_key'] ?? null, $settings),
    //         'widgetScriptUrl' => rtrim($this->getWidgetFrontendBaseUrl(), '/') . '/assets/js/widget.js',
    //     ];

    //     // return view('client/widget/install', $data);
    //     return $this->respond([
    //         'status' => 'success',
    //         'data' => $data,
    //         'client_id' => $clientId,
    //         'settings' => $settings,
    //     ]);
    // }

    public function customization()
    {
        // $redirect = $this->ensureClientAccountOwner('Widget customization is only available to account owners.');
        // if ($redirect instanceof RedirectResponse) {
        //     return $redirect;
        // }
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Token data missing (filter did not pass data)'
            ]);
        }

        $payload = (array) $tokenObject->data;

        $clientId = $payload['id'] ?? null;
        $currentUser = $this->getCurrentClientUser();

        $settings = $this->mergeWithDefaults($this->clientWidgetSettingModel->getByClientId($clientId));
        $apiKey = $this->apiKeyModel->where('client_id', $clientId)->first();

        $data = [
            'title' => 'Chat Widget Customization',
            'user' => $currentUser,
            'bodyClass' => 'client-settings-page',
            'widgetSettings' => $settings,
            'apiKey' => $apiKey,
            'client_id' => $clientId,
            'directChatLink' => $this->resolveDirectChatLink($settings, $apiKey),
            'widgetEmbedCode' => $this->buildEmbedSnippet($apiKey['api_key'] ?? null, $settings),
            // 'sidebarContext' => $this->buildSettingsSidebarContext('website_widget.customization'),
            'widgetScriptUrl' => rtrim($this->getWidgetFrontendBaseUrl(), '/') . '/assets/js/widget.js',
            'apiEndpoints' => [
                'fetch' => getDomainSpecificUrl('client/settings/widget/fetch', 'client'),
                'update' => getDomainSpecificUrl('client/settings/widget/update', 'client'),
                'publicConfig' => getDomainSpecificUrl('api/widget/config', 'client'),
            ],
        ];

        // return view('client/widget/customization', $data);

        return $this->respond([
            'status' => 'success',
            // 'data' => $data,
            'settings' => $settings,
        ]);
    }

    public function chatPage()
    {
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Token data missing (filter did not pass data)'
            ]);
        }

        $payload = (array) $tokenObject->data;

        $clientId = $payload['id'] ?? null;
        $currentUser = $this->getCurrentClientUser();

        if (!$clientId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid or missing client ID in token.'
            ]);
        }

        // Fetch settings
        $settings = $this->mergeWithDefaults($this->clientWidgetSettingModel->getByClientId($clientId));
        $apiKey = $this->apiKeyModel->where('client_id', $clientId)->first();

        // my code above 

        $data = [
            'title' => 'Chat Page Link',
            'bodyClass' => 'client-settings-page',
            'user' => $currentUser,
            'client_id' => $clientId,
            // 'sidebarContext' => $this->buildSettingsSidebarContext('channels.chat_page'),
            'directChatLink' => $this->resolveDirectChatLink($settings, $apiKey),
            'apiKey' => $apiKey,
        ];

        // return view('client/widget/chat-page', $data);
        return $this->respond([
            'status' => 'success',
            'data' => $data,
            // 'settings' => $settings,
        ]);
    }

    // public function language()
    // {
    //     $redirect = $this->ensureClientAccountOwner('Only account owners can access widget settings.');
    //     if ($redirect instanceof RedirectResponse) {
    //         return $redirect;
    //     }

    //     $data = [
    //         'title' => 'Widget Language Settings',
    //         'user' => $this->getCurrentClientUser(),
    //         'bodyClass' => 'client-settings-page',
    //         'sidebarContext' => $this->buildSettingsSidebarContext('website_widget.language'),
    //     ];

    //     return view('client/widget/placeholder', $data + [
    //         'placeholder' => [
    //             'headline' => 'Language configuration is coming soon',
    //             'description' => 'We are preparing a flexible language experience so you can adjust greetings, system messages, and localization for your teams.',
    //         ],
    //     ]);
    // }

    // public function availability()
    // {
    //     $redirect = $this->ensureClientAccountOwner('Only account owners can access widget settings.');
    //     if ($redirect instanceof RedirectResponse) {
    //         return $redirect;
    //     }

    //     $data = [
    //         'title' => 'Widget Availability Settings',
    //         'user' => $this->getCurrentClientUser(),
    //         'bodyClass' => 'client-settings-page',
    //         'sidebarContext' => $this->buildSettingsSidebarContext('website_widget.availability'),
    //     ];

    //     return view('client/widget/placeholder', $data + [
    //         'placeholder' => [
    //             'headline' => 'Availability scheduling is on the roadmap',
    //             'description' => 'Soon you will be able to define business hours, custom auto-replies, and escalation paths when your agents are offline.',
    //         ],
    //     ]);
    // }

    // public function welcomeScreen()
    // {
    //     $redirect = $this->ensureClientAccountOwner('Only account owners can access widget settings.');
    //     if ($redirect instanceof RedirectResponse) {
    //         return $redirect;
    //     }

    //     $data = [
    //         'title' => 'Widget Welcome Screen',
    //         'user' => $this->getCurrentClientUser(),
    //         'bodyClass' => 'client-settings-page',
    //         'sidebarContext' => $this->buildSettingsSidebarContext('website_widget.welcome_screen'),
    //     ];

    //     return view('client/widget/placeholder', $data + [
    //         'placeholder' => [
    //             'headline' => 'Welcome screen customization is nearly here',
    //             'description' => 'Configure multi-step welcome flows, capture visitor intent, and pre-qualify leads directly from your chat widget.',
    //         ],
    //     ]);
    // }

    public function fetchSettings(): ResponseInterface
    {
        // $redirect = $this->ensureClientAccountOwner('Authentication required to load widget settings.');
        // if ($redirect instanceof RedirectResponse) {
        //     return $this->jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        // }

        // $clientId = $this->getClientId();
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Token data missing (filter did not pass data)'
            ]);
        }

        $payload = (array) $tokenObject->data;

        $clientId = $payload['id'] ?? null;
        // $currentUser = $this->getCurrentClientUser();
        $settings = $this->mergeWithDefaults($this->clientWidgetSettingModel->getByClientId($clientId));

        $apiKey = $this->apiKeyModel->where('client_id', $clientId)->first();

        return $this->jsonResponse([
            'status' => 'success',
            'data' => [
                'settings' => $settings,
                'api_key' => $apiKey ? $apiKey['api_key'] : null,
                'embed_code' => $this->buildEmbedSnippet($apiKey['api_key'] ?? null, $settings),
            ],
        ]);
    }

    public function updateSettings(): ResponseInterface
    {
        // $redirect = $this->ensureClientAccountOwner('Authentication required to update widget settings.');
        // if ($redirect instanceof RedirectResponse) {
        //     return $this->jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        // }
        $tokenObject = $this->request->clientToken ?? null;

        if (!$tokenObject) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Token data missing (filter did not pass data)'
            ]);
        }

        // $payload = (array) $tokenObject->data;

        // $clientId = $payload['id'] ?? null;


        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (!is_array($payload)) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Invalid request payload.',
            ], 422);
        }

        // $rules = [
        //     'widget_name' => 'required|string|min_length[3]|max_length[100]',
        //     'widget_status' => 'required|in_list[active,inactive]',
        //     'widget_color' => 'required|regex_match[/^#[0-9a-fA-F]{6}$/]',
        //     'theme' => 'required|string|max_length[50]',
        //     'position' => 'required|string|max_length[30]',
        //     'welcome_message' => 'permit_empty|string|max_length[255]',
        //     'welcome_delay_ms' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[60000]',
        //     'welcome_auto_hide' => 'required|in_list[0,1]',
        //     'welcome_auto_hide_delay_ms' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[60000]',
        //     'avatar_type' => 'required|in_list[emoji,image,none]',
        //     'avatar_value' => 'permit_empty|string|max_length[255]',
        // ];

        $rules = [
                'widget_name' => 'permit_empty|string|min_length[3]|max_length[100]',
                'widget_status' => 'permit_empty|in_list[active,inactive]',
                'widget_color' => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
                'theme' => 'permit_empty|string|max_length[50]',
                'position' => 'permit_empty|string|max_length[30]',
                'welcome_message' => 'permit_empty|string|max_length[255]',
                'welcome_delay_ms' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[60000]',
                'welcome_auto_hide' => 'permit_empty',
                'welcome_auto_hide_delay_ms' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[60000]',
                'avatar_type' => 'permit_empty|in_list[emoji,image,none]',
                'avatar_value' => 'permit_empty|string|max_length[255]',
            ];

        if (!$this->validateData($payload, $rules)) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $clientId = $this->getTokenClientId();

        $behaviorDefaults = [
            'disable_sound_notification' => false,
            'disable_agent_typing_notification' => false,
            'hide_widget_on_mobile' => false,
            'maximize_on_click' => false,
        ];
        $behaviorPayload = $payload['behavior_options'] ?? [];
        if (!is_array($behaviorPayload)) {
            $behaviorPayload = [];
        }
        $normalizedBehavior = array_merge(
            $behaviorDefaults,
            array_intersect_key($behaviorPayload, $behaviorDefaults)
        );

        $normalized = [
            'widget_name' => $payload['widget_name'],
            'widget_status' => $payload['widget_status'],
            'widget_color' => $payload['widget_color'],
            'theme' => $payload['theme'],
            'position' => $payload['position'],
            'welcome_message' => $payload['welcome_message'] ?? null,
            'welcome_delay_ms' => (int) $payload['welcome_delay_ms'],
            'welcome_auto_hide' => (int) $payload['welcome_auto_hide'] === 1,
            'welcome_auto_hide_delay_ms' => (int) $payload['welcome_auto_hide_delay_ms'],
            'avatar_type' => $payload['avatar_type'],
            'avatar_value' => $payload['avatar_value'] ?? null,
            'display_options' => $payload['display_options'] ?? [],
            'appearance_options' => $payload['appearance_options'] ?? [],
            'behavior_options' => $normalizedBehavior,
        ];

        $updated = $this->clientWidgetSettingModel->upsertForClient($clientId, $normalized);
        $apiKey = $this->apiKeyModel->where('client_id', $clientId)->first();

        return $this->jsonResponse([
            'status' => 'success',
            'data' => [
                'settings' => $updated,
                'embed_code' => $this->buildEmbedSnippet($apiKey['api_key'] ?? null, $updated),
            ],
        ]);
    }

    public function publicConfig(): ResponseInterface
    {
        $data = $this->request->getJSON(true);
    
        $apiKeyValue = $data['api_key'] ?? null;

        // $apiKeyValue = $this->request->getGet('api_key');

        if (empty($apiKeyValue)) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'API key is required.',
            ], 422);
        }

        $apiKey = $this->apiKeyModel
            ->where('api_key', $apiKeyValue)
            ->where('status', 'active')
            ->first();

        if (!$apiKey) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Invalid API key.',
            ], 404);
        }

        $settings = $this->mergeWithDefaults(
            $this->clientWidgetSettingModel->getByClientId((int) $apiKey['client_id'])
        );

        return $this->jsonResponse([
            'status' => 'success',
            'data' => [
                'widget' => [
                    'name' => $settings['widget_name'],
                    'status' => $settings['widget_status'],
                    'color' => $settings['widget_color'],
                    'theme' => $settings['theme'],
                ],
                'position' => $settings['position'],
                'welcomeBubble' => [
                    'enabled' => !empty($settings['welcome_message']),
                    'message' => $settings['welcome_message'],
                    'delay' => $settings['welcome_delay_ms'],
                    'autoHide' => (bool) $settings['welcome_auto_hide'],
                    'autoHideDelay' => $settings['welcome_auto_hide_delay_ms'],
                    'avatar' => $this->resolveWelcomeAvatar($settings),
                    'avatarType' => $settings['avatar_type'],
                    'avatarValue' => $settings['avatar_value'],
                ],
                'behavior' => $settings['behavior_options'],
                'appearance' => $settings['appearance_options'],
                'display' => $settings['display_options'],
            ],
        ]);
    }

    protected function mergeWithDefaults(array $settings): array
    {
        $behaviorDefaults = [
            'disable_sound_notification' => false,
            'disable_agent_typing_notification' => false,
            'hide_widget_on_mobile' => false,
            'maximize_on_click' => false,
        ];

        $defaults = [
            'widget_name' => 'Live Chat',
            'widget_status' => 'active',
            'widget_color' => '#03a84e',
            'theme' => 'blue',
            'position' => 'bottom-right',
            'welcome_message' => 'Hi! I\'m here to help. Ask me anything!',
            'welcome_delay_ms' => 3000,
            'welcome_auto_hide' => true,
            'welcome_auto_hide_delay_ms' => 10000,
            'avatar_type' => 'emoji',
            'avatar_value' => '👋',
            'display_options' => [],
            'appearance_options' => [],
            'behavior_options' => $behaviorDefaults,
        ];

        // ✅ array_replace preserves false, 0, and empty strings
        $merged = array_replace($defaults, $settings);

        // Normalize behavior_options safely
        $merged['behavior_options'] = array_replace(
            $behaviorDefaults,
            is_array($merged['behavior_options'] ?? null) ? $merged['behavior_options'] : []
        );
        // $merged = array_merge($defaults, array_filter($settings, function ($value) {
        //     return $value !== null;
        // }));

        // if (!isset($merged['behavior_options']) || !is_array($merged['behavior_options'])) {
        //     $merged['behavior_options'] = $behaviorDefaults;
        // } else {
        //     $merged['behavior_options'] = array_merge($behaviorDefaults, $merged['behavior_options']);
        // }

        return $merged;
    }

    // protected function buildSettingsSidebarContext(string $activeKey): array
    // {
    //     return [
    //         'active' => $activeKey,
    //         'groups' => [
    //             [
    //                 'label' => 'Channels',
    //                 'items' => [
    //                     [
    //                         'key' => 'channels.chat_widget',
    //                         'label' => 'Chat Widget',
    //                         'icon' => 'bi-chat-left-text',
    //                         'url' => getDomainSpecificUrl('client/settings/widget/chat-widget', 'client'),
    //                     ],
    //                     [
    //                         'key' => 'channels.chat_page',
    //                         'label' => 'Chat page',
    //                         'icon' => 'bi-box-arrow-up-right',
    //                         'url' => getDomainSpecificUrl('client/settings/widget/chat-page', 'client'),
    //                     ],
    //                 ],
    //             ],
    //             [
    //                 'label' => 'Website widget',
    //                 'items' => [
    //                     [
    //                         'key' => 'website_widget.customization',
    //                         'label' => 'Customization',
    //                         'icon' => 'bi-sliders',
    //                         'url' => getDomainSpecificUrl('client/settings/widget/customization', 'client'),
    //                     ],
    //                     [
    //                         'key' => 'website_widget.language',
    //                         'label' => 'Language',
    //                         'icon' => 'bi-translate',
    //                         'url' => getDomainSpecificUrl('client/settings/widget/language', 'client'),
    //                     ],
    //                     [
    //                         'key' => 'website_widget.availability',
    //                         'label' => 'Availability',
    //                         'icon' => 'bi-calendar2-check',
    //                         'url' => getDomainSpecificUrl('client/settings/widget/availability', 'client'),
    //                     ],
    //                     [
    //                         'key' => 'website_widget.welcome_screen',
    //                         'label' => 'Welcome screen',
    //                         'icon' => 'bi-easel3',
    //                         'url' => getDomainSpecificUrl('client/settings/widget/welcome-screen', 'client'),
    //                     ],
    //                 ],
    //             ],
    //             [
    //                 'label' => 'Engagement',
    //                 'items' => [
    //                     [
    //                         'key' => 'engagement.forms',
    //                         'label' => 'Forms',
    //                         'icon' => 'bi-ui-checks-grid',
    //                         'url' => '#',
    //                         'disabled' => true,
    //                     ],
    //                     [
    //                         'key' => 'engagement.tags',
    //                         'label' => 'Tags',
    //                         'icon' => 'bi-tags',
    //                         'url' => '#',
    //                         'disabled' => true,
    //                     ],
    //                 ],
    //             ],
    //         ],
    //     ];
    // }

    protected function buildEmbedSnippet(?string $apiKey, array $settings): string
    {
        // $baseUrl = $this->getWidgetFrontendBaseUrl();
        $baseUrl = $this->getWidgetBaseUrl();
        $config = [
            'baseUrl' => $baseUrl,
            'apiKey' => $apiKey ?: 'YOUR_API_KEY',
            'theme' => $settings['theme'],
            'position' => $settings['position'],
            'widgetColor' => $settings['widget_color'],
            'branding' => [
                'title' => $settings['widget_name'],
            ],
            'welcomeBubble' => [
                'enabled' => !empty($settings['welcome_message']),
                'message' => $settings['welcome_message'],
                'delay' => $settings['welcome_delay_ms'],
                'autoHide' => (bool) $settings['welcome_auto_hide'],
                'autoHideDelay' => $settings['welcome_auto_hide_delay_ms'],
                'avatar' => $this->resolveWelcomeAvatar($settings),
                'avatarType' => $settings['avatar_type'],
                'avatarValue' => $settings['avatar_value'],
            ],
            'widgetConfig' => [
                'status' => $settings['widget_status'],
                'color' => $settings['widget_color'],
                'theme' => $settings['theme'],
            ],
            'behaviorOptions' => $settings['behavior_options'],
        ];

        if (!empty($settings['appearance_options'])) {
            $config['appearanceOptions'] = $settings['appearance_options'];
        }

        if (!empty($settings['display_options'])) {
            $config['displayOptions'] = $settings['display_options'];
        }

        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return implode(PHP_EOL, [
            '<!-- Live Chat Widget -->',
            '<script>',
            'window.LiveChatConfig = ' . $configJson . ';',
            '</script>',
            sprintf(
                '<script src="%s/assets/js/widget.js" async></script>',
                $baseUrl
            ),
        ]);
    }

    protected function resolveDirectChatLink(array $settings, ?array $apiKey): ?string
    {
        $directChatLink = $settings['direct_chat_link'] ?? null;

        if (!$directChatLink && $apiKey && !empty($apiKey['api_key'])) {
            $directChatLink = sprintf(
                // '%s/?api_key=%s',
                '%s/?api_key=%s',
                $this->getWidgetFrontendBaseUrl(),
                urlencode($apiKey['api_key'])
            );
        }

        return $directChatLink;
    }

    protected function resolveWelcomeAvatar(array $settings): ?string
    {
        if (($settings['avatar_type'] ?? 'emoji') === 'none') {
            return null;
        }

        return $settings['avatar_value'] ?? null;
    }

}

