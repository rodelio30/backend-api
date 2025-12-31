<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientWidgetTagModel extends Model
{
    protected $table      = 'client_widget_chat_tags';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'client_id',
        'tag',
        'author_type',
        'author_name'
    ];

    /**
     * Get all tags by client
     */
    public function getByClient(int $clientId)
    {
        return $this->where('client_id', $clientId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Check if tag exists
     */
    public function exists(int $clientId, string $tag): bool
    {
        return $this->where([
            'client_id' => $clientId,
            'tag' => $tag
        ])->countAllResults() > 0;
    }
}
