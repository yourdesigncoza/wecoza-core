<?php
declare(strict_types=1);

/**
 * WeCoza Core - Portfolio Upload Service
 *
 * Handles portfolio file uploads for LP progression completion.
 * Files are stored in wp-content/uploads/portfolios/
 *
 * @package WeCoza\Learners\Services
 * @since 1.0.0
 */

namespace WeCoza\Learners\Services;

use WeCoza\Learners\Repositories\LearnerProgressionRepository;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class PortfolioUploadService
{
    private string $uploadDir;
    private string $uploadUrl;
    private array $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    private array $allowedExtensions = ['pdf', 'doc', 'docx'];
    private int $maxFileSize = 10485760; // 10MB

    public function __construct()
    {
        $uploadDirInfo = wp_upload_dir();
        $this->uploadDir = $uploadDirInfo['basedir'] . '/portfolios/';
        $this->uploadUrl = $uploadDirInfo['baseurl'] . '/portfolios/';

        $this->ensureUploadDirectory();
    }

    /**
     * Ensure upload directory exists
     */
    private function ensureUploadDirectory(): void
    {
        if (!file_exists($this->uploadDir)) {
            wp_mkdir_p($this->uploadDir);
        }

        // Add index.php for security
        $indexFile = $this->uploadDir . 'index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, '<?php // Silence is golden');
        }

        // Add .htaccess for additional security
        $htaccessFile = $this->uploadDir . '.htaccess';
        if (!file_exists($htaccessFile)) {
            $htaccess = "Options -Indexes\n";
            $htaccess .= "<FilesMatch '\.(php|php3|php4|php5|phtml|pl|py|cgi)$'>\n";
            $htaccess .= "Order Deny,Allow\n";
            $htaccess .= "Deny from all\n";
            $htaccess .= "</FilesMatch>\n";
            file_put_contents($htaccessFile, $htaccess);
        }
    }

    /**
     * Upload a portfolio file for LP progression
     */
    public function uploadProgressionPortfolio(int $trackingId, array $file, ?int $uploadedBy = null): array
    {
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['error']];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newFilename = sprintf('progression_%d_%s.%s', $trackingId, uniqid(), $extension);
        $filePath = $this->uploadDir . $newFilename;
        $relativePath = 'portfolios/' . $newFilename;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }

        $repository = new LearnerProgressionRepository();

        $fileId = $repository->savePortfolioFile($trackingId, [
            'file_name' => $file['name'],
            'file_path' => $relativePath,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
            'uploaded_by' => $uploadedBy,
        ]);

        if (!$fileId) {
            @unlink($filePath);
            return ['success' => false, 'message' => 'Failed to save file record'];
        }

        return [
            'success' => true,
            'file_id' => $fileId,
            'file_path' => $relativePath,
            'file_url' => $this->uploadUrl . $newFilename,
            'file_name' => $file['name'],
        ];
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }

        if ($file['size'] > $this->maxFileSize) {
            $maxMB = $this->maxFileSize / 1048576;
            return ['valid' => false, 'error' => "File size exceeds maximum of {$maxMB}MB"];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $this->allowedExtensions)];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type detected'];
        }

        return ['valid' => true];
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum size',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
        ];

        return $messages[$errorCode] ?? 'Unknown upload error';
    }

    /**
     * Get portfolio files for a tracking ID
     */
    public function getPortfolioFiles(int $trackingId): array
    {
        $repository = new LearnerProgressionRepository();
        $files = $repository->getPortfolioFiles($trackingId);

        foreach ($files as &$file) {
            $file['file_url'] = $this->uploadUrl . basename($file['file_path']);
        }

        return $files;
    }

    /**
     * Delete portfolio file
     */
    public function deletePortfolioFile(int $fileId): bool
    {
        $db = wecoza_db();

        $sql = "SELECT file_path FROM learner_progression_portfolios WHERE file_id = :file_id";
        try {
            $stmt = $db->query($sql, ['file_id' => $fileId]);
            $file = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$file) {
                return false;
            }

            $deleteSql = "DELETE FROM learner_progression_portfolios WHERE file_id = :file_id";
            $db->query($deleteSql, ['file_id' => $fileId]);

            $uploadDir = wp_upload_dir();
            $fullPath = $uploadDir['basedir'] . '/' . $file['file_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            return true;
        } catch (Exception $e) {
            error_log("WeCoza Core: Error deleting portfolio file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get upload directory path
     */
    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }

    /**
     * Get upload URL
     */
    public function getUploadUrl(): string
    {
        return $this->uploadUrl;
    }
}
