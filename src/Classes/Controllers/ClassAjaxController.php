<?php
/**
 * WeCoza Core - Class AJAX Controller
 *
 * Controller for handling all AJAX requests related to class management.
 * Migrated from wecoza-classes-plugin.
 *
 * @package WeCoza\Classes\Controllers
 * @since 1.0.0
 */

namespace WeCoza\Classes\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Classes\Models\ClassModel;
use WeCoza\Classes\Repositories\ClassRepository;
use WeCoza\Classes\Services\FormDataProcessor;
use WeCoza\Classes\Services\ScheduleService;
use WeCoza\Classes\Services\UploadService;

if (!defined('ABSPATH')) {
    exit;
}

class ClassAjaxController extends BaseController
{
    /**
     * Initialize the controller - Register AJAX hooks
     */
    public function initialize(): void
    {
        // Class CRUD operations (authenticated only)
        add_action('wp_ajax_save_class', [__CLASS__, 'saveClassAjax']);
        add_action('wp_ajax_delete_class', [__CLASS__, 'deleteClassAjax']);

        // Calendar events (authenticated only)
        add_action('wp_ajax_get_calendar_events', [__CLASS__, 'getCalendarEventsAjax']);

        // Class subjects lookup (public - required for form dropdowns)
        add_action('wp_ajax_get_class_subjects', [__CLASS__, 'getClassSubjectsAjax']);
        add_action('wp_ajax_nopriv_get_class_subjects', [__CLASS__, 'getClassSubjectsAjax']);

        // Class notes operations (authenticated only)
        add_action('wp_ajax_get_class_notes', [__CLASS__, 'getClassNotes']);
        add_action('wp_ajax_save_class_note', [__CLASS__, 'saveClassNote']);
        add_action('wp_ajax_delete_class_note', [__CLASS__, 'deleteClassNote']);

        // File uploads (authenticated only)
        add_action('wp_ajax_upload_attachment', [__CLASS__, 'uploadAttachment']);
    }

