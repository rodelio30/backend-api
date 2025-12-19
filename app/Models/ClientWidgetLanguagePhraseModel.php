<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientWidgetLanguagePhraseModel extends Model
{
    protected $table      = 'client_widget_language_phrases';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'client_id',
        'locale',
        'phrase_key',
        'phrase_value',
    ];

    protected $useTimestamps = true;
    protected $updatedField = 'updated_at';

    public function getClientPhrases(int $clientId, string $locale): array
    {
        return $this->where([
            'client_id' => $clientId,
            'locale'    => $locale
        ])->findAll();
    }

    public function upsertPhrase(
        int $clientId,
        string $locale,
        string $key,
        string $value
    ): void {
        $existing = $this->where([
            'client_id' => $clientId,
            'locale'    => $locale,
            'phrase_key'=> $key
        ])->first();

        if ($existing) {
            $this->update($existing['id'], [
                'phrase_value' => $value
            ]);
        } else {
            $this->insert([
                'client_id'   => $clientId,
                'locale'      => $locale,
                'phrase_key'  => $key,
                'phrase_value'=> $value
            ]);
        }
    }
}
