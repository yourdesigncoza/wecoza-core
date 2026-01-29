<?php
/**
 * WeCoza Core - Learner Repository
 *
 * Data access layer for learner CRUD operations.
 * Extends BaseRepository for common patterns.
 *
 * @package WeCoza\Learners\Repositories
 * @since 1.0.0
 */

namespace WeCoza\Learners\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use PDO;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class LearnerRepository extends BaseRepository
{
    /**
     * Table name
     */
    protected static string $table = 'learners';

    /**
     * Primary key column
     */
    protected static string $primaryKey = 'id';

    /*
    |--------------------------------------------------------------------------
    | Query Methods with Mappings
    |--------------------------------------------------------------------------
    */

    /**
     * Base query with common joins for full learner data
     */
    private function baseQueryWithMappings(): string
    {
        return "
            WITH portfolio_info AS (
                SELECT
                    learner_id,
                    string_agg(file_path || '|' || upload_date::text, ', ' ORDER BY upload_date DESC) as portfolio_details
                FROM learner_portfolios
                GROUP BY learner_id
            )
            SELECT
                learners.*,
                learner_qualifications.qualification AS highest_qualification,
                locations.suburb AS suburb,
                locations.town AS city_town_name,
                locations.province AS province_region_name,
                locations.postal_code AS postal_code,
                employers.employer_name AS employer_name,
                numeracy_level_table.level AS numeracy_level,
                communication_level_table.level AS communication_level,
                CASE WHEN learners.employment_status = true THEN 'Employed' ELSE 'Unemployed' END AS employment_status,
                CASE WHEN learners.disability_status = true THEN 'Yes' ELSE 'No' END AS disability_status,
                portfolio_info.portfolio_details
            FROM learners
            LEFT JOIN learner_qualifications
                ON learners.highest_qualification = learner_qualifications.id
            LEFT JOIN locations
                ON learners.city_town_id = locations.location_id
            LEFT JOIN employers
                ON learners.employer_id = employers.employer_id
            LEFT JOIN learner_placement_level AS numeracy_level_table
                ON learners.numeracy_level = numeracy_level_table.placement_level_id
            LEFT JOIN learner_placement_level AS communication_level_table
                ON learners.communication_level = communication_level_table.placement_level_id
            LEFT JOIN portfolio_info
                ON learners.id = portfolio_info.learner_id
        ";
    }