    /**
     * Handle AJAX request to save class data
     */
    public static function saveClassAjax(): void
    {
        ob_start();

        $errorMessages = [];
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$errorMessages) {
            $errorMessages[] = "PHP Warning: $errstr in $errfile on line $errline";
            return true;
        });

        try {
            while (ob_get_level() > 1) {
                ob_end_clean();
            }

            header('Content-Type: application/json; charset=utf-8');

            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wecoza_class_nonce')) {
                ob_clean();
                restore_error_handler();
                wp_send_json_error('Security check failed. Please refresh the page and try again.');
                return;
            }

            $formData = FormDataProcessor::processFormData($_POST, $_FILES);

            $isUpdate = isset($formData['id']) && !empty($formData['id']);
            $classId = $isUpdate ? intval($formData['id']) : null;

            try {
                try {
                    $db = wecoza_db();
                } catch (\Exception $dbError) {
                    ob_clean();
                    restore_error_handler();
                    wp_send_json_error('Database connection failed. Please ensure PostgreSQL credentials are configured.');
                    return;
                }

                if ($isUpdate) {
                    $class = ClassModel::getById($classId);
                    if (!$class) {
                        ob_clean();
                        restore_error_handler();
                        wp_send_json_error('Class not found for update.');
                        return;
                    }

                    $class = FormDataProcessor::populateClassModel($class, $formData);
                    $result = $class->update();
                } else {
                    $class = new ClassModel();
                    $class = FormDataProcessor::populateClassModel($class, $formData);
                    $result = $class->save();
                }

                if ($result) {
                    if (!empty($_POST) || !empty($_FILES)) {
                        QAController::saveQAVisits($class->getId(), $_POST, $_FILES);
                    }

                    $redirect_url = '';
                    $display_page = get_page_by_path('app/display-single-class');
                    if ($display_page) {
                        $redirect_url = add_query_arg(
                            'class_id',
                            $class->getId(),
                            get_permalink($display_page->ID)
                        );
                    }

                    ob_clean();
                    restore_error_handler();

                    wp_send_json_success([
                        'message' => $isUpdate ? 'Class updated successfully.' : 'Draft class created successfully.',
                        'class_id' => $class->getId(),
                        'redirect_url' => $redirect_url
                    ]);
                } else {
                    ob_clean();
                    restore_error_handler();
                    wp_send_json_error(
                        $isUpdate ? 'Failed to update class.' : 'Failed to create class.'
                    );
                }
            } catch (\Exception $e) {
                ob_clean();
                restore_error_handler();
                wp_send_json_error('An error occurred while saving the class: ' . $e->getMessage());
            }
        } catch (\Error $e) {
            ob_clean();
            restore_error_handler();
            wp_send_json_error('A server error occurred. Please check the error logs.');
        } catch (\Throwable $e) {
            ob_clean();
            restore_error_handler();
            wp_send_json_error('A critical error occurred. Please check the error logs.');
        }
    }

    /**
     * Handle AJAX request to delete class
     */
    public static function deleteClassAjax(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wecoza_class_nonce')) {
            wp_send_json_error('Security check failed.');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Only administrators can delete classes.');
            return;
        }

        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        if (empty($class_id) || $class_id <= 0) {
            wp_send_json_error('Invalid class ID provided.');
            return;
        }

        try {
            $db = wecoza_db();
            $db->beginTransaction();

            try {
                $stmt = $db->query(
                    "DELETE FROM public.classes WHERE class_id = $1 RETURNING class_id",
                    [$class_id]
                );
                $deletedClass = $stmt->fetch();

                if (!$deletedClass) {
                    $db->rollback();
                    wp_send_json_error('Class not found or already deleted.');
                    return;
                }

                $db->commit();
                wp_send_json_success([
                    'message' => 'Class deleted successfully.',
                    'class_id' => $class_id
                ]);

            } catch (\Exception $e) {
                $db->rollback();
                wp_send_json_error('Failed to delete class: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            wp_send_json_error('Database error occurred while deleting class.');
        }
    }

    /**
     * Handle AJAX request to get calendar events
     */
    public static function getCalendarEventsAjax(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wecoza_calendar_nonce')) {
            wp_send_json_error('Security check failed.');
            return;
        }

        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        if (empty($class_id) || $class_id <= 0) {
            wp_send_json_error('Invalid class ID provided.');
            return;
        }

        try {
            $class = ClassRepository::getSingleClass($class_id);

            if (!$class) {
                wp_send_json_error('Class not found.');
                return;
            }

            $scheduleService = new ScheduleService();
            $events = $scheduleService->generateCalendarEvents($class);

            wp_send_json($events);

        } catch (\Exception $e) {
            wp_send_json_error('Error loading calendar events.');
        }
    }

    /**
     * Handle AJAX request to get class subjects
     */
    public static function getClassSubjectsAjax(): void
    {
        if (!isset($_GET['class_type']) || empty($_GET['class_type'])) {
            wp_send_json_error('Class type is required.');
            return;
        }

        $classType = sanitize_text_field($_GET['class_type']);

        try {
            $subjects = ClassTypesController::getClassSubjects($classType);

            if (empty($subjects)) {
                wp_send_json_error('No subjects found for the selected class type.');
                return;
            }

            wp_send_json_success($subjects);

        } catch (\Exception $e) {
            wp_send_json_error('Error loading class subjects.');
        }
    }

    /**
     * Get class notes via AJAX
     */
    public static function getClassNotes(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wecoza_class_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $class_id = intval($_POST['class_id'] ?? 0);
        if (!$class_id) {
            wp_send_json_error('Invalid class ID');
        }

        $notes = ClassRepository::getCachedClassNotes($class_id);

        if ($notes === false) {
            wp_send_json_error('Class not found');
        }

        if (!is_array($notes)) {
            $notes = [];
        }

        foreach ($notes as &$note) {
            if (isset($note['author_id'])) {
                $user = get_user_by('id', $note['author_id']);
                $note['author_name'] = $user ? $user->display_name : 'Unknown';
            }

            if (isset($note['created_at'])) {
                $note['created_at'] = date('c', strtotime($note['created_at']));
            }
            if (isset($note['updated_at'])) {
                $note['updated_at'] = date('c', strtotime($note['updated_at']));
            }
        }

        wp_send_json_success(['notes' => $notes]);
    }

    /**
     * Save class note via AJAX
     */
    public static function saveClassNote(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wecoza_class_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $class_id = intval($_POST['class_id'] ?? 0);
        if (!$class_id) {
            wp_send_json_error('Invalid class ID');
        }

        $note_data = $_POST['note'] ?? null;
        if (!$note_data) {
            wp_send_json_error('No note data provided');
        }

        $note = [
            'id' => !empty($note_data['id']) ? sanitize_text_field($note_data['id']) : uniqid('note_'),
            'content' => stripslashes(sanitize_textarea_field($note_data['content'] ?? '')),
            'category' => isset($note_data['category']) && is_array($note_data['category']) ?
                array_map('sanitize_text_field', $note_data['category']) :
                [sanitize_text_field($note_data['category'] ?? '')],
            'priority' => sanitize_text_field($note_data['priority'] ?? ''),
            'author_id' => get_current_user_id(),
            'created_at' => $note_data['created_at'] ?? date('c'),
            'updated_at' => date('c'),
            'attachments' => $note_data['attachments'] ?? []
        ];

        if (empty($note['content'])) {
            wp_send_json_error('Note content is required');
        }

        if (empty($note['category']) || (is_array($note['category']) && count($note['category']) === 0)) {
            wp_send_json_error('At least one class note type is required');
        }

        if (empty($note['priority'])) {
            wp_send_json_error('Priority is required');
        }

        try {
            $db = wecoza_db();

            $stmt = $db->query(
                "SELECT class_notes_data FROM public.classes WHERE class_id = $1 LIMIT 1",
                [$class_id]
            );

            $result = $stmt->fetch();

            if (!$result) {
                wp_send_json_error('Class not found');
            }

            $notes = [];
            if (!empty($result['class_notes_data'])) {
                $notes_data = json_decode($result['class_notes_data'], true);
                if (is_array($notes_data)) {
                    $notes = $notes_data;
                }
            }

            $note_found = false;
            foreach ($notes as &$existing_note) {
                if ($existing_note['id'] === $note['id']) {
                    $existing_note = array_merge($existing_note, $note);
                    $note_found = true;
                    break;
                }
            }

            if (!$note_found) {
                $notes[] = $note;
            }

            $notes_json = json_encode($notes);
            $db->query(
                "UPDATE public.classes SET class_notes_data = $1, updated_at = NOW() WHERE class_id = $2",
                [$notes_json, $class_id]
            );

            ClassRepository::clearCachedClassNotes($class_id);

            $user = get_user_by('id', $note['author_id']);
            $note['author_name'] = $user ? $user->display_name : 'Unknown';

            wp_send_json_success(['note' => $note]);

        } catch (\PDOException $e) {
            wp_send_json_error('Database error: Failed to save note');
        }
    }

    /**
     * Delete class note via AJAX
     */
    public static function deleteClassNote(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wecoza_class_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $class_id = intval($_POST['class_id'] ?? 0);
        $note_id = sanitize_text_field($_POST['note_id'] ?? '');

        if (!$class_id) {
            wp_send_json_error('Invalid class ID');
        }

        if (!isset($_POST['note_id'])) {
            wp_send_json_error('Note ID not provided');
        }

        try {
            $db = wecoza_db();

            $stmt = $db->query(
                "SELECT class_notes_data FROM public.classes WHERE class_id = $1 LIMIT 1",
                [$class_id]
            );

            $result = $stmt->fetch();

            if (!$result) {
                wp_send_json_error('Class not found');
            }

            $notes = [];
            if (!empty($result['class_notes_data'])) {
                $notes_data = json_decode($result['class_notes_data'], true);
                if (is_array($notes_data)) {
                    $notes = $notes_data;
                }
            }

            $note_found = false;
            foreach ($notes as $index => $existing_note) {
                if ($existing_note['id'] === $note_id) {
                    unset($notes[$index]);
                    $note_found = true;
                    break;
                }
            }

            if (!$note_found) {
                wp_send_json_error('Note not found');
            }

            $notes = array_values($notes);

            $notes_json = json_encode($notes);
            $db->query(
                "UPDATE public.classes SET class_notes_data = $1, updated_at = NOW() WHERE class_id = $2",
                [$notes_json, $class_id]
            );

            ClassRepository::clearCachedClassNotes($class_id);

            wp_send_json_success(['message' => 'Note deleted successfully']);

        } catch (\PDOException $e) {
            wp_send_json_error('Database error: Failed to delete note');
        }
    }

    /**
     * AJAX: Upload attachment to WordPress media library
     */
    public static function uploadAttachment(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wecoza_class_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error('No file uploaded');
            return;
        }

        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'general';

        $uploadService = new UploadService();
        $result = $uploadService->uploadClassAttachment($_FILES['file'], $context);

        if ($result['success']) {
            unset($result['success']);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
}
