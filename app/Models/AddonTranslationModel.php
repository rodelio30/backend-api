<?php

namespace App\Models;

use CodeIgniter\Model;

class AddonTranslationModel extends Model
{
    protected $table = 'addon_translations';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'addon_id',
        'locale',
        'name',
        'headline',
        'summary',
        'cta_label',
        'price_label',
        'disclaimer',
    ];

    protected $returnType = 'array';
}

