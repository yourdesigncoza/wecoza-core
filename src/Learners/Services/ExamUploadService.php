<?php
declare(strict_types=1);

/**
 * WeCoza Core - Exam Upload Service
 *
 * Handles file uploads for SBA scans and final exam certificates.
 * Files are stored in wp-content/uploads/exam-documents/
 *
 * Follows PortfolioUploadService pattern with expanded MIME types
 * to include images (JPG, PNG) for scanned documents.
 *
 * @package WeCoza\Learners\Services
 * @since 1.2.0
 */

namespace WeCoza\Learners\Services;

use WeCoza\Learners\Enums\ExamStep;

if (!defined('ABSPATH')) {
    exit;
}

class ExamUploadService
{
    private string $uploadDir;
    private string $uploadUrl;

    private array $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
    ];

    private array $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    private int $maxFileSize = 10485760; // 10MB

    public function __construct()
    {
        $uploadDirInfo = wp_upload_dir();
        $this->uploadDir = $uploadDirInfo['basedir'] . '/exam-documents/';
        $this->uploadUrl = $uploadDirInfo['baseurl'] . '/exam-documents/';

        $this->ensureUploadDirectory();
    }

    /**
     * Ensure upload directory exists with security files
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

        // Add .htaccess to deny direct access
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
     * Upload an exam document (SBA scan or certificate)
     *
     * @param array    $file       The $_FILES array element for the upload
     * @param int      $trackingId Learner LP tracking ID
     * @param ExamStep $step       Which exam step this file belongs to
     * @return array{success: bool, file_path?: string, file_name?: string, error?: string}
     */
    public function upload(array $file, int $trackingId, ExamStep $step): array
    {
        // Validate the uploaded file
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            error_log("WeCoza Exam: ExamUploadService::upload - Validation failed for tracking_id={$trackingId}, step={$step->value}: {$validation['error']}");
            return [
                'success'   => false,
                'file_path' => '',
                'file_name' => '',
                'error'     => $validation['error'],
            ];
        }

        // Build sanitized filename: {tracking_id}_{exam_step}_{unique}.{ext}
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $sanitizedFilename = sprintf(
            '%d_%s_%s.%s',
            $trackingId,
            $step->value,
            uniqid(),
            $extension
        );

        $fullPath     = $this->uploadDir . $sanitizedFilename;
        $relativePath = 'exam-documents/' . $sanitizedFilename;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            error_log("WeCoza Exam: ExamUploadService::upload - Failed to move file for tracking_id={$trackingId}, step={$step->value}");
            return [
                'success'   => false,
                'file_path' => '',
                'file_name' => '',
                'error'     => 'Failed to write uploaded file to disk',
            ];
        }

        return [
            'success'   => true,
            'file_path' => $relativePath,
            'file_name' => $file['name'],
            'error'     => '',
        ];
    }

    /**
     * Validate uploaded file for type, size, and MIME
     *
     * @param array $file The $_FILES array element
     * @return array{valid: bool, error?: string}
     */
    private function validateFile(array $file): array
    {
        // Check for PHP upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $maxMB = $this->maxFileSize / 1048576;
            return ['valid' => false, 'error' => "File size exceeds maximum of {$maxMB}MB"];
        }

        // Check extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions, true)) {
            return [
                'valid' => false,
                'error' => 'Invalid file type. Allowed: ' . implode(', ', $this->allowedExtensions),
            ];
        }

        // MIME validation using finfo (not just extension)
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedTypes, true)) {
            return ['valid' => false, 'error' => 'Invalid file type detected'];
        }

        return ['valid' => true];
    }

    /**
     * Get human-readable upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server maximum size',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form maximum size',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by extension',
        ];

        return $messages[$errorCode] ?? 'Unknown upload error';
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
