<?php

namespace App\Controllers;

use Exception;

class GeneralCW extends BaseController 
{
    /**
     * Generate unique session ID
     */
    public function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Sanitize input
     */
    public function sanitizeInput($input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Format timestamp
     */
    public function formatTimestamp($timestamp): string
    {
        return date('M d, Y h:i A', strtotime($timestamp));
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->session->has('user_id');
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser()
    {
        if ($this->isAuthenticated()) {
            return $this->userCWModel->find($this->session->get('user_id'));
        }
        return null;
    }
    
    /**
     * Send JSON response
     */
    public function jsonResponse($data, $status = 200)
    {
        return $this->response->setJSON($data)->setStatusCode($status);
    }
    
    /**
     * Check if current user is admin
     */
    public function isAdmin()
    {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === 'admin';
    }
}
