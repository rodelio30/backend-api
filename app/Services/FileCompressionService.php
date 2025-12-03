<?php

namespace App\Services;

use Exception;

class FileCompressionService
{
    private const MAX_FILE_SIZE = 1048576; // 1MB in bytes
    private const UPLOAD_PATH = 'files/livechat/default/chat/';
    private const THUMB_PATH = 'files/livechat/default/thumbs/';
    
    private function getCentralizedStoragePath($clientId = 'default')
    {
        return '/www/wwwroot/files/livechat/' . $clientId . '/chat/';
    }
    
    private function getCentralizedThumbsPath($clientId = 'default')
    {
        return '/www/wwwroot/files/livechat/' . $clientId . '/thumbs/';
    }
    
    private function getFileUrl($relativePath, $clientId = 'default')
    {
        return 'https://files.kopisugar.cc/livechat/' . $clientId . '/chat/' . $relativePath;
    }
    
    private function getThumbnailUrl($relativePath, $clientId = 'default')
    {
        return 'https://files.kopisugar.cc/livechat/' . $clientId . '/thumbs/' . $relativePath;
    }
    
    private $allowedTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
        'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'],
        'audio' => ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac', 'flac'],
        'document' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
        'other' => ['csv', 'xls', 'xlsx', 'ppt', 'pptx']
    ];
    
    public function __construct()
    {
        // Create upload directories if they don't exist
        $this->createDirectories();
    }
    
    /**
     * Process uploaded file with compression
     */
    public function processFile($uploadedFile, $sessionId)
    {
        try {
            // Validate file
            $validation = $this->validateFile($uploadedFile);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }
            
            // Get file info
            $originalName = $uploadedFile->getName();
            $originalSize = $uploadedFile->getSize();
            $mimeType = $uploadedFile->getMimeType();
            $fileType = $this->detectFileType($originalName, $mimeType);
            
            // Generate unique filename
            $storedName = $this->generateFileName($originalName);
            $datePath = date('Y/m/d');
            $fullPath = $this->getCentralizedStoragePath() . $datePath . '/';
            
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
            
            $filePath = $fullPath . $storedName;
            $relativePath = $datePath . '/' . $storedName;
            
            $uploadedFile->move($fullPath, $storedName);
            
            // Compress file if needed
            $compressionResult = $this->compressFile($filePath, $fileType, $originalSize);
            
            // Generate thumbnail for images
            $thumbnailPath = null;
            if ($fileType === 'image') {
                $thumbnailPath = $this->generateThumbnail($filePath, $storedName, $datePath);
            }
            
            return [
                'success' => true,
                'file_data' => [
                    'original_name' => $originalName,
                    'stored_name' => $storedName,
                    'file_path' => $relativePath,
                    'file_size' => $originalSize,
                    'compressed_size' => filesize($filePath),
                    'mime_type' => $mimeType,
                    'file_type' => $fileType,
                    'compression_status' => $compressionResult['status'],
                    'thumbnail_path' => $thumbnailPath,
                    'compression_ratio' => $compressionResult['ratio']
                ]
            ];
            
        } catch (Exception $e) {
            error_log('File processing error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'File processing failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file)
    {
        if (!$file->isValid()) {
            return ['valid' => false, 'error' => 'File upload failed'];
        }
        
        // Check file size (50MB max before compression)
        if ($file->getSize() > 52428800) { // 50MB
            return ['valid' => false, 'error' => 'File too large. Maximum size is 50MB'];
        }
        
        // Check file extension
        $extension = strtolower($file->getClientExtension());
        $isAllowed = false;
        
        foreach ($this->allowedTypes as $category => $extensions) {
            if (in_array($extension, $extensions)) {
                $isAllowed = true;
                break;
            }
        }
        
        if (!$isAllowed) {
            return ['valid' => false, 'error' => 'File type not allowed'];
        }
        
        // Basic security check - scan for executable extensions in filename
        $dangerousExtensions = ['php', 'exe', 'bat', 'com', 'scr', 'vbs', 'js'];
        if (in_array($extension, $dangerousExtensions)) {
            return ['valid' => false, 'error' => 'File type not allowed for security reasons'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Detect file type category
     */
    private function detectFileType($filename, $mimeType)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        foreach ($this->allowedTypes as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                return $type;
            }
        }
        
        // Fallback to mime type detection
        if (strpos($mimeType, 'image/') === 0) return 'image';
        if (strpos($mimeType, 'video/') === 0) return 'video';
        if (strpos($mimeType, 'audio/') === 0) return 'audio';
        if (strpos($mimeType, 'application/pdf') === 0) return 'document';
        
        return 'other';
    }
    
    /**
     * Compress file to target size
     */
    private function compressFile($filePath, $fileType, $originalSize)
    {
        if ($originalSize <= self::MAX_FILE_SIZE) {
            return ['status' => 'original', 'ratio' => 1.0];
        }
        
        $beforeSize = $originalSize;
        
        try {
            switch ($fileType) {
                case 'image':
                    $result = $this->compressImage($filePath);
                    break;
                    
                case 'video':
                    $result = $this->compressVideo($filePath);
                    break;
                    
                case 'document':
                case 'other':
                    $result = $this->compressGenericFile($filePath);
                    break;
                    
                default:
                    $result = $this->compressGenericFile($filePath);
            }
            
            $afterSize = filesize($filePath);
            $ratio = $afterSize / $beforeSize;
            
            return [
                'status' => $result ? 'compressed' : 'failed',
                'ratio' => $ratio
            ];
            
        } catch (Exception $e) {
            error_log('Compression error: ' . $e->getMessage());
            return ['status' => 'failed', 'ratio' => 1.0];
        }
    }
    
    /**
     * Compress image files
     */
    private function compressImage($filePath)
    {
        if (!extension_loaded('gd')) {
            error_log('GD extension not available for image compression');
            return false;
        }
        
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return false;
        }
        
        $mimeType = $imageInfo['mime'];
        
        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($filePath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($filePath);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = imagecreatefromwebp($filePath);
                } else {
                    return false;
                }
                break;
            default:
                return false;
        }
        
        if (!$image) {
            return false;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Calculate quality and dimensions for target size
        $quality = 85;
        $maxDimension = 1920;
        
        // Resize if too large
        if ($width > $maxDimension || $height > $maxDimension) {
            $ratio = min($maxDimension / $width, $maxDimension / $height);
            $newWidth = intval($width * $ratio);
            $newHeight = intval($height * $ratio);
            
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG and GIF
            if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefill($resizedImage, 0, 0, $transparent);
            }
            
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
        }
        
        // Try different quality levels until we get under 1MB
        $tempFile = $filePath . '.tmp';
        
        while ($quality >= 10) {
            // Save with current quality
            $success = false;
            
            if ($mimeType == 'image/png') {
                // PNG compression (0-9, where 9 is max compression)
                $compression = intval((100 - $quality) / 10);
                $success = imagepng($image, $tempFile, $compression);
            } else {
                // Convert everything else to JPEG for better compression
                $success = imagejpeg($image, $tempFile, $quality);
            }
            
            if ($success && file_exists($tempFile)) {
                $fileSize = filesize($tempFile);
                
                if ($fileSize <= self::MAX_FILE_SIZE) {
                    // Success! Replace original file
                    rename($tempFile, $filePath);
                    imagedestroy($image);
                    return true;
                }
            }
            
            // Reduce quality and try again
            $quality -= 15;
        }
        
        // If still too large, just use the lowest quality
        if (file_exists($tempFile)) {
            rename($tempFile, $filePath);
        }
        
        imagedestroy($image);
        return true;
    }
    
    /**
     * Compress video files (basic approach - mainly validation)
     */
    private function compressVideo($filePath)
    {
        // For videos, we'll primarily rely on the 1MB upload limit
        // Advanced video compression would require FFmpeg
        
        $currentSize = filesize($filePath);
        
        if ($currentSize > self::MAX_FILE_SIZE) {
            // If video is larger than 1MB, we'll reject it or truncate
            // This is a simple approach - in production you might want to use FFmpeg
            error_log("Video file too large: {$currentSize} bytes");
            return false;
        }
        
        return true;
    }
    
    /**
     * Compress generic files (documents, archives, etc.)
     */
    private function compressGenericFile($filePath)
    {
        $currentSize = filesize($filePath);
        
        if ($currentSize <= self::MAX_FILE_SIZE) {
            return true;
        }
        
        // Try ZIP compression for documents
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            $zipPath = $filePath . '.zip';
            
            if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
                $zip->addFile($filePath, basename($filePath));
                $zip->close();
                
                if (file_exists($zipPath) && filesize($zipPath) <= self::MAX_FILE_SIZE) {
                    // Replace original with compressed version
                    unlink($filePath);
                    rename($zipPath, $filePath);
                    return true;
                }
                
                // Clean up if compression didn't help
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                }
            }
        }
        
        // If still too large, truncate or reject
        if ($currentSize > self::MAX_FILE_SIZE * 2) {
            error_log("File too large after compression attempts: {$currentSize} bytes");
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate thumbnail for images
     */
    private function generateThumbnail($filePath, $storedName, $datePath)
    {
        if (!extension_loaded('gd')) {
            return null;
        }
        
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return null;
        }
        
        $mimeType = $imageInfo['mime'];
        
        // Create image resource
        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($filePath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($filePath);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = imagecreatefromwebp($filePath);
                } else {
                    return null;
                }
                break;
            default:
                return null;
        }
        
        if (!$image) {
            return null;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Create thumbnail (150x150 max)
        $thumbSize = 150;
        $ratio = min($thumbSize / $width, $thumbSize / $height);
        $thumbWidth = intval($width * $ratio);
        $thumbHeight = intval($height * $ratio);
        
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Preserve transparency
        if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }
        
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        
        $thumbPath = $this->getCentralizedThumbsPath() . $datePath . '/';
        if (!is_dir($thumbPath)) {
            mkdir($thumbPath, 0755, true);
        }
        
        $thumbName = 'thumb_' . $storedName;
        $thumbFile = $thumbPath . $thumbName;
        $thumbRelativePath = $datePath . '/' . $thumbName;
        
        $success = imagejpeg($thumbnail, $thumbFile, 85);
        
        imagedestroy($image);
        imagedestroy($thumbnail);
        
        return $success ? $thumbRelativePath : null;
    }
    
    /**
     * Generate unique filename
     */
    private function generateFileName($originalName)
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        return "{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Create necessary directories
     */
    private function createDirectories()
    {
        $paths = [
            $this->getCentralizedStoragePath(),
            $this->getCentralizedThumbsPath()
        ];
        
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    /**
     * Get file type icon class for display
     */
    public static function getFileIcon($fileType, $mimeType = '')
    {
        switch ($fileType) {
            case 'image':
                return 'fas fa-image text-primary';
            case 'video':
                return 'fas fa-video text-danger';
            case 'document':
                if (strpos($mimeType, 'pdf') !== false) {
                    return 'fas fa-file-pdf text-danger';
                }
                return 'fas fa-file-alt text-info';
            case 'archive':
                return 'fas fa-file-archive text-warning';
            default:
                return 'fas fa-file text-secondary';
        }
    }
    
    /**
     * Format file size for display
     */
    public static function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}