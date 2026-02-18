<?php
declare(strict_types=1);

/**
 * WeCoza Core - Learner Model
 *
 * Model for learner data with full persistence support.
 * Uses Repository pattern internally via LearnerRepository.
 *
 * ARCHITECTURE NOTE: Some properties hold display values (names) instead of raw IDs
 * because the DB query transforms them via JOINs/CASE statements. This is intentional:
 * - Display: Model holds "NL2" (name) for direct template output
 * - Forms: Compare by name for pre-selection, submit IDs
 * - Updates: AJAX receives IDs from form, passes to DB
 *
 * @package WeCoza\Learners\Models
 * @since 1.0.0
 */

namespace WeCoza\Learners\Models;

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Core\Abstract\BaseModel;
use WeCoza\Learners\Repositories\LearnerRepository;

if (!defined('ABSPATH')) {
    exit;
}

class LearnerModel extends BaseModel
{
    /**
     * Database table name
     */
    protected static string $table = 'learners';

    /**
     * Primary key column
     */
    protected static string $primaryKey = 'id';

    /**
     * Type casting definitions
     */
    protected static array $casts = [
        'id' => 'int',
        'cityTownId' => 'int',
        'provinceRegionId' => 'int',
        'highestQualification' => 'int',
        'numeracyLevel' => 'int',
        'communicationLevel' => 'int',
        'employmentStatus' => 'string',
        'employerId' => 'int',
        'disabilityStatus' => 'string',
    ];

    // Database columns mapped to properties
    protected ?int $id = null;
    protected ?string $title = null;
    protected string $firstName = '';
    protected ?string $secondName = null;
    protected ?string $initials = null;
    protected string $surname = '';
    protected ?string $gender = null;
    protected ?string $race = null;
    protected ?string $saIdNo = null;
    protected ?string $passportNumber = null;
    protected ?string $telNumber = null;
    protected ?string $alternativeTelNumber = null;
    protected ?string $emailAddress = null;
    protected ?string $addressLine1 = null;
    protected ?string $addressLine2 = null;
    protected ?string $suburb = null;
    protected ?int $cityTownId = null;
    protected ?int $provinceRegionId = null;
    protected ?string $postalCode = null;
    protected ?int $highestQualification = null;
    protected ?string $assessmentStatus = null;
    protected ?string $placementAssessmentDate = null;
    protected ?int $numeracyLevel = null;
    protected ?int $communicationLevel = null;
    protected ?string $employmentStatus = null;
    protected ?int $employerId = null;
    protected ?string $disabilityStatus = null;
    protected ?string $scannedPortfolio = null;
    protected ?string $createdAt = null;
    protected ?string $updatedAt = null;

    // Joined/computed fields (not persisted directly)
    protected ?string $highestQualificationName = null;
    protected ?string $employerName = null;
    protected ?string $cityTownName = null;
    protected ?string $provinceRegionName = null;
    protected ?string $numeracyLevelName = null;
    protected ?string $communicationLevelName = null;

    /**
     * Repository instance (lazy loaded)
     */
    private static ?LearnerRepository $repository = null;

    /**
     * Get repository instance
     */
    private static function getRepository(): LearnerRepository
    {
        if (self::$repository === null) {
            self::$repository = new LearnerRepository();
        }
        return self::$repository;
    }

    /*
    |--------------------------------------------------------------------------
    | Static Query Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get learner by ID
     */
    public static function getById(int $id): ?static
    {
        $data = self::getRepository()->findByIdWithMappings($id);

        if (!$data) {
            return null;
        }

        return new static($data);
    }

    /**
     * Get all learners with optional pagination
     */
    public static function getAll(int $limit = AppConstants::DEFAULT_PAGE_SIZE, int $offset = 0): array
    {
        $results = self::getRepository()->findAll($limit, $offset);

        $learners = [];
        foreach ($results as $row) {
            $learners[] = new static($row);
        }

        return $learners;
    }

    /**
     * Get all learners with full mappings (qualifications, locations, etc.)
     */
    public static function getAllWithMappings(): array
    {
        $results = self::getRepository()->findAllWithMappings();

        $learners = [];
        foreach ($results as $row) {
            $learners[] = new static($row);
        }

        return $learners;
    }

