<?php
/**
 * WeCoza Core - Upload Service for Classes Module
 *
 * Handles file uploads for class-related operations:
 * - Class attachments (WordPress media library)
 * - QA reports (direct file storage)
 * - QA question attachments (direct file storage)
 *
 * @package WeCoza\Classes\Services
 * @since 1.0.0
 */

namespace WeCoza\Classes\Services;

if (!defined('ABSPATH')) {
    exit;
}

class UploadService
{
    private string $qaReportsDir;
    private string $qaReportsUrl;
    private string $qaQuestionsDir;
    private string $qaQuestionsUrl;
    private string $classAttachmentsSubdir = '/wecoza-classes';

    private array $documentTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private array $imageTypes = [
        'image/jpeg',
        'image/png',
    ];

    private int $maxFileSize = 10485760; // 10MB

    public function __construct()
    {
        $uploadDirInfo = wp_upload_dir();
        $this->qaReportsDir = $uploadDirInfo['basedir'] . '/qa-reports/';
        $this->qaReportsUrl = $uploadDirInfo['baseurl'] . '/qa-reports/';
        $this->qaQuestionsDir = $uploadDirInfo['basedir'] . '/qa-questions/';
        $this->qaQuestionsUrl = $uploadDirInfo['baseurl'] . '/qa-questions/';

        $this->ensureUploadDirectories();
    }

    /**
     * Ensure upload directories exist with security files
     */
    private function ensureUploadDirectories(): void
    {
        $directories = [$this->qaReportsDir, $this->qaQuestionsDir];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }

            $indexFile = $dir . 'index.php';
            if (!file_exists($indexFile)) {
                file_put_contents($indexFile, '<?php // Silence is golden');
            }

