<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientWidgetWelcomeModel extends Model
{
    protected $table      = 'client_widget_welcome_settings';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'client_id',
        'show_logo',
        'logo_url',
        'welcome_text',
    ];

    /**
     * Get settings by client ID
     */
    public function getByClientId(int $clientId)
    {
        return $this->where('client_id', $clientId)->first();
    }

    /**
     * Insert or Update (UPSERT)
     */
    public function saveByClient(array $data)
    {
        $existing = $this->where('client_id', $data['client_id'])->first();

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->insert($data);
    }
}
