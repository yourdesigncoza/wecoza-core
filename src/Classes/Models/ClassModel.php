<?php
/**
 * WeCoza Core - Class Model
 *
 * Main class entity model for training class management.
 * Migrated from wecoza-classes-plugin to wecoza-core.
 *
 * @package WeCoza\Classes\Models
 * @since 1.0.0
 */

namespace WeCoza\Classes\Models;

use WeCoza\Core\Abstract\BaseModel;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class ClassModel extends BaseModel
{
    protected static string $table = 'classes';
    protected static string $primaryKey = 'class_id';

    // Class properties
    private ?int $id = null;
    private ?int $clientId = null;
    private string|int|null $siteId = null;
    private ?string $classAddressLine = null;
    private ?string $classType = null;
    private ?string $classSubject = null;
    private ?string $classCode = null;
    private ?int $classDuration = null;
    private ?string $originalStartDate = null;
    private ?bool $setaFunded = null;
    private ?string $seta = null;
    private ?bool $examClass = null;
    private ?string $examType = null;
    private ?int $classAgent = null;
    private ?int $initialClassAgent = null;
    private ?string $initialAgentStartDate = null;
    private ?int $projectSupervisorId = null;
    private array $learnerIds = [];
    private array $examLearners = [];
    private array $backupAgentIds = [];
    private array $agentReplacements = [];
    private array $scheduleData = [];
    private array $stopRestartDates = [];
    private array $classNotesData = [];
    private array $eventDates = [];
    private ?string $order_nr = null;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;

    // Enriched data (not stored in database)
    public ?string $client_name = null;
    public ?string $agent_name = null;
    public ?string $supervisor_name = null;
    public ?string $site_name = null;

    public function __construct($data = null)
    {
        if ($data) {
            $this->hydrate($data);
        }
    }

    public function hydrate(array $data): static
    {
        $this->setId($data['class_id'] ?? $data['id'] ?? null);
        $this->setClientId($data['client_id'] ?? null);
        $this->setSiteId($data['site_id'] ?? null);
        $this->setClassAddressLine($data['class_address_line'] ?? $data['site_address'] ?? null);
        $this->setClassType($data['class_type'] ?? null);
        $this->setClassSubject($data['class_subject'] ?? null);
        $this->setClassCode($data['class_code'] ?? null);
        $this->setClassDuration($data['class_duration'] ?? null);
        $this->setOriginalStartDate($data['original_start_date'] ?? $data['class_start_date'] ?? null);
        $this->setSetaFunded($data['seta_funded'] ?? null);
        $this->setSeta($data['seta'] ?? $data['seta_id'] ?? null);
        $this->setExamClass($data['exam_class'] ?? null);
        $this->setExamType($data['exam_type'] ?? null);
        $this->setClassAgent($data['class_agent'] ?? null);
        $this->setInitialClassAgent($data['initial_class_agent'] ?? null);
        $this->setInitialAgentStartDate($data['initial_agent_start_date'] ?? null);
        $this->setProjectSupervisorId($data['project_supervisor_id'] ?? $data['project_supervisor'] ?? null);
        $this->setCreatedAt($data['created_at'] ?? null);
        $this->setUpdatedAt($data['updated_at'] ?? null);

        // Handle JSONB arrays
        $this->setLearnerIds($this->parseJsonField($data['learner_ids'] ?? $data['learnerIds'] ?? $data['add_learner'] ?? []));
        $this->setExamLearners($this->parseJsonField($data['exam_learners'] ?? $data['examLearners'] ?? []));
        $this->setBackupAgentIds($this->parseJsonField($data['backup_agent_ids'] ?? $data['backupAgentIds'] ?? $data['backup_agent'] ?? []));
        $this->setAgentReplacements($this->parseJsonField($data['agent_replacements'] ?? $data['agentReplacements'] ?? []));
        $this->setScheduleData($this->parseJsonField($data['schedule_data'] ?? $data['scheduleData'] ?? []));
        $this->setStopRestartDates($this->parseJsonField($data['stop_restart_dates'] ?? []));
        $this->setClassNotesData($this->parseJsonField($data['class_notes_data'] ?? $data['classNotes'] ?? $data['class_notes'] ?? []));
        $this->setEventDates($this->parseJsonField($data['event_dates'] ?? $data['eventDates'] ?? []));
        $this->setOrderNr($data['order_nr'] ?? null);

        // Load agent replacements from database if existing class
        if ($this->getId()) {
            $this->setAgentReplacements($this->loadAgentReplacements());
        }

        return $this;
    }

    private function parseJsonField($field): array
    {
        if (is_string($field)) {
            return json_decode($field, true) ?: [];
        }
        return is_array($field) ? $field : [];
    }

    public static function getById(int $id): ?static
    {
        try {
            $db = wecoza_db();
            $stmt = $db->query("SELECT * FROM classes WHERE class_id = ?", [$id]);

            if ($row = $stmt->fetch()) {
                return new self($row);
            }

            return null;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error fetching class: ' . $e->getMessage());
            return null;
        }
    }

    public function save(): bool
    {
        try {
            $db = wecoza_db();
            $db->beginTransaction();

            $now = date('Y-m-d H:i:s');
            $this->setCreatedAt($now);
            $this->setUpdatedAt($now);

            $stopRestartJson = $this->prepareStopRestartDates();

            $sql = "INSERT INTO classes (
                client_id, site_id, class_address_line, class_type, class_subject,
                class_code, class_duration, original_start_date, seta_funded, seta,
                exam_class, exam_type, class_agent, initial_class_agent,
                initial_agent_start_date, project_supervisor_id,
                learner_ids, exam_learners, backup_agent_ids, schedule_data, stop_restart_dates,
                class_notes_data, event_dates, order_nr, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $this->getClientId(),
                $this->getSiteId(),
                $this->getClassAddressLine(),
                $this->getClassType(),
                $this->getClassSubject(),
                $this->getClassCode(),
                $this->getClassDuration(),
                $this->getOriginalStartDate(),
                $this->getSetaFunded() ? 'true' : 'false',
                $this->getSeta(),
                $this->getExamClass() ? 'true' : 'false',
                $this->getExamType(),
                $this->getClassAgent(),
                $this->getInitialClassAgent(),
                $this->getInitialAgentStartDate(),
                $this->getProjectSupervisorId(),
                json_encode($this->getLearnerIds()),
                json_encode($this->getExamLearners()),
                json_encode($this->getBackupAgentIds()),
                json_encode($this->getScheduleData()),
                $stopRestartJson,
                json_encode($this->getClassNotesData()),
                json_encode($this->getEventDates()),
                $this->getOrderNr(),
                $this->getCreatedAt(),
                $this->getUpdatedAt()
            ];

            $db->query($sql, $params);
            $classId = $db->lastInsertId();
            $this->setId($classId);

            $this->saveAgentReplacements();

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('WeCoza Core: Error saving class: ' . $e->getMessage());
            return false;
        }
    }

    public function update(): bool
    {
        try {
            $db = wecoza_db();
            $db->beginTransaction();

            $this->setUpdatedAt(date('Y-m-d H:i:s'));
            $stopRestartJson = $this->prepareStopRestartDates();

            $sql = "UPDATE classes SET
                client_id = ?, site_id = ?, class_address_line = ?, class_type = ?,
                class_subject = ?, class_code = ?, class_duration = ?, original_start_date = ?,
                seta_funded = ?, seta = ?, exam_class = ?, exam_type = ?,
                class_agent = ?, initial_class_agent = ?, initial_agent_start_date = ?,
                project_supervisor_id = ?, learner_ids = ?, exam_learners = ?, backup_agent_ids = ?,
                schedule_data = ?, stop_restart_dates = ?, class_notes_data = ?, event_dates = ?, order_nr = ?, updated_at = ?
                WHERE class_id = ?";

            $params = [
                $this->getClientId(), $this->getSiteId(), $this->getClassAddressLine(),
                $this->getClassType(), $this->getClassSubject(), $this->getClassCode(),
                $this->getClassDuration(), $this->getOriginalStartDate(),
                $this->getSetaFunded() ? 'true' : 'false',
                $this->getSeta(),
                $this->getExamClass() ? 'true' : 'false',
                $this->getExamType(),
                $this->getClassAgent(), $this->getInitialClassAgent(),
                $this->getInitialAgentStartDate(), $this->getProjectSupervisorId(),
                json_encode($this->getLearnerIds()),
                json_encode($this->getExamLearners()), json_encode($this->getBackupAgentIds()), json_encode($this->getScheduleData()),
                $stopRestartJson, json_encode($this->getClassNotesData()),
                json_encode($this->getEventDates()), $this->getOrderNr(),
                $this->getUpdatedAt(), $this->getId()
            ];

            $db->query($sql, $params);
            $this->saveAgentReplacements();

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('WeCoza Core: Error updating class: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(): bool
    {
        try {
            $db = wecoza_db();
            $db->query("DELETE FROM classes WHERE class_id = ?", [$this->getId()]);
            return true;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error deleting class: ' . $e->getMessage());
            return false;
        }
    }

    private function prepareStopRestartDates(): string
    {
        $stopDates = $this->getStopDates();
        $restartDates = $this->getRestartDates();
        $combined = [];

        if (!empty($stopDates) && !empty($restartDates)) {
            for ($i = 0; $i < max(count($stopDates), count($restartDates)); $i++) {
                if (!empty($stopDates[$i]) || !empty($restartDates[$i])) {
                    $combined[] = [
                        'stop_date' => $stopDates[$i] ?? null,
                        'restart_date' => $restartDates[$i] ?? null
                    ];
                }
            }
        }

        return json_encode($combined);
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function setId(mixed $id): self { $this->id = $id ? (int)$id : null; return $this; }

    public function getClientId(): ?int { return $this->clientId; }
    public function setClientId(mixed $clientId): self {
        $this->clientId = is_numeric($clientId) ? (int)$clientId : null;
        return $this;
    }

    public function getSiteId(): string|int|null { return $this->siteId; }
    public function setSiteId(mixed $siteId): self {
        $this->siteId = (is_string($siteId) || is_numeric($siteId)) ? $siteId : null;
        return $this;
    }

    public function getClassAddressLine(): ?string { return $this->classAddressLine; }
    public function setClassAddressLine(?string $classAddressLine): self { $this->classAddressLine = $classAddressLine; return $this; }

    public function getClassType(): ?string { return $this->classType; }
    public function setClassType(?string $classType): self { $this->classType = $classType; return $this; }

    public function getClassSubject(): ?string { return $this->classSubject; }
    public function setClassSubject(?string $classSubject): self { $this->classSubject = $classSubject; return $this; }

    public function getClassCode(): ?string { return $this->classCode; }
    public function setClassCode(?string $classCode): self { $this->classCode = $classCode; return $this; }

    public function getClassDuration(): ?int { return $this->classDuration; }
    public function setClassDuration(mixed $classDuration): self { $this->classDuration = $classDuration ? (int)$classDuration : null; return $this; }

    public function getOriginalStartDate(): ?string { return $this->originalStartDate; }
    public function setOriginalStartDate(?string $originalStartDate): self { $this->originalStartDate = $originalStartDate; return $this; }

    public function getSetaFunded(): bool {
        return $this->setaFunded ?? false;
    }
    public function setSetaFunded(mixed $setaFunded): self {
        $this->setaFunded = match (true) {
            $setaFunded === 'Yes' => true,
            $setaFunded === 'No' => false,
            is_bool($setaFunded) => $setaFunded,
            default => null,
        };
        return $this;
    }

    public function getSeta(): ?string { return $this->seta; }
    public function setSeta(mixed $seta): self {
        $this->seta = is_string($seta) ? $seta : null;
        return $this;
    }

    public function getExamClass(): bool {
        return $this->examClass ?? false;
    }
    public function setExamClass(mixed $examClass): self {
        $this->examClass = match (true) {
            $examClass === 'Yes' => true,
            $examClass === 'No' => false,
            is_bool($examClass) => $examClass,
            default => null,
        };
        return $this;
    }

    public function getExamType(): ?string { return $this->examType; }
    public function setExamType(?string $examType): self { $this->examType = $examType; return $this; }

    public function getClassAgent(): ?int { return $this->classAgent; }
    public function setClassAgent(mixed $classAgent): self {
        $this->classAgent = is_numeric($classAgent) ? (int)$classAgent : null;
        return $this;
    }

    public function getInitialClassAgent(): ?int { return $this->initialClassAgent; }
    public function setInitialClassAgent(mixed $initialClassAgent): self {
        $this->initialClassAgent = is_numeric($initialClassAgent) ? (int)$initialClassAgent : null;
        return $this;
    }

    public function getInitialAgentStartDate(): ?string { return $this->initialAgentStartDate; }
    public function setInitialAgentStartDate(mixed $initialAgentStartDate): self {
        $this->initialAgentStartDate = is_string($initialAgentStartDate) ? $initialAgentStartDate : null;
        return $this;
    }

    public function getProjectSupervisorId(): ?int { return $this->projectSupervisorId; }
    public function setProjectSupervisorId(mixed $projectSupervisorId): self {
        $this->projectSupervisorId = is_numeric($projectSupervisorId) ? (int)$projectSupervisorId : null;
        return $this;
    }

    public function getLearnerIds(): array { return $this->learnerIds; }
    public function setLearnerIds(mixed $learnerIds): self { $this->learnerIds = is_array($learnerIds) ? $learnerIds : []; return $this; }

    public function getExamLearners(): array { return $this->examLearners; }
    public function setExamLearners(mixed $examLearners): self { $this->examLearners = is_array($examLearners) ? $examLearners : []; return $this; }

    public function getBackupAgentIds(): array { return $this->backupAgentIds; }
    public function setBackupAgentIds(mixed $backupAgentIds): self { $this->backupAgentIds = is_array($backupAgentIds) ? $backupAgentIds : []; return $this; }

    public function getAgentReplacements(): array { return $this->agentReplacements; }
    public function setAgentReplacements(mixed $agentReplacements): self { $this->agentReplacements = is_array($agentReplacements) ? $agentReplacements : []; return $this; }

    public function getScheduleData(): array { return $this->scheduleData; }
    public function setScheduleData(mixed $scheduleData): self { $this->scheduleData = is_array($scheduleData) ? $scheduleData : []; return $this; }

    public function getStopRestartDates(): array { return $this->stopRestartDates; }
    public function setStopRestartDates(mixed $stopRestartDates): self { $this->stopRestartDates = is_array($stopRestartDates) ? $stopRestartDates : []; return $this; }

    public function getStopDates(): array {
        return array_column($this->stopRestartDates, 'stop_date');
    }

    public function getRestartDates(): array {
        return array_column($this->stopRestartDates, 'restart_date');
    }

    public function getClassNotesData(): array { return $this->classNotesData; }
    public function setClassNotesData(mixed $classNotesData): self { $this->classNotesData = is_array($classNotesData) ? $classNotesData : []; return $this; }

    public function getEventDates(): array {
        return array_map(function($event) {
            $event['status'] = $event['status'] ?? 'Pending';
            return $event;
        }, $this->eventDates);
    }
    public function setEventDates(mixed $eventDates): self { $this->eventDates = is_array($eventDates) ? $eventDates : []; return $this; }

    public function getOrderNr(): ?string { return $this->order_nr; }
    public function setOrderNr(mixed $order_nr): self { $this->order_nr = is_string($order_nr) ? $order_nr : null; return $this; }

    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt(?string $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function setUpdatedAt(?string $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function isDraft(): bool {
        return empty($this->order_nr);
    }

    public function isActive(): bool {
        return !empty($this->order_nr);
    }

    public function getStatus(): string {
        return $this->isActive() ? 'Active' : 'Draft';
    }

    public function getLearnerData(): array
    {
        if (empty($this->learnerIds)) {
            return [];
        }

        $firstItem = reset($this->learnerIds);

        if (is_array($firstItem) && isset($firstItem['id'])) {
            return $this->learnerIds;
        } else {
            return array_map(function($id) {
                return [
                    'id' => intval($id),
                    'name' => 'Legacy Learner',
                    'status' => 'CIC - Currently in Class',
                    'level' => ''
                ];
            }, $this->learnerIds);
        }
    }

    public function getLearnerIdsOnly(): array
    {
        if (empty($this->learnerIds)) {
            return [];
        }

        $firstItem = reset($this->learnerIds);

        if (is_array($firstItem) && isset($firstItem['id'])) {
            return array_map(function($learner) {
                return intval($learner['id']);
            }, $this->learnerIds);
        } else {
            return array_map('intval', $this->learnerIds);
        }
    }

    public static function getAll($limit = null, $offset = 0): array
    {
        try {
            $db = wecoza_db();
            $sql = "SELECT * FROM classes ORDER BY created_at DESC";

            if ($limit) {
                $sql .= " LIMIT ? OFFSET ?";
                $stmt = $db->query($sql, [$limit, $offset]);
            } else {
                $stmt = $db->query($sql);
            }

            $classes = [];
            while ($row = $stmt->fetch()) {
                $classes[] = new self($row);
            }

            return $classes;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error fetching classes: ' . $e->getMessage());
            return [];
        }
    }

    public static function search($criteria = []): array
    {
        try {
            $db = wecoza_db();
            $where = [];
            $params = [];

            if (!empty($criteria['client_id'])) {
                $where[] = "client_id = ?";
                $params[] = $criteria['client_id'];
            }

            if (!empty($criteria['class_type'])) {
                $where[] = "class_type = ?";
                $params[] = $criteria['class_type'];
            }

            if (!empty($criteria['class_agent'])) {
                $where[] = "class_agent = ?";
                $params[] = $criteria['class_agent'];
            }

            $sql = "SELECT * FROM classes";
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            $sql .= " ORDER BY created_at DESC";

            $stmt = $db->query($sql, $params);
            $classes = [];

            while ($row = $stmt->fetch()) {
                $classes[] = new self($row);
            }

            return $classes;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error searching classes: ' . $e->getMessage());
            return [];
        }
    }

    private function saveAgentReplacements(): bool
    {
        if (empty($this->agentReplacements) || !$this->getId()) {
            return true;
        }

        try {
            $db = wecoza_db();

            $deleteStmt = $db->prepare("DELETE FROM agent_replacements WHERE class_id = ?");
            $deleteStmt->execute([$this->getId()]);

            $insertStmt = $db->prepare("
                INSERT INTO agent_replacements (class_id, original_agent_id, replacement_agent_id, start_date)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($this->agentReplacements as $replacement) {
                if (isset($replacement['agent_id']) && isset($replacement['date'])) {
                    $originalAgentId = $this->getInitialClassAgent() ?: $this->getClassAgent();

                    $insertStmt->execute([
                        $this->getId(),
                        $originalAgentId,
                        $replacement['agent_id'],
                        $replacement['date']
                    ]);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error saving agent replacements: ' . $e->getMessage());
            return false;
        }
    }

    private function loadAgentReplacements(): array
    {
        if (!$this->getId()) {
            return [];
        }

        try {
            $db = wecoza_db();
            $stmt = $db->prepare("
                SELECT replacement_agent_id, start_date, reason
                FROM agent_replacements
                WHERE class_id = ?
                ORDER BY start_date ASC
            ");
            $stmt->execute([$this->getId()]);

            $replacements = [];
            while ($row = $stmt->fetch()) {
                $replacements[] = [
                    'agent_id' => $row['replacement_agent_id'],
                    'date' => $row['start_date'],
                    'reason' => $row['reason']
                ];
            }

            return $replacements;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error loading agent replacements: ' . $e->getMessage());
            return [];
        }
    }

    public function getQAVisits(): array
    {
        if (!$this->getId()) {
            return [];
        }

        return QAVisitModel::findByClassId($this->getId());
    }

    public function toArray(): array
    {
        return [
            'class_id' => $this->getId(),
            'client_id' => $this->getClientId(),
            'site_id' => $this->getSiteId(),
            'class_address_line' => $this->getClassAddressLine(),
            'class_type' => $this->getClassType(),
            'class_subject' => $this->getClassSubject(),
            'class_code' => $this->getClassCode(),
            'class_duration' => $this->getClassDuration(),
            'original_start_date' => $this->getOriginalStartDate(),
            'seta_funded' => $this->getSetaFunded(),
            'seta' => $this->getSeta(),
            'exam_class' => $this->getExamClass(),
            'exam_type' => $this->getExamType(),
            'class_agent' => $this->getClassAgent(),
            'initial_class_agent' => $this->getInitialClassAgent(),
            'initial_agent_start_date' => $this->getInitialAgentStartDate(),
            'project_supervisor_id' => $this->getProjectSupervisorId(),
            'learner_ids' => $this->getLearnerIds(),
            'exam_learners' => $this->getExamLearners(),
            'backup_agent_ids' => $this->getBackupAgentIds(),
            'agent_replacements' => $this->getAgentReplacements(),
            'schedule_data' => $this->getScheduleData(),
            'stop_restart_dates' => $this->getStopRestartDates(),
            'class_notes_data' => $this->getClassNotesData(),
            'event_dates' => $this->getEventDates(),
            'order_nr' => $this->getOrderNr(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            'status' => $this->getStatus(),
        ];
    }
}
