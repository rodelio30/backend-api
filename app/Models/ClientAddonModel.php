<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientAddonModel extends Model
{
    protected $table = 'client_addons';
    protected $primaryKey = 'id';

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'client_id',
        'addon_id',
        'payment_id',
        'status',
        'starts_at',
        'expires_at',
        'auto_renew',
        'notes',
    ];

    protected $returnType = 'array';

    protected $validationRules = [
        'client_id' => 'required|integer',
        'addon_id' => 'required|integer',
        'status' => 'required|in_list[active,expired,cancelled,pending]',
        'starts_at' => 'required|valid_date[Y-m-d H:i:s]',
        'expires_at' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'auto_renew' => 'in_list[0,1]'
    ];

    protected $skipValidation = false;
}