    /**
     * Get total count of learners
     */
    public static function count(): int
    {
        return self::getRepository()->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Persistence Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Save learner (insert new)
     */
    public function save(): bool
    {
        $data = $this->toDbArray(true);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        unset($data['id']);

        $newId = self::getRepository()->insert($data);

        if ($newId) {
            $this->id = (int) $newId;
            return true;
        }

        return false;
    }

    /**
     * Update existing learner
     */
    public function update(): bool
    {
        if (!$this->id) {
            return false;
        }

        $data = $this->toDbArray(true);
        $data['updated_at'] = current_time('mysql');

        return self::getRepository()->update($this->id, $data);
    }

    /**
     * Delete learner
     */
    public function delete(): bool
    {
        if (!$this->id) {
            return false;
        }

        return self::getRepository()->delete($this->id);
    }

    /**
     * Check if learner exists
     */
    public function exists(): bool
    {
        if (!$this->id) {
            return false;
        }

        return self::getRepository()->exists($this->id);
    }

    /**
     * Get portfolios for this learner
     */
    public function getPortfolios(): array
    {
        if (!$this->id) {
            return [];
        }

        return self::getRepository()->getPortfolios($this->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Conversion Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Convert model to database array (snake_case)
     */
    public function toDbArray(bool $includeNull = false): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'first_name' => $this->firstName,
            'second_name' => $this->secondName,
            'initials' => $this->initials,
            'surname' => $this->surname,
            'gender' => $this->gender,
            'race' => $this->race,
            'sa_id_no' => $this->saIdNo,
            'passport_number' => $this->passportNumber,
            'tel_number' => $this->telNumber,
            'alternative_tel_number' => $this->alternativeTelNumber,
            'email_address' => $this->emailAddress,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'suburb' => $this->suburb,
            'city_town_id' => $this->cityTownId,
            'province_region_id' => $this->provinceRegionId,
            'postal_code' => $this->postalCode,
            'highest_qualification' => $this->highestQualification,
            'assessment_status' => $this->assessmentStatus,
            'placement_assessment_date' => $this->placementAssessmentDate,
            'numeracy_level' => $this->numeracyLevel,
            'communication_level' => $this->communicationLevel,
            'employment_status' => $this->employmentStatus,
            'employer_id' => $this->employerId,
            'disability_status' => $this->disabilityStatus,
            'scanned_portfolio' => $this->scannedPortfolio,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];

        if (!$includeNull) {
            $data = array_filter($data, fn($v) => $v !== null);
        }

        return $data;
    }

    /**
     * Convert model to array (camelCase, includes computed fields)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'firstName' => $this->firstName,
            'secondName' => $this->secondName,
            'initials' => $this->initials,
            'surname' => $this->surname,
            'fullName' => trim($this->firstName . ' ' . $this->surname),
            'gender' => $this->gender,
            'race' => $this->race,
            'saIdNo' => $this->saIdNo,
            'passportNumber' => $this->passportNumber,
            'telNumber' => $this->telNumber,
            'alternativeTelNumber' => $this->alternativeTelNumber,
            'emailAddress' => $this->emailAddress,
            'addressLine1' => $this->addressLine1,
            'addressLine2' => $this->addressLine2,
            'suburb' => $this->suburb,
            'cityTownId' => $this->cityTownId,
            'cityTownName' => $this->cityTownName,
            'provinceRegionId' => $this->provinceRegionId,
            'provinceRegionName' => $this->provinceRegionName,
            'postalCode' => $this->postalCode,
            'highestQualification' => $this->highestQualification,
            'highestQualificationName' => $this->highestQualificationName,
            'assessmentStatus' => $this->assessmentStatus,
            'placementAssessmentDate' => $this->placementAssessmentDate,
            'numeracyLevel' => $this->numeracyLevel,
            'numeracyLevelName' => $this->numeracyLevelName,
            'communicationLevel' => $this->communicationLevel,
            'communicationLevelName' => $this->communicationLevelName,
            'employmentStatus' => $this->employmentStatus,
            'employerId' => $this->employerId,
            'employerName' => $this->employerName,
            'disabilityStatus' => $this->disabilityStatus,
            'scannedPortfolio' => $this->scannedPortfolio,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function getFirstName(): string { return $this->firstName; }
    public function getSecondName(): ?string { return $this->secondName; }
    public function getInitials(): ?string { return $this->initials; }
    public function getSurname(): string { return $this->surname; }
    public function getFullName(): string { return trim($this->firstName . ' ' . $this->surname); }

    /**
     * Get formatted full name with title and second name
     */
    public function getFormattedFullName(): string
    {
        $name = '';
        if ($this->title) {
            $name .= $this->title . '. ';
        }
        $name .= $this->firstName;
        if ($this->secondName) {
            $name .= ' ' . $this->secondName;
        }
        $name .= ' ' . $this->surname;
        return trim($name);
    }

    public function getGender(): ?string { return $this->gender; }
    public function getRace(): ?string { return $this->race; }
    public function getSaIdNo(): ?string { return $this->saIdNo; }
    public function getPassportNumber(): ?string { return $this->passportNumber; }
    public function getTelNumber(): ?string { return $this->telNumber; }
    public function getAlternativeTelNumber(): ?string { return $this->alternativeTelNumber; }
    public function getEmailAddress(): ?string { return $this->emailAddress; }
    public function getAddressLine1(): ?string { return $this->addressLine1; }
    public function getAddressLine2(): ?string { return $this->addressLine2; }
    public function getSuburb(): ?string { return $this->suburb; }
    public function getCityTownId(): ?int { return $this->cityTownId; }
    public function getCityTownName(): ?string { return $this->cityTownName; }
    public function getProvinceRegionId(): ?int { return $this->provinceRegionId; }
    public function getProvinceRegionName(): ?string { return $this->provinceRegionName; }
    public function getPostalCode(): ?string { return $this->postalCode; }
    public function getHighestQualification(): ?int { return $this->highestQualification; }
    public function getHighestQualificationName(): ?string { return $this->highestQualificationName; }
    public function getAssessmentStatus(): ?string { return $this->assessmentStatus; }
    public function getPlacementAssessmentDate(): ?string { return $this->placementAssessmentDate; }
    public function getNumeracyLevel(): ?int { return $this->numeracyLevel; }
    public function getCommunicationLevel(): ?int { return $this->communicationLevel; }
    public function getEmploymentStatus(): ?string { return $this->employmentStatus; }
    public function getEmployerId(): ?int { return $this->employerId; }
    public function getEmployerName(): ?string { return $this->employerName; }
    public function getDisabilityStatus(): ?string { return $this->disabilityStatus; }
    public function getScannedPortfolio(): ?string { return $this->scannedPortfolio; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    /*
    |--------------------------------------------------------------------------
    | Setters (Fluent Interface)
    |--------------------------------------------------------------------------
    */

    public function setId(?int $id): static { $this->id = $id; return $this; }
    public function setTitle(?string $title): static { $this->title = $title; return $this; }
    public function setFirstName(string $firstName): static { $this->firstName = $firstName; return $this; }
    public function setSecondName(?string $secondName): static { $this->secondName = $secondName; return $this; }
    public function setInitials(?string $initials): static { $this->initials = $initials; return $this; }
    public function setSurname(string $surname): static { $this->surname = $surname; return $this; }
    public function setGender(?string $gender): static { $this->gender = $gender; return $this; }
    public function setRace(?string $race): static { $this->race = $race; return $this; }
    public function setSaIdNo(?string $saIdNo): static { $this->saIdNo = $saIdNo; return $this; }
    public function setPassportNumber(?string $passportNumber): static { $this->passportNumber = $passportNumber; return $this; }
    public function setTelNumber(?string $telNumber): static { $this->telNumber = $telNumber; return $this; }
    public function setAlternativeTelNumber(?string $alternativeTelNumber): static { $this->alternativeTelNumber = $alternativeTelNumber; return $this; }
    public function setEmailAddress(?string $emailAddress): static { $this->emailAddress = $emailAddress; return $this; }
    public function setAddressLine1(?string $addressLine1): static { $this->addressLine1 = $addressLine1; return $this; }
    public function setAddressLine2(?string $addressLine2): static { $this->addressLine2 = $addressLine2; return $this; }
    public function setSuburb(?string $suburb): static { $this->suburb = $suburb; return $this; }
    public function setCityTownId(?int $cityTownId): static { $this->cityTownId = $cityTownId; return $this; }
    public function setProvinceRegionId(?int $provinceRegionId): static { $this->provinceRegionId = $provinceRegionId; return $this; }
    public function setPostalCode(?string $postalCode): static { $this->postalCode = $postalCode; return $this; }
    public function setHighestQualification(?int $highestQualification): static { $this->highestQualification = $highestQualification; return $this; }
    public function setAssessmentStatus(?string $assessmentStatus): static { $this->assessmentStatus = $assessmentStatus; return $this; }
    public function setPlacementAssessmentDate(?string $placementAssessmentDate): static { $this->placementAssessmentDate = $placementAssessmentDate; return $this; }
    public function setNumeracyLevel(?int $numeracyLevel): static { $this->numeracyLevel = $numeracyLevel; return $this; }
    public function setCommunicationLevel(?int $communicationLevel): static { $this->communicationLevel = $communicationLevel; return $this; }
    public function setEmploymentStatus(?string $employmentStatus): static { $this->employmentStatus = $employmentStatus; return $this; }
    public function setEmployerId(?int $employerId): static { $this->employerId = $employerId; return $this; }
    public function setDisabilityStatus(?string $disabilityStatus): static { $this->disabilityStatus = $disabilityStatus; return $this; }
    public function setScannedPortfolio(?string $scannedPortfolio): static { $this->scannedPortfolio = $scannedPortfolio; return $this; }
}
