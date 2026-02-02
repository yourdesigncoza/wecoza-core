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
    | Column Whitelisting (Security)
    |--------------------------------------------------------------------------
    */

    /**
     * Columns allowed for ORDER BY
     */
    protected function getAllowedOrderColumns(): array
    {
        return [
            'id', 'first_name', 'surname', 'email_address',
            'created_at', 'updated_at', 'city_town_id', 'employer_id'
        ];
    }

    /**
     * Columns allowed for WHERE filtering
     */
    protected function getAllowedFilterColumns(): array
    {
        return [
            'id', 'first_name', 'surname', 'email_address', 'sa_id_no',
            'city_town_id', 'province_region_id', 'employer_id',
            'employment_status', 'disability_status', 'created_at', 'updated_at'
        ];
    }

    /**
     * Columns allowed for INSERT
     */
    protected function getAllowedInsertColumns(): array
    {
        return [
            'title', 'first_name', 'second_name', 'initials', 'surname',
            'gender', 'race', 'sa_id_no', 'passport_number',
            'tel_number', 'alternative_tel_number', 'email_address',
            'address_line_1', 'address_line_2', 'suburb',
            'city_town_id', 'province_region_id', 'postal_code',
            'highest_qualification', 'assessment_status',
            'placement_assessment_date', 'numeracy_level', 'communication_level',
            'employment_status', 'employer_id', 'disability_status',
            'scanned_portfolio', 'created_at', 'updated_at'
        ];
    }

    /**
     * Columns allowed for UPDATE
     */
    protected function getAllowedUpdateColumns(): array
    {
        // Same as insert, minus created_at
        $columns = $this->getAllowedInsertColumns();
        return array_values(array_diff($columns, ['created_at']));
    }

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
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::findByIdWithMappings'));
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
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::findAllWithMappings'));
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
        // Filter data to only allowed columns (SQL injection prevention)
        $allowedColumns = $this->getAllowedInsertColumns();
        $filteredData = $this->filterAllowedColumns($data, $allowedColumns);

        if (empty($filteredData)) {
            error_log("WeCoza Core: LearnerRepository insert rejected - no valid columns in data");
            return null;
        }

        try {
            $pdo = $this->db->getPdo();
            $pdo->beginTransaction();

            // Validate highest_qualification if provided
            if (!empty($filteredData['highest_qualification'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM learner_qualifications WHERE id = :id");
                $stmt->execute(['id' => $filteredData['highest_qualification']]);
                if ($stmt->fetchColumn() == 0) {
                    throw new Exception("Invalid highest qualification ID: " . $filteredData['highest_qualification']);
                }
            }

            // Build insert SQL
            $columns = array_keys($filteredData);
            $placeholders = array_map(fn($c) => ":{$c}", $columns);

            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s) RETURNING %s",
                static::$table,
                implode(', ', $columns),
                implode(', ', $placeholders),
                static::$primaryKey
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute($filteredData);
            $newId = (int) $stmt->fetchColumn();

            $pdo->commit();

            // Clear cache
            delete_transient('learner_db_get_learners_mappings');

            return $newId;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::insert'));
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
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::getLocations'));
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
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::getQualifications'));
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
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::getPlacementLevels'));
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
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::getEmployers'));
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Progression Context (for Class Learner Selection)
    |--------------------------------------------------------------------------
    */

    /**
     * Get learners with their progression context for class assignment
     *
     * Optimized query that fetches:
     * - Last completed course (product name, completion date)
     * - Current active LP (product_id, product_name, progress %, class_id)
     *
     * Uses window functions to avoid N+1 queries.
     *
     * @param array $filters Optional filters ['client_id', 'exclude_class_id']
     * @return array Learners with progression context
     */
    public function getLearnersWithProgressionContext(array $filters = []): array
    {
        $sql = "
            WITH last_completed AS (
                SELECT DISTINCT ON (lpt.learner_id)
                    lpt.learner_id,
                    lpt.product_id AS last_product_id,
                    p.product_name AS last_course_name,
                    lpt.completion_date AS last_completion_date
                FROM learner_lp_tracking lpt
                LEFT JOIN products p ON lpt.product_id = p.product_id
                WHERE lpt.status = 'completed'
                ORDER BY lpt.learner_id, lpt.completion_date DESC
            ),
            active_lp AS (
                SELECT
                    lpt.learner_id,
                    lpt.tracking_id AS active_tracking_id,
                    lpt.product_id AS active_product_id,
                    p.product_name AS active_course_name,
                    p.product_duration AS active_product_duration,
                    lpt.class_id AS active_class_id,
                    c.class_code AS active_class_code,
                    lpt.hours_present AS active_hours_present,
                    lpt.start_date AS active_start_date,
                    CASE
                        WHEN p.product_duration > 0 THEN
                            LEAST(100, ROUND((lpt.hours_present / p.product_duration) * 100, 1))
                        ELSE 0
                    END AS active_progress_pct
                FROM learner_lp_tracking lpt
                LEFT JOIN products p ON lpt.product_id = p.product_id
                LEFT JOIN classes c ON lpt.class_id = c.class_id
                WHERE lpt.status = 'in_progress'
            )
            SELECT
                l.id,
                l.first_name,
                l.surname,
                CONCAT(l.first_name, ' ', l.surname) AS full_name,
                l.sa_id_no,
                l.cell_phone,
                l.email_address,
                lc.last_course_name,
                lc.last_completion_date,
                alp.active_tracking_id,
                alp.active_product_id,
                alp.active_course_name,
                alp.active_class_id,
                alp.active_class_code,
                alp.active_hours_present,
                alp.active_product_duration,
                alp.active_progress_pct,
                alp.active_start_date,
                CASE WHEN alp.active_tracking_id IS NOT NULL THEN true ELSE false END AS has_active_lp
            FROM learners l
            LEFT JOIN last_completed lc ON l.id = lc.learner_id
            LEFT JOIN active_lp alp ON l.id = alp.learner_id
        ";

        $conditions = [];
        $params = [];

        // Filter by client if provided
        if (!empty($filters['client_id'])) {
            // Join to classes to filter by client
            $sql .= " LEFT JOIN classes cl ON l.id = ANY(
                SELECT jsonb_array_elements(cl2.learner_ids::jsonb)->>'id'::int
                FROM classes cl2 WHERE cl2.client_id = :client_id
            )";
            $params['client_id'] = $filters['client_id'];
        }

        // Exclude learners already in a specific class
        if (!empty($filters['exclude_class_id'])) {
            $conditions[] = "(alp.active_class_id IS NULL OR alp.active_class_id != :exclude_class_id)";
            $params['exclude_class_id'] = $filters['exclude_class_id'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY l.surname, l.first_name";

        try {
            $stmt = $this->db->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::getLearnersWithProgressionContext'));
            return [];
        }
    }

    /**
     * Get active LP for a specific learner
     *
     * Used for collision detection when adding learner to class.
     *
     * @param int $learnerId
     * @return array|null Active LP data or null if none
     */
    public function getActiveLPForLearner(int $learnerId): ?array
    {
        $sql = "
            SELECT
                lpt.tracking_id,
                lpt.product_id,
                p.product_name,
                lpt.class_id,
                c.class_code,
                lpt.hours_present,
                lpt.hours_trained,
                p.product_duration,
                lpt.start_date,
                CASE
                    WHEN p.product_duration > 0 THEN
                        LEAST(100, ROUND((lpt.hours_present / p.product_duration) * 100, 1))
                    ELSE 0
                END AS progress_pct
            FROM learner_lp_tracking lpt
            LEFT JOIN products p ON lpt.product_id = p.product_id
            LEFT JOIN classes c ON lpt.class_id = c.class_id
            WHERE lpt.learner_id = :learner_id
            AND lpt.status = 'in_progress'
            LIMIT 1
        ";

        try {
            $stmt = $this->db->query($sql, ['learner_id' => $learnerId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::getActiveLPForLearner'));
            return null;
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
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::getPortfolios'));
            return [];
        }
    }

    /**
     * Save learner portfolios
     */
    public function savePortfolios(int $learnerId, array $files): array
    {
        $pdo = null;  // Initialize to prevent catch block crash
        try {
            $pdo = $this->db->getPdo();
            $uploadDir = wp_upload_dir();
            $portfolioDir = $uploadDir['basedir'] . '/portfolios/';
            $portfolioPaths = [];

            if (!file_exists($portfolioDir)) {
                wp_mkdir_p($portfolioDir);
            }

            $pdo->beginTransaction();

            // Fetch existing portfolios to append, not overwrite
            $existingStmt = $pdo->prepare("SELECT scanned_portfolio FROM learners WHERE id = :id");
            $existingStmt->execute(['id' => $learnerId]);
            $existingPortfolios = $existingStmt->fetchColumn();
            $currentPaths = $existingPortfolios ? array_map('trim', explode(',', $existingPortfolios)) : [];

            if (!is_array($files['name']) || empty($files['name'][0])) {
                throw new Exception('No files were uploaded.');
            }

            $skippedCount = 0;

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = $files['name'][$i];
                    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    if ($fileExt === 'pdf') {
                        // Validate actual MIME type (SEC-04: prevent malicious files)
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
                        finfo_close($finfo);

                        if ($mimeType !== 'application/pdf') {
                            // Generic error per CONTEXT.md decision - do NOT reveal detected MIME
                            $skippedCount++;
                            continue; // Skip invalid file, process others
                        }

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
                    } else {
                        $skippedCount++;
                    }
                }
            }

            if (!empty($portfolioPaths)) {
                // Merge existing and new paths, remove duplicates
                $allPaths = array_merge($currentPaths, $portfolioPaths);
                $uniquePaths = array_unique(array_filter($allPaths));
                $portfolioList = implode(', ', $uniquePaths);
                $stmt = $pdo->prepare("UPDATE learners SET scanned_portfolio = :paths WHERE id = :id");
                $stmt->execute(['paths' => $portfolioList, 'id' => $learnerId]);
            }

            $pdo->commit();
            delete_transient('learner_db_get_learners_mappings');

            return [
                'success' => true,
                'message' => $skippedCount > 0
                    ? 'Some files were skipped due to invalid type. Please upload PDF documents only.'
                    : 'Files uploaded successfully',
                'paths' => $portfolioPaths,
                'skipped' => $skippedCount,
            ];
        } catch (Exception $e) {
            if ($pdo !== null && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::savePortfolios'));
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
        $pdo = null;  // Initialize to prevent catch block crash
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
            if ($pdo !== null && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::deletePortfolio'));
            return false;
        }
    }
}