    /**
     * Find learner by ID with all mappings
     */
    public function findByIdWithMappings(int $id): ?array
    {
        $sql = $this->baseQueryWithMappings() . " WHERE learners.id = :id";

        try {
            $stmt = $this->db->query($sql, ['id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $result = $this->processPortfolioDetails($result);
            }

            return $result ?: null;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerRepository findByIdWithMappings error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find all learners with mappings (cached)
     */
    public function findAllWithMappings(): array
    {
        $cacheKey = 'learner_db_get_learners_mappings';
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $sql = $this->baseQueryWithMappings() . " ORDER BY learners.created_at DESC";

        try {
            $stmt = $this->db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Process portfolio details for each learner
            foreach ($results as &$learner) {
                $learner = $this->processPortfolioDetails($learner);
            }

            // Cache for 12 hours
            set_transient($cacheKey, $results, 12 * HOUR_IN_SECONDS);

            return $results;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerRepository findAllWithMappings error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Process portfolio details from combined string
     */
    private function processPortfolioDetails(array $learner): array
    {
        if (!empty($learner['portfolio_details'])) {
            $portfolios = [];
            $dates = [];

            $portfolioItems = explode(', ', $learner['portfolio_details']);
            foreach ($portfolioItems as $item) {
                $parts = explode('|', $item);
                if (count($parts) === 2) {
                    $portfolios[] = $parts[0];
                    $dates[] = $parts[1];
                }
            }

            $learner['scanned_portfolio'] = implode(', ', $portfolios);
            $learner['portfolio_upload_dates'] = $dates;
        } else {
            $learner['scanned_portfolio'] = '';
            $learner['portfolio_upload_dates'] = [];
        }

        return $learner;
    }

    /*
    |--------------------------------------------------------------------------
    | Insert with Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Insert new learner with validation
     */
    public function insert(array $data): ?int
    {
        try {
            $pdo = $this->db->getPdo();
            $pdo->beginTransaction();

            // Validate highest_qualification if provided
            if (!empty($data['highest_qualification'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM learner_qualifications WHERE id = :id");
                $stmt->execute(['id' => $data['highest_qualification']]);
                if ($stmt->fetchColumn() == 0) {
                    throw new Exception("Invalid highest qualification ID: " . $data['highest_qualification']);
                }
            }

            // Build insert SQL
            $columns = array_keys($data);
            $placeholders = array_map(fn($c) => ":{$c}", $columns);

            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s) RETURNING %s",
                static::$table,
                implode(', ', $columns),
                implode(', ', $placeholders),
                static::$primaryKey
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            $newId = (int) $stmt->fetchColumn();

            $pdo->commit();

            // Clear cache
            delete_transient('learner_db_get_learners_mappings');

            return $newId;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("WeCoza Core: LearnerRepository insert error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update learner
     */
    public function update(int $id, array $data): bool
    {
        $result = parent::update($id, $data);

        if ($result) {
            delete_transient('learner_db_get_learners_mappings');
        }

        return $result;
    }

    /**
     * Delete learner
     */
    public function delete(int $id): bool
    {
        $result = parent::delete($id);

        if ($result) {
            delete_transient('learner_db_get_learners_mappings');
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Dropdown Data (Cached)
    |--------------------------------------------------------------------------
    */

    /**
     * Get locations for dropdowns (cached 15 min)
     */
    public function getLocations(): array
    {
        $cacheKey = 'learner_db_locations';
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        try {
            $pdo = $this->db->getPdo();

            // PostgreSQL DISTINCT ON query
            $citiesSql = "
                SELECT DISTINCT ON (LOWER(town))
                    location_id,
                    town
                FROM locations
                WHERE town IS NOT NULL AND town != ''
                ORDER BY LOWER(town), location_id ASC
            ";

            $provincesSql = "
                SELECT DISTINCT ON (LOWER(province))
                    location_id,
                    province
                FROM locations
                WHERE province IS NOT NULL AND province != ''
                ORDER BY LOWER(province), location_id ASC
            ";

            $citiesStmt = $pdo->query($citiesSql);
            $provincesStmt = $pdo->query($provincesSql);

            $result = [
                'cities' => $citiesStmt->fetchAll(PDO::FETCH_ASSOC),
                'provinces' => $provincesStmt->fetchAll(PDO::FETCH_ASSOC),
            ];

            set_transient($cacheKey, $result, 15 * MINUTE_IN_SECONDS);

            return $result;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerRepository getLocations error: " . $e->getMessage());
            return ['cities' => [], 'provinces' => []];
        }
    }

    /**
     * Get qualifications for dropdown (cached 15 min)
     */
    public function getQualifications(): array
    {
        $cacheKey = 'learner_db_qualifications';
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        try {
            $stmt = $this->db->query("SELECT id, qualification FROM learner_qualifications ORDER BY qualification ASC");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            set_transient($cacheKey, $result, 15 * MINUTE_IN_SECONDS);

            return $result;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerRepository getQualifications error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get placement levels for dropdown (cached 15 min)
     */
    public function getPlacementLevels(): array
    {
        $cacheKey = 'learner_db_placement_levels';
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        try {
            $stmt = $this->db->query("SELECT placement_level_id, level FROM learner_placement_level ORDER BY level ASC");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            set_transient($cacheKey, $result, 15 * MINUTE_IN_SECONDS);

            return $result;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerRepository getPlacementLevels error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get employers for dropdown (cached 15 min)
     */
    public function getEmployers(): array
    {
        $cacheKey = 'learner_db_employers';
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        try {
            $stmt = $this->db->query("SELECT employer_id, employer_name FROM employers ORDER BY employer_name ASC");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            set_transient($cacheKey, $result, 15 * MINUTE_IN_SECONDS);

            return $result;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerRepository getEmployers error: " . $e->getMessage());
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Portfolio Management
    |--------------------------------------------------------------------------
    */

    /**
     * Get portfolios for a learner
     */
    public function getPortfolios(int $learnerId): array
    {
        $sql = "
            SELECT portfolio_id, file_path, upload_date
            FROM learner_portfolios
            WHERE learner_id = :learner_id
            ORDER BY upload_date DESC
        ";

        try {
            $stmt = $this->db->query($sql, ['learner_id' => $learnerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerRepository getPortfolios error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save learner portfolios
     */
    public function savePortfolios(int $learnerId, array $files): array
    {
        try {
            $pdo = $this->db->getPdo();
            $uploadDir = wp_upload_dir();
            $portfolioDir = $uploadDir['basedir'] . '/portfolios/';
            $portfolioPaths = [];

            if (!file_exists($portfolioDir)) {
                wp_mkdir_p($portfolioDir);
            }

            $pdo->beginTransaction();

            if (!is_array($files['name']) || empty($files['name'][0])) {
                throw new Exception('No files were uploaded.');
            }

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = $files['name'][$i];
                    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    if ($fileExt === 'pdf') {
                        $newFilename = uniqid('portfolio_', true) . '.pdf';
                        $filePath = $portfolioDir . $newFilename;
                        $relativePath = 'portfolios/' . $newFilename;

                        if (move_uploaded_file($files['tmp_name'][$i], $filePath)) {
                            $portfolioPaths[] = $relativePath;

                            $stmt = $pdo->prepare("
                                INSERT INTO learner_portfolios (learner_id, file_path)
                                VALUES (:learner_id, :file_path)
                            ");
                            $stmt->execute([
                                'learner_id' => $learnerId,
                                'file_path' => $relativePath,
                            ]);
                        }
                    }
                }
            }

            if (!empty($portfolioPaths)) {
                $portfolioList = implode(', ', $portfolioPaths);
                $stmt = $pdo->prepare("UPDATE learners SET scanned_portfolio = :paths WHERE id = :id");
                $stmt->execute(['paths' => $portfolioList, 'id' => $learnerId]);
            }

            $pdo->commit();
            delete_transient('learner_db_get_learners_mappings');

            return [
                'success' => true,
                'message' => 'Files uploaded successfully',
                'paths' => $portfolioPaths,
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("WeCoza Core: LearnerRepository savePortfolios error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error processing files: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete portfolio file
     */
    public function deletePortfolio(int $portfolioId): bool
    {
        try {
            $pdo = $this->db->getPdo();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT file_path, learner_id
                FROM learner_portfolios
                WHERE portfolio_id = :portfolio_id
            ");
            $stmt->execute(['portfolio_id' => $portfolioId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$file) {
                $pdo->rollBack();
                return false;
            }

            $uploadDir = wp_upload_dir();
            $filePath = $uploadDir['basedir'] . '/' . $file['file_path'];

            if (file_exists($filePath)) {
                @unlink($filePath);
            }

            $pdo->prepare("DELETE FROM learner_portfolios WHERE portfolio_id = :id")
                ->execute(['id' => $portfolioId]);

            // Update learners table with remaining portfolios
            $stmt = $pdo->prepare("
                SELECT file_path FROM learner_portfolios
                WHERE learner_id = :learner_id
                ORDER BY upload_date DESC
            ");
            $stmt->execute(['learner_id' => $file['learner_id']]);
            $remaining = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $pdo->prepare("UPDATE learners SET scanned_portfolio = :paths WHERE id = :id")
                ->execute([
                    'paths' => !empty($remaining) ? implode(', ', $remaining) : null,
                    'id' => $file['learner_id'],
                ]);

            $pdo->commit();
            delete_transient('learner_db_get_learners_mappings');

            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("WeCoza Core: LearnerRepository deletePortfolio error: " . $e->getMessage());
            return false;
        }
    }
}
