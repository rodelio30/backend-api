<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientWidgetEyeCatcherModel extends Model
{
    protected $table = 'client_widget_eye_catchers';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'client_id',
        'image_url',
        'sort_order',
        'interval_seconds',
        'is_active',
    ];

    protected $useTimestamps = true;

    /**
     * Get active eye-catchers for widget
     */
    public function getActiveByClient(int $clientId)
    {
        return $this->where('client_id', $clientId)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll();
    }
}
