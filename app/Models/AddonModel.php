<?php

namespace App\Models;

use CodeIgniter\Model;

class AddonModel extends Model
{
    protected $table = 'addons';
    protected $primaryKey = 'id';

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'slug',
        'name',
        'category',
        'thumbnail_icon',
        'hero_icon',
        'headline',
        'summary',
        'overview_bullets',
        'benefits',
        'price',
        'currency_code',
        'billing_type',
        'billing_interval',
        'billing_interval_count',
        'duration_days',
        'cta_label',
        'price_label',
        'disclaimer',
        'is_active',
        'display_order',
    ];

    protected $validationRules = [
        'slug' => 'required|min_length[3]|max_length[120]',
        'name' => 'required|min_length[3]|max_length[150]',
        'price' => 'required|decimal',
        'currency_code' => 'required|max_length[10]',
        'billing_type' => 'required|in_list[one_time,subscription]',
        'billing_interval' => 'permit_empty|in_list[day,week,month,year]',
        'billing_interval_count' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[36]',
        'duration_days' => 'permit_empty|integer|greater_than_equal_to[1]',
        'cta_label' => 'permit_empty|max_length[120]',
        'price_label' => 'permit_empty|max_length[120]',
        'display_order' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[9999]',
    ];

    protected $validationMessages = [];
    protected $skipValidation = false;
}