            $htaccessFile = $dir . '.htaccess';
            if (!file_exists($htaccessFile)) {
                $htaccess = "Options -Indexes\n";
                $htaccess .= "<FilesMatch '\.(php|php3|php4|php5|phtml|pl|py|cgi)$'>\n";
                $htaccess .= "Order Deny,Allow\n";
                $htaccess .= "Deny from all\n";
                $htaccess .= "</FilesMatch>\n";
                file_put_contents($htaccessFile, $htaccess);
            }
        }
    }

    /**
     * Upload class attachment to WordPress media library
     */
    public function uploadClassAttachment(array $file, string $context = 'general'): array
    {
        $validation = $this->validateFile($file, array_merge($this->documentTypes, $this->imageTypes));
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['error']];
        }

        if (!current_user_can('upload_files')) {
            return ['success' => false, 'message' => 'You do not have permission to upload files'];
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        add_filter('upload_dir', [$this, 'filterClassAttachmentDir']);
        $movefile = wp_handle_upload($file, ['test_form' => false]);
        remove_filter('upload_dir', [$this, 'filterClassAttachmentDir']);

        if (!$movefile || isset($movefile['error'])) {
            return [
                'success' => false,
                'message' => $movefile['error'] ?? 'File upload failed'
            ];
        }

        $filename = $movefile['file'];
        $filetype = wp_check_filetype(basename($filename), null);
        $wp_upload_dir = wp_upload_dir();

        $attachment = [
            'guid' => $wp_upload_dir['url'] . '/' . basename($filename),
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attachId = wp_insert_attachment($attachment, $filename);

        if (is_wp_error($attachId)) {
            return ['success' => false, 'message' => 'Failed to create attachment'];
        }

        $attachData = wp_generate_attachment_metadata($attachId, $filename);
        wp_update_attachment_metadata($attachId, $attachData);
        update_post_meta($attachId, '_wecoza_context', $context);

        return [
            'success' => true,
            'id' => $attachId,
            'url' => wp_get_attachment_url($attachId),
            'title' => get_the_title($attachId),
            'filename' => basename($filename),
            'filesize' => filesize($filename),
            'filetype' => $filetype['type']
        ];
    }

    /**
     * Upload QA report file (PDF only)
     */
    public function uploadQAReport(array $file, array $visitData = []): array
    {
        $validation = $this->validateFile($file, ['application/pdf'], ['pdf']);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['error']];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newFilename = 'qa_report_' . wp_date('Ymd_His') . '_' . uniqid() . '.' . $extension;
        $filePath = $this->qaReportsDir . $newFilename;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }

        return [
            'success' => true,
            'filename' => $newFilename,
            'original_name' => $file['name'],
            'file_path' => 'qa-reports/' . $newFilename,
            'file_url' => $this->qaReportsUrl . $newFilename,
            'file_size' => $file['size'],
            'uploaded_by' => wp_get_current_user()->display_name,
            'upload_date' => current_time('mysql')
        ];
    }

    /**
     * Upload multiple QA report files
     */
    public function uploadQAReports(array $files, array $visitData = []): array
    {
        $uploadedReports = [];

        if (empty($files['name']) || !is_array($files['name'])) {
            return $uploadedReports;
        }

        for ($i = 0; $i < count($files['name']); $i++) {
            if (empty($files['name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];

            $result = $this->uploadQAReport($file, $visitData[$i] ?? []);

            if ($result['success']) {
                unset($result['success']);
                $uploadedReports[] = $result;
            }
        }

        return $uploadedReports;
    }

    /**
     * Upload QA question attachment
     */
    public function uploadQAQuestionAttachment(int $classId, array $file): array
    {
        $allowedTypes = array_merge(
            ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['image/jpeg', 'image/png']
        );
        $validation = $this->validateFile($file, $allowedTypes, null, 5 * 1024 * 1024);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['error']];
        }

        $classDir = $this->qaQuestionsDir . $classId . '/';
        if (!file_exists($classDir)) {
            wp_mkdir_p($classDir);
        }

        $newFilename = 'question_' . uniqid() . '_' . sanitize_file_name($file['name']);
        $filePath = $classDir . $newFilename;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }

        $relativePath = 'qa-questions/' . $classId . '/' . $newFilename;

        return [
            'success' => true,
            'url' => $this->qaQuestionsUrl . $classId . '/' . $newFilename,
            'path' => $relativePath,
            'name' => $newFilename
        ];
    }

    /**
     * Delete a QA report file
     */
    public function deleteQAReportFile(string $filePath): bool
    {
        if (empty($filePath)) {
            return false;
        }

        $uploadDir = wp_upload_dir();
        $fullPath = $uploadDir['basedir'] . '/' . $filePath;

        if (file_exists($fullPath)) {
            return @unlink($fullPath);
        }

        return false;
    }

    /**
     * Delete a WordPress media attachment
     */
    public function deleteClassAttachment(int $attachmentId): bool
    {
        if ($attachmentId <= 0) {
            return false;
        }

        return wp_delete_attachment($attachmentId, true) !== false;
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(
        array $file,
        ?array $allowedMimeTypes = null,
        ?array $allowedExtensions = null,
        ?int $maxSize = null
    ): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }

        $maxSize = $maxSize ?? $this->maxFileSize;
        if ($file['size'] > $maxSize) {
            $maxMB = $maxSize / 1048576;
            return ['valid' => false, 'error' => "File size exceeds maximum of {$maxMB}MB"];
        }

        if ($allowedExtensions !== null) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)];
            }
        }

        if ($allowedMimeTypes !== null) {
            $fileType = wp_check_filetype($file['name']);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($file['type'], $allowedMimeTypes) &&
                !in_array($fileType['type'], $allowedMimeTypes) &&
                !in_array($detectedMime, $allowedMimeTypes)) {
                return ['valid' => false, 'error' => 'Invalid file type'];
            }
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
     * Filter for custom upload directory (class attachments)
     */
    public function filterClassAttachmentDir(array $upload): array
    {
        $upload['subdir'] = $this->classAttachmentsSubdir . $upload['subdir'];
        $upload['path'] = $upload['basedir'] . $upload['subdir'];
        $upload['url'] = $upload['baseurl'] . $upload['subdir'];

        return $upload;
    }

    /**
     * Get QA reports directory path
     */
    public function getQAReportsDir(): string
    {
        return $this->qaReportsDir;
    }

    /**
     * Get QA reports URL
     */
    public function getQAReportsUrl(): string
    {
        return $this->qaReportsUrl;
    }

    /**
     * Get QA questions directory path
     */
    public function getQAQuestionsDir(): string
    {
        return $this->qaQuestionsDir;
    }

    /**
     * Get QA questions URL
     */
    public function getQAQuestionsUrl(): string
    {
        return $this->qaQuestionsUrl;
    }
}
