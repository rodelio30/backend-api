<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientWidgetSettingCWModel extends Model
{
    protected $table = 'client_widget_settings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'client_id',
        'widget_name',
        'widget_status',
        'widget_color',
        'theme',
        'position',
        'welcome_message',
        'welcome_delay_ms',
        'welcome_auto_hide',
        'welcome_auto_hide_delay_ms',
        'avatar_type',
        'avatar_value',
        'direct_chat_link',
        'embed_code',
        'api_key_id',
        'display_options',
        'appearance_options',
        'behavior_options',
    ];

    protected array $casts = [
        'welcome_auto_hide' => 'boolean',
    ];

    public function getByClientId(int $clientId): array
    {
        $record = $this->where('client_id', $clientId)->first();
        return $this->decodeJsonFields($record ?? []);
    }

    protected function decodeJsonFields(array $data): array
    {
        foreach (['display_options', 'appearance_options', 'behavior_options'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $decoded = json_decode($data[$field], true);
                $data[$field] = is_array($decoded) ? $decoded : [];
            } elseif (!isset($data[$field]) || $data[$field] === null) {
                $data[$field] = [];
            }
        }

        return $data;
    }

    public function encodeJsonFields(array $data): array
    {
        $jsonFields = ['display_options', 'appearance_options', 'behavior_options'];

        foreach ($jsonFields as $field) {
            $value = $data[$field] ?? [];

            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($value)) {
                $value = [];
            }

            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false || $encoded === null || $encoded === '' || $encoded === 'null') {
                $encoded = '{}';
            }

            $data[$field] = $encoded;
        }

        return $data;
    }
}

