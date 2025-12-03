<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatFileModel extends Model
{
    protected $table = 'chat_files';
    protected $primaryKey = 'id';
    protected $allowedFields = ['message_id', 'session_id', 'original_name', 'file_path', 'file_size', 'mime_type', 'uploaded_at'];
    
    public function getMessageFiles($messageId)
    {
        return $this->where('message_id', $messageId)->findAll();
    }
    
    public function getSessionFiles($sessionId)
    {
        return $this->where('session_id', $sessionId)
                    ->orderBy('uploaded_at', 'DESC')
                    ->findAll();
    }
    
    public function deleteFile($fileId)
    {
        $file = $this->find($fileId);
        if ($file && file_exists(WRITEPATH . $file['file_path'])) {
            unlink(WRITEPATH . $file['file_path']);
        }
        return $this->delete($fileId);
    }
}