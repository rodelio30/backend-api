<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientWidgetLanguageModel extends Model
{
    protected $table      = 'client_widget_languages';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'locale',
        'language_name',
        'is_default',
        'status'
    ];

    public function languageExists(string $locale): bool
    {
        return $this->where([
            'locale' => $locale,
            'status' => 'active'
        ])->countAllResults() > 0;
    }

    public function getDefaultLocale(): string
    {
        $row = $this->where('is_default', 1)->first();
        return $row['locale'] ?? 'en';
    }

    public function getActiveLanguages(): array
    {
        return $this->where('status', 'active')->findAll();
    }
}
