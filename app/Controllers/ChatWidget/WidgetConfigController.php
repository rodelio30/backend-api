<?php

namespace App\Controllers\ChatWidget;

use App\Models\ApiKeyCWModel;
use App\Models\ClientWidgetSettingCWModel;
use CodeIgniter\HTTP\ResponseInterface;
use App\Controllers\BaseResourceController;

class WidgetConfigController extends BaseResourceController
{
    protected ApiKeyCWModel $apiKeyCWModel;
    protected ClientWidgetSettingCWModel $clientWidgetSettingCWModel;

    public function __construct()
    {
        $this->apiKeyCWModel = new ApiKeyCWModel();
        $this->clientWidgetSettingCWModel = new ClientWidgetSettingCWModel();
    }

    public function publicConfig(): ResponseInterface
    {
        $apiKeyValue = $this->request->getGet('api_key');
        if (empty($apiKeyValue)) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'API key is required.',
            ], 422);
        }

        $apiKey = $this->apiKeyCWModel
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
            $this->clientWidgetSettingCWModel->getByClientId((int) $apiKey['client_id'])
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
                'widgetColor' => $settings['widget_color'],
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

        $merged = array_merge($defaults, array_filter($settings, function ($value) {
            return $value !== null;
        }));

        if (!isset($merged['behavior_options']) || !is_array($merged['behavior_options'])) {
            $merged['behavior_options'] = $behaviorDefaults;
        } else {
            $merged['behavior_options'] = array_merge($behaviorDefaults, $merged['behavior_options']);
        }

        return $merged;
    }

    protected function resolveWelcomeAvatar(array $settings): ?string
    {
        if (($settings['avatar_type'] ?? 'emoji') === 'none') {
            return null;
        }

        return $settings['avatar_value'] ?? null;
    }
}