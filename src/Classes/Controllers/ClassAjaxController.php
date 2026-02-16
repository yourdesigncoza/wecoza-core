<?php
declare(strict_types=1);

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
use WeCoza\Learners\Services\ProgressionService;
use WeCoza\Events\Services\EventDispatcher;

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

                // Capture old state before update for event dispatching
                $oldClassData = null;
                $oldLearnerIds = [];

                if ($isUpdate) {
                    $class = ClassModel::getById($classId);
                    if (!$class) {
                        ob_clean();
                        restore_error_handler();
                        wp_send_json_error('Class not found for update.');
                        return;
                    }

                    // Capture old state for event diffing
                    $oldClassData = $class->toArray();
                    $oldLearnerIds = $class->getLearnerIdsOnly();

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

                    // Dispatch notification events (non-blocking)
                    self::dispatchClassEvents($class, $isUpdate, $oldClassData, $oldLearnerIds);

                    // Auto-create LP records for newly assigned learners
                    $lpCreationResults = self::createLPsForNewLearners($class, $formData, $isUpdate);

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

                    $message = $isUpdate ? 'Class updated successfully.' : 'Draft class created successfully.';

                    // Append LP creation summary to message
                    if (!empty($lpCreationResults['created'])) {
                        $message .= " Created {$lpCreationResults['created']} LP record(s).";
                    }
                    if (!empty($lpCreationResults['warnings'])) {
                        $message .= " {$lpCreationResults['warnings']} learner(s) had existing LPs put on hold.";
                    }

                    wp_send_json_success([
                        'message' => $message,
                        'class_id' => $class->getId(),
                        'redirect_url' => $redirect_url,
                        'lp_creation_results' => $lpCreationResults
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

            // Capture class data before deletion for event dispatching
            $classDataBeforeDelete = null;
            try {
                $class = ClassModel::getById($class_id);
                if ($class) {
                    $classDataBeforeDelete = $class->toArray();
                }
            } catch (\Exception $e) {
                // Non-blocking: continue with deletion even if we can't capture data
                wecoza_log("Could not capture class data before deletion: " . $e->getMessage(), 'warning');
            }

            $db->beginTransaction();

            try {
                $stmt = $db->query(
                    "DELETE FROM public.classes WHERE class_id = ? RETURNING class_id",
                    [$class_id]
                );
                $deletedClass = $stmt->fetch();

                if (!$deletedClass) {
                    $db->rollback();
                    wp_send_json_error('Class not found or already deleted.');
                    return;
                }

                $db->commit();

                // Dispatch CLASS_DELETE event (non-blocking)
                if ($classDataBeforeDelete) {
                    try {
                        EventDispatcher::classDeleted($class_id, $classDataBeforeDelete);
                    } catch (\Throwable $e) {
                        wecoza_log('Class delete event dispatch failed: ' . $e->getMessage(), 'warning');
                    }
                }

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
                $note['created_at'] = wp_date('c', strtotime($note['created_at']));
            }
            if (isset($note['updated_at'])) {
                $note['updated_at'] = wp_date('c', strtotime($note['updated_at']));
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
            'created_at' => $note_data['created_at'] ?? wp_date('c'),
            'updated_at' => wp_date('c'),
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
                "SELECT class_notes_data FROM public.classes WHERE class_id = ? LIMIT 1",
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
                "UPDATE public.classes SET class_notes_data = ?, updated_at = NOW() WHERE class_id = ?",
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
                "SELECT class_notes_data FROM public.classes WHERE class_id = ? LIMIT 1",
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
                "UPDATE public.classes SET class_notes_data = ?, updated_at = NOW() WHERE class_id = ?",
                [$notes_json, $class_id]
            );

            ClassRepository::clearCachedClassNotes($class_id);

            wp_send_json_success(['message' => 'Note deleted successfully']);

        } catch (\PDOException $e) {
            wp_send_json_error('Database error: Failed to delete note');
        }
    }

    /**
     * Create LP records for newly assigned learners
     *
     * Compares current learner_ids with previous (for updates) and creates
     * LP records for new learners.
     *
     * @param ClassModel $class The class model
     * @param array $formData The form data
     * @param bool $isUpdate Whether this is an update operation
     * @return array Results with 'created', 'warnings', 'errors', 'details'
     */
    private static function createLPsForNewLearners(ClassModel $class, array $formData, bool $isUpdate): array
    {
        $results = [
            'created' => 0,
            'warnings' => 0,
            'errors' => 0,
            'details' => [],
        ];

        // Get the product_id (class subject) from the class
        $productId = $class->getClassSubject(); // This should return the product_id
        if (!$productId) {
            $results['details'][] = 'No product/course assigned to class - LP creation skipped';
            return $results;
        }

        // Get current learner IDs from the class
        $currentLearnerIds = $class->getLearnerIdsOnly();
        if (empty($currentLearnerIds)) {
            return $results;
        }

        // For updates, get previous learner IDs to determine new ones
        $previousLearnerIds = [];
        if ($isUpdate) {
            // Fetch the previous state from database
            try {
                $db = wecoza_db();
                $stmt = $db->query(
                    "SELECT learner_ids FROM classes WHERE class_id = :class_id",
                    ['class_id' => $class->getId()]
                );
                $row = $stmt->fetch();
                if ($row && !empty($row['learner_ids'])) {
                    $previousData = is_string($row['learner_ids'])
                        ? json_decode($row['learner_ids'], true)
                        : $row['learner_ids'];

                    if (is_array($previousData)) {
                        foreach ($previousData as $learner) {
                            if (isset($learner['id'])) {
                                $previousLearnerIds[] = (int) $learner['id'];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("WeCoza Core: Error fetching previous learner IDs: " . $e->getMessage());
            }
        }

        // Calculate newly added learners
        $newLearnerIds = $isUpdate
            ? array_diff($currentLearnerIds, $previousLearnerIds)
            : $currentLearnerIds;

        if (empty($newLearnerIds)) {
            return $results;
        }

        // Check if force override is enabled (from form data or always true for class assignment)
        $forceOverride = !empty($formData['force_lp_override']) || true; // Always force for now

        // Create LP records for each new learner
        $progressionService = new ProgressionService();

        foreach ($newLearnerIds as $learnerId) {
            try {
                $lpResult = $progressionService->createLPForClassAssignment(
                    (int) $learnerId,
                    (int) $productId,
                    $class->getId(),
                    $forceOverride
                );

                if ($lpResult['success']) {
                    $results['created']++;

                    if ($lpResult['collision_data']) {
                        $results['warnings']++;
                        $results['details'][] = "Learner {$learnerId}: Created LP (previous LP put on hold)";
                    } else {
                        $results['details'][] = "Learner {$learnerId}: Created LP successfully";
                    }
                } else {
                    if ($lpResult['warning']) {
                        $results['warnings']++;
                        $results['details'][] = "Learner {$learnerId}: {$lpResult['message']}";
                    } else {
                        $results['errors']++;
                        $results['details'][] = "Learner {$learnerId}: Failed - {$lpResult['message']}";
                    }
                }
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = "Learner {$learnerId}: Exception - " . $e->getMessage();
                error_log("WeCoza Core: LP creation error for learner {$learnerId}: " . $e->getMessage());
            }
        }

        // Clear the learners cache since progression data has changed
        delete_transient('wecoza_class_learners_with_progression');

        return $results;
    }

    /**
     * Dispatch notification events for class create/update and learner roster changes
     *
     * Events are dispatched asynchronously and failures do not affect the main operation.
     *
     * @param ClassModel $class The saved class model
     * @param bool $isUpdate Whether this was an update operation
     * @param array|null $oldClassData Previous class data (for updates)
     * @param array $oldLearnerIds Previous learner IDs (for updates)
     */
    private static function dispatchClassEvents(
        ClassModel $class,
        bool $isUpdate,
        ?array $oldClassData,
        array $oldLearnerIds
    ): void {
        try {
            $classId = $class->getId();
            $newClassData = $class->toArray();

            if ($isUpdate) {
                // Dispatch CLASS_UPDATE event
                EventDispatcher::classUpdated($classId, $newClassData, $oldClassData);

                // Detect and dispatch learner roster changes
                self::dispatchLearnerRosterEvents($class, $oldLearnerIds);

                // Check for status change (explicit event for visibility)
                $oldStatus = $oldClassData['class_status'] ?? null;
                $newStatus = $newClassData['class_status'] ?? null;
                if ($oldStatus !== null && $newStatus !== null && $oldStatus !== $newStatus) {
                    EventDispatcher::boot()->dispatchStatusChange(
                        $classId,
                        (string) $oldStatus,
                        (string) $newStatus,
                        $newClassData
                    );
                }
            } else {
                // Dispatch CLASS_INSERT event
                EventDispatcher::classCreated($classId, $newClassData);

                // Dispatch LEARNER_ADD events for initial learners
                $initialLearnerIds = $class->getLearnerIdsOnly();
                foreach ($initialLearnerIds as $learnerId) {
                    EventDispatcher::learnerAdded($classId, (int) $learnerId, [
                        'learner_id' => $learnerId,
                        'action' => 'initial_assignment',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Log but don't fail the main operation - notifications are secondary
            wecoza_log('Event dispatch failed: ' . $e->getMessage(), 'warning');
        }
    }

    /**
     * Dispatch LEARNER_ADD and LEARNER_REMOVE events for roster changes
     *
     * @param ClassModel $class The updated class model
     * @param array $oldLearnerIds Previous learner IDs
     */
    private static function dispatchLearnerRosterEvents(ClassModel $class, array $oldLearnerIds): void
    {
        $classId = $class->getId();
        $newLearnerIds = $class->getLearnerIdsOnly();

        // Find added learners (in new but not in old)
        $addedLearnerIds = array_diff($newLearnerIds, $oldLearnerIds);
        foreach ($addedLearnerIds as $learnerId) {
            try {
                EventDispatcher::learnerAdded($classId, (int) $learnerId, [
                    'learner_id' => $learnerId,
                    'action' => 'added_to_class',
                ]);
            } catch (\Throwable $e) {
                wecoza_log("Learner add event dispatch failed for learner {$learnerId}: " . $e->getMessage(), 'warning');
            }
        }

        // Find removed learners (in old but not in new)
        $removedLearnerIds = array_diff($oldLearnerIds, $newLearnerIds);
        foreach ($removedLearnerIds as $learnerId) {
            try {
                EventDispatcher::learnerRemoved($classId, (int) $learnerId, [
                    'learner_id' => $learnerId,
                    'action' => 'removed_from_class',
                ]);
            } catch (\Throwable $e) {
                wecoza_log("Learner remove event dispatch failed for learner {$learnerId}: " . $e->getMessage(), 'warning');
            }
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

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions.', 403);
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
