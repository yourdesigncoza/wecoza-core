<?php
declare(strict_types=1);

/**
 * WeCoza Core - QA Visit Model
 *
 * QA visit tracking model.
 * Migrated from wecoza-classes-plugin.
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

class QAVisitModel extends BaseModel
{
    protected static string $table = 'qa_visits';
    protected static string $primaryKey = 'id';

    private ?int $id = null;
    private ?int $classId = null;
    private ?string $visitDate = null;
    private ?string $visitType = null;
    private ?string $officerName = null;
    private mixed $latestDocument = null;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;

    public function __construct($data = [])
    {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }

    public function hydrate(array $data): static
    {
        $this->setId($data['id'] ?? null);
        $this->setClassId($data['class_id'] ?? null);
        $this->setVisitDate($data['visit_date'] ?? null);
        $this->setVisitType($data['visit_type'] ?? null);
        $this->setOfficerName($data['officer_name'] ?? null);
        $this->setLatestDocument($data['latest_document'] ?? null);
        $this->setCreatedAt($data['created_at'] ?? null);
        $this->setUpdatedAt($data['updated_at'] ?? null);

        return $this;
    }

    public function save(): bool
    {
        if ($this->getId()) {
            return $this->update();
        } else {
            return $this->create();
        }
    }

    private function create(): bool
    {
        $db = wecoza_db();

        $sql = "INSERT INTO qa_visits (
            class_id, visit_date, visit_type, officer_name, latest_document, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $this->getClassId(),
            $this->getVisitDate(),
            $this->getVisitType(),
            $this->getOfficerName(),
            $this->getLatestDocument() ? json_encode($this->getLatestDocument()) : null,
            current_time('mysql'),
            current_time('mysql')
        ];

        $db->query($sql, $params);
        $this->setId($db->lastInsertId());

        return true;
    }

    public function update(): bool
    {
        $db = wecoza_db();

        $sql = "UPDATE qa_visits SET
            class_id = ?, visit_date = ?, visit_type = ?, officer_name = ?,
            latest_document = ?, updated_at = ?
            WHERE id = ?";

        $params = [
            $this->getClassId(),
            $this->getVisitDate(),
            $this->getVisitType(),
            $this->getOfficerName(),
            $this->getLatestDocument() ? json_encode($this->getLatestDocument()) : null,
            current_time('mysql'),
            $this->getId()
        ];

        return $db->query($sql, $params) !== false;
    }

    public function delete(): bool
    {
        if (!$this->getId()) {
            return false;
        }

        $db = wecoza_db();
        $sql = "DELETE FROM qa_visits WHERE id = ?";

        return $db->query($sql, [$this->getId()]) !== false;
    }

    public static function getById(int $id): ?static
    {
        $db = wecoza_db();
        $sql = "SELECT * FROM qa_visits WHERE id = ?";

        $stmt = $db->query($sql, [$id]);

        if ($row = $stmt->fetch()) {
            return new static($row);
        }

        return null;
    }

    public static function findById(int $id): ?static
    {
        return self::getById($id);
    }

    public static function findByClassId(int $classId): array
    {
        $db = wecoza_db();
        $sql = "SELECT * FROM qa_visits WHERE class_id = ? ORDER BY visit_date DESC";

        $stmt = $db->query($sql, [$classId]);

        $visits = [];
        while ($row = $stmt->fetch()) {
            $visits[] = new self($row);
        }

        return $visits;
    }

    public static function findByOfficer(string $officerName): array
    {
        $db = wecoza_db();
        $sql = "SELECT * FROM qa_visits WHERE officer_name = ? ORDER BY visit_date DESC";

        $stmt = $db->query($sql, [$officerName]);

        $visits = [];
        while ($row = $stmt->fetch()) {
            $visits[] = new self($row);
        }

        return $visits;
    }

    public static function findByDateRange(string $startDate, string $endDate): array
    {
        $db = wecoza_db();
        $sql = "SELECT * FROM qa_visits WHERE visit_date BETWEEN ? AND ? ORDER BY visit_date DESC";

        $stmt = $db->query($sql, [$startDate, $endDate]);

        $visits = [];
        while ($row = $stmt->fetch()) {
            $visits[] = new self($row);
        }

        return $visits;
    }

    public static function deleteByClassId(int $classId): bool
    {
        $db = wecoza_db();
        $sql = "DELETE FROM qa_visits WHERE class_id = ?";

        return $db->query($sql, [$classId]) !== false;
    }

    public static function getCountByClassId(int $classId): int
    {
        $db = wecoza_db();
        $sql = "SELECT COUNT(*) as count FROM qa_visits WHERE class_id = ?";

        $stmt = $db->query($sql, [$classId]);
        $row = $stmt->fetch();

        return $row ? (int)$row['count'] : 0;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'class_id' => $this->getClassId(),
            'visit_date' => $this->getVisitDate(),
            'visit_type' => $this->getVisitType(),
            'officer_name' => $this->getOfficerName(),
            'latest_document' => $this->getLatestDocument(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt()
        ];
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function setId($id): self { $this->id = $id ? (int)$id : null; return $this; }

    public function getClassId(): ?int { return $this->classId; }
    public function setClassId($classId): self { $this->classId = $classId ? (int)$classId : null; return $this; }

    public function getVisitDate(): ?string { return $this->visitDate; }
    public function setVisitDate($visitDate): self { $this->visitDate = $visitDate; return $this; }

    public function getVisitType(): ?string { return $this->visitType; }
    public function setVisitType($visitType): self { $this->visitType = $visitType; return $this; }

    public function getOfficerName(): ?string { return $this->officerName; }
    public function setOfficerName($officerName): self { $this->officerName = $officerName; return $this; }

    public function getLatestDocument() { return $this->latestDocument; }
    public function setLatestDocument($latestDocument): self {
        if (is_string($latestDocument)) {
            $this->latestDocument = json_decode($latestDocument, true);
        } else {
            $this->latestDocument = $latestDocument;
        }
        return $this;
    }

    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt($createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function setUpdatedAt($updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
