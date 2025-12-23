<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientWidgetAvailabilityModel extends Model
{
    protected $table = 'client_widget_availability';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'client_id',
        'allow_chat',
        'enable_queue',
        'max_queue_size',
    ];

    protected $useTimestamps = true;

    public function getByClientId(int $clientId)
    {
        return $this->where('client_id', $clientId)->first();
    }
}
