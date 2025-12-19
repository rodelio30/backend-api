<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientWidgetPhraseModel extends Model
{
    protected $table      = 'client_widget_system_phrases';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'locale',
        'phrase_key',
        'phrase_value',
    ];

    protected $useTimestamps = false;

    /**
     * Get all phrases by locale
     */
    public function getByLocale(string $locale): array
    {
        return $this->where('locale', $locale)
                    ->findAll();
    }
}