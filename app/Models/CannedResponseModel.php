<?php

namespace App\Models;

use CodeIgniter\Model;

class CannedResponseModel extends Model
{
    protected $table = 'canned_responses';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'title', 'content', 'category', 'response_type', 'api_action_type', 
        'api_parameters', 'api_key', 'created_by_user_type', 
        'created_by_user_id', 'is_active', 'created_at', 'updated_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation
    protected $validationRules = [
        'title' => 'required|min_length[1]|max_length[100]',
        'category' => 'max_length[50]',
        'response_type' => 'in_list[plain_text,api]',
        'api_action_type' => 'max_length[100]',
        'api_key' => 'required|max_length[255]',
        'created_by_user_type' => 'required|in_list[client,agent]',
        'created_by_user_id' => 'required|integer'
    ];
    
    protected $validationMessages = [
        'title' => [
            'required' => 'Title is required',
            'min_length' => 'Title must be at least 1 character long',
            'max_length' => 'Title cannot exceed 100 characters'
        ],
        'response_type' => [
            'in_list' => 'Response type must be either plain_text or api'
        ],
        'api_action_type' => [
            'max_length' => 'API action type cannot exceed 100 characters'
        ],
        'api_key' => [
            'required' => 'API key is required'
        ],
        'created_by_user_type' => [
            'required' => 'User type is required',
            'in_list' => 'User type must be either client or agent'
        ],
        'created_by_user_id' => [
            'required' => 'User ID is required',
            'integer' => 'User ID must be an integer'
        ]
    ];
    
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get all active canned responses
     */
    public function getActiveResponses()
    {
        return $this->where('is_active', 1)->findAll();
    }

    /**
     * Get canned responses for a specific API key and creator
     */
    public function getResponsesForCreator($apiKey, $userType, $userId)
    {
        return $this->where('api_key', $apiKey)
                    ->where('created_by_user_type', $userType)
                    ->where('created_by_user_id', $userId)
                    ->where('is_active', 1)
                    ->orderBy('title', 'ASC')
                    ->findAll();
    }

    /**
     * Get all canned responses for a specific API key (for dropdown usage in chat)
     */
    public function getResponsesForApiKey($apiKey)
    {
        return $this->where('api_key', $apiKey)
                    ->where('is_active', 1)
                    ->orderBy('title', 'ASC')
                    ->findAll();
    }

    /**
     * Check if title already exists for the same creator
     */
    public function titleExistsForCreator($title, $apiKey, $userType, $userId, $excludeId = null)
    {
        $builder = $this->where('title', $title)
                        ->where('api_key', $apiKey)
                        ->where('created_by_user_type', $userType)
                        ->where('created_by_user_id', $userId);
        
        if ($excludeId) {
            $builder->where('id !=', $excludeId);
        }
        
        return $builder->first() !== null;
    }

    /**
     * Toggle active status of a canned response
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
     * Check if user can manage this canned response (creator check)
     */
    public function canUserManage($id, $userType, $userId)
    {
        $response = $this->find($id);
        if (!$response) {
            return false;
        }
        
        return ($response['created_by_user_type'] === $userType && 
                $response['created_by_user_id'] == $userId);
    }

    /**
     * Get canned responses with category grouping for management interface
     */
    public function getResponsesGroupedByCategory($apiKey, $userType, $userId)
    {
        $responses = $this->where('api_key', $apiKey)
                          ->where('created_by_user_type', $userType)
                          ->where('created_by_user_id', $userId)
                          ->orderBy('category', 'ASC')
                          ->orderBy('title', 'ASC')
                          ->findAll();

        $grouped = [];
        foreach ($responses as $response) {
            $category = $response['category'] ?: 'general';
            $grouped[$category][] = $response;
        }
        
        return $grouped;
    }
}
