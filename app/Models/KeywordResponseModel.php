<?php

namespace App\Models;

use CodeIgniter\Model;

class KeywordResponseModel extends Model
{
    protected $table = 'keywords_responses';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['keyword', 'response', 'is_active', 'client_id', 'created_at', 'updated_at'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'keyword' => 'required|min_length[1]|max_length[255]',
        'response' => 'required|min_length[1]'
    ];
    protected $validationMessages = [
        'keyword' => [
            'required' => 'Keyword is required',
            'min_length' => 'Keyword must be at least 1 character long',
            'max_length' => 'Keyword cannot exceed 255 characters'
        ],
        'response' => [
            'required' => 'Response is required',
            'min_length' => 'Response must be at least 1 character long'
        ]
    ];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Get all active keyword responses
     */
    public function getActiveResponses()
    {
        return $this->where('is_active', 1)->findAll();
    }

    /**
     * Check if keyword already exists
     */
    public function keywordExists($keyword, $excludeId = null)
    {
        $builder = $this->where('keyword', $keyword);
        
        if ($excludeId) {
            $builder->where('id !=', $excludeId);
        }
        
        return $builder->first() !== null;
    }

    /**
     * Toggle active status of a keyword response
     */
    public function toggleStatus($id)
    {
        $record = $this->find($id);
        if ($record) {
            return $this->update($id, ['is_active' => $record['is_active'] ? 0 : 1]);
        }
        return false;
    }
    
    /**
     * Get active keyword responses for a specific client
     */
    public function getActiveResponsesByClient($clientId)
    {
        return $this->where('client_id', $clientId)
                   ->where('is_active', 1)
                   ->findAll();
    }
    
    /**
     * Get all keyword responses for a specific client
     */
    public function getResponsesByClient($clientId)
    {
        return $this->where('client_id', $clientId)
                   ->orderBy('created_at', 'DESC')
                   ->findAll();
    }
    
    /**
     * Check if keyword already exists for a specific client
     */
    public function keywordExistsForClient($keyword, $clientId, $excludeId = null)
    {
        $builder = $this->where('keyword', $keyword)
                        ->where('client_id', $clientId);
        
        if ($excludeId) {
            $builder->where('id !=', $excludeId);
        }
        
        return $builder->first() !== null;
    }
}
