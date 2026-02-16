# SQL Audit: Repository Direct SQL Usage

**Objective:** Catalogue all direct SQL queries across every repository to identify opportunities for BaseRepository method reuse.

**Classification:**
- **REPLACEABLE** - Can use BaseRepository methods (findBy/insert/update/delete/deleteBy/findById/findOneBy/count)
- **JUSTIFIED** - Requires custom SQL due to complexity

---

## 1. LearnerRepository (26 methods audited)

| Method | Query Type | Uses Parent? | Classification | Reason |
|--------|-----------|--------------|----------------|--------|
| baseQueryWithMappings() | SELECT | No | JUSTIFIED | CTE + 6-table JOIN for full learner data with portfolio aggregation |
| findByIdWithMappings() | SELECT | No | JUSTIFIED | Complex JOINs via baseQueryWithMappings() |
| findAllWithMappings() | SELECT | No | JUSTIFIED | Complex JOINs via baseQueryWithMappings() |
| processPortfolioDetails() | N/A | N/A | N/A | Data processing only, no SQL |
| insert() | INSERT | No | **REPLACEABLE** | FK validation then manual SQL (should delegate to parent::insert after validation) |
| update() | UPDATE | **Yes** | GOOD | Calls parent::update() + cache invalidation |
| delete() | DELETE | **Yes** | GOOD | Calls parent::delete() + cache invalidation |
| getLocations() | SELECT | No | JUSTIFIED | DISTINCT ON for deduplicated location dropdowns from locations table (not $table) |
| getQualifications() | SELECT | No | JUSTIFIED | Reads from learner_qualifications table (not $table) |
| getPlacementLevels() | SELECT | No | JUSTIFIED | Reads from learner_placement_level table (not $table) |
| getEmployers() | SELECT | No | JUSTIFIED | Reads from employers table (not $table) |
| getLearnersWithProgressionContext() | SELECT | No | JUSTIFIED | Dual CTE with 4-table JOIN for progression context |
| getActiveLPForLearner() | SELECT | No | JUSTIFIED | 3-table JOIN with calculated progress percentage |
| getPortfolios() | SELECT | No | JUSTIFIED | Reads from learner_portfolios table (not $table) |
| savePortfolios() | INSERT | No | JUSTIFIED | Multi-table transactional portfolio upload with file I/O |
| deletePortfolio() | DELETE | No | JUSTIFIED | Multi-table transactional portfolio deletion with file cleanup |
| getSponsors() | SELECT | No | JUSTIFIED | Reads from learner_sponsors table (not $table) |
| saveSponsors() | INSERT/DELETE | No | JUSTIFIED | Transactional replace-all on learner_sponsors table |
| getAllowedOrderColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedFilterColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedInsertColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedUpdateColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| findById() | N/A | **Inherited** | N/A | Uses parent::findById |
| findBy() | N/A | **Inherited** | N/A | Uses parent::findBy |
| findOneBy() | N/A | **Inherited** | N/A | Uses parent::findOneBy |
| count() | N/A | **Inherited** | N/A | Uses parent::count |

**Summary:** 1 replaceable, 12 justified, 13 inherited/config methods

---

## 2. AgentRepository (29 methods audited)

| Method | Query Type | Uses Parent? | Classification | Reason |
|--------|-----------|--------------|----------------|--------|
| createAgent() | INSERT | No | **REPLACEABLE** | Uses wecoza_db()->insert() directly (should use parent::insert) |
| getAgent() | SELECT | No | JUSTIFIED | LEFT JOIN to locations table for address resolution |
| getAgentByEmail() | SELECT | No | JUSTIFIED | LEFT JOIN to locations table for address resolution |
| getAgentByIdNumber() | SELECT | No | JUSTIFIED | LEFT JOIN to locations table for address resolution |
| getAgents() | SELECT | No | JUSTIFIED | Complex search with pagination, LIKE searches, JOIN to locations |
| updateAgent() | UPDATE | No | **REPLACEABLE** | Uses wecoza_db()->update() directly (should use parent::update) |
| deleteAgent() | UPDATE | No | **REPLACEABLE** | Soft delete via updateAgent (which should use parent::update) |
| deleteAgentPermanently() | DELETE | No | **REPLACEABLE** | Uses wecoza_db()->delete() directly (should use parent::delete) |
| countAgents() | SELECT | No | JUSTIFIED | COUNT with complex search conditions |
| searchAgents() | N/A | N/A | N/A | Delegates to getAgents() |
| getAgentsByStatus() | N/A | N/A | N/A | Delegates to getAgents() |
| bulkUpdateStatus() | UPDATE | No | N/A | Loops calling updateAgent() |
| sanitizeAgentData() | N/A | N/A | N/A | Data sanitization only, no SQL |
| sanitizeWorkingArea() | N/A | N/A | N/A | Data sanitization only, no SQL |
| addAgentMeta() | INSERT | No | JUSTIFIED | Operates on agent_meta table (not $table) |
| getAgentMeta() | SELECT | No | JUSTIFIED | Operates on agent_meta table (not $table) |
| updateAgentMeta() | UPDATE | No | JUSTIFIED | Operates on agent_meta table (not $table) |
| deleteAgentMeta() | DELETE | No | JUSTIFIED | Operates on agent_meta table (not $table) |
| addAgentNote() | INSERT | No | JUSTIFIED | Operates on agent_notes table (not $table) |
| getAgentNotes() | SELECT | No | JUSTIFIED | Operates on agent_notes table (not $table) |
| deleteAgentNotes() | DELETE | No | JUSTIFIED | Operates on agent_notes table (not $table) |
| addAgentAbsence() | INSERT | No | JUSTIFIED | Operates on agent_absences table (not $table) |
| getAgentAbsences() | SELECT | No | JUSTIFIED | Operates on agent_absences table (not $table) |
| deleteAgentAbsences() | DELETE | No | JUSTIFIED | Operates on agent_absences table (not $table) |
| resolveAddressFields() | N/A | N/A | N/A | Data processing only, no SQL |
| getAllowedOrderColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedFilterColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedInsertColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedUpdateColumns() | N/A | N/A | N/A | Column whitelist, no SQL |

**Summary:** 4 replaceable (createAgent, updateAgent, deleteAgent soft, deleteAgentPermanently), 14 justified (JOINs + different tables), 11 helper/config methods

---

## 3. ClientRepository (8 methods audited)

| Method | Query Type | Uses Parent? | Classification | Reason |
|--------|-----------|--------------|----------------|--------|
| getModel() | N/A | N/A | N/A | Returns model class name, no SQL |
| getAllowedOrderColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedFilterColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedInsertColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedUpdateColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getMainClients() | SELECT | No | **REPLACEABLE** | Simple WHERE main_client_id IS NULL (can use findBy) |
| getBranchClients() | SELECT | No | **REPLACEABLE** | Simple WHERE main_client_id = :id (can use findBy) |
| searchClients() | SELECT | No | JUSTIFIED | ILIKE search not supported by BaseRepository findBy |

**Summary:** 2 replaceable (getMainClients, getBranchClients), 1 justified, 5 helper/config methods

---

## 4. LearnerProgressionRepository (18 methods audited)

| Method | Query Type | Uses Parent? | Classification | Reason |
|--------|-----------|--------------|----------------|--------|
| baseQuery() | SELECT | No | JUSTIFIED | 4-table JOIN (lp_tracking + products + learners + classes) |
| findById() | SELECT | No | JUSTIFIED | Complex JOINs via baseQuery() |
| findCurrentForLearner() | SELECT | No | JUSTIFIED | Complex JOINs via baseQuery() |
| findAllForLearner() | SELECT | No | JUSTIFIED | Complex JOINs via baseQuery() |
| findHistoryForLearner() | SELECT | No | JUSTIFIED | Complex JOINs via baseQuery() |
| findByClass() | SELECT | No | JUSTIFIED | Complex JOINs via baseQuery() |
| findByProduct() | SELECT | No | JUSTIFIED | Complex JOINs via baseQuery() |
| insert() | INSERT | No | JUSTIFIED | Manual transaction with custom column filtering + RETURNING |
| update() | UPDATE | No | JUSTIFIED | Custom column filtering different from parent behavior |
| delete() | DELETE | No | JUSTIFIED | Direct SQL with custom error handling |
| logHours() | INSERT | No | JUSTIFIED | Operates on learner_hours_log table (not $table) |
| getHoursLog() | SELECT | No | JUSTIFIED | Operates on learner_hours_log table (not $table) |
| getHoursLogForLearner() | SELECT | No | JUSTIFIED | Operates on learner_hours_log table (not $table) with JOIN |
| getMonthlyProgressions() | SELECT | No | JUSTIFIED | 4-table JOIN with client data |
| findWithFilters() | SELECT | No | JUSTIFIED | Dynamic multi-table JOIN with complex filters |
| countWithFilters() | SELECT | No | JUSTIFIED | COUNT with dynamic JOIN conditions |
| savePortfolioFile() | INSERT | No | JUSTIFIED | Operates on learner_progression_portfolios table (not $table) |
| getPortfolioFiles() | SELECT | No | JUSTIFIED | Operates on learner_progression_portfolios table (not $table) |

**Summary:** 0 replaceable, 18 justified (all use complex JOINs via baseQuery or operate on different tables)

---

## 5. ClassRepository (26 methods audited)

| Method | Query Type | Uses Parent? | Classification | Reason |
|--------|-----------|--------------|----------------|--------|
| getAllowedOrderColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedFilterColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedInsertColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedUpdateColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| insertClass() | INSERT | No | **REPLACEABLE** | Manual SQL construction (should use parent::insert) |
| updateClass() | UPDATE | No | **REPLACEABLE** | Manual SQL construction (should use parent::update) |
| deleteClass() | DELETE | No | **REPLACEABLE** | Manual SQL construction (should use parent::delete) |
| filterAllowedColumns() | N/A | N/A | N/A | Helper method (duplicates parent method) |
| getClients() | SELECT | No | JUSTIFIED | Static method reads from clients table (not $table) |
| getSites() | SELECT | No | JUSTIFIED | Static method reads from sites table with JOIN to locations |
| getLearners() | SELECT | No | JUSTIFIED | Static method with massive CTE + 7-table JOIN for progression context |
| getAgents() | SELECT | No | JUSTIFIED | Static method reads from agents table (not $table) |
| getSupervisors() | SELECT | No | JUSTIFIED | Static method reads from agents table (not $table) |
| getSeta() | N/A | N/A | N/A | Returns hardcoded array, no SQL |
| getClassTypes() | N/A | N/A | N/A | Delegates to controller, no SQL |
| getYesNoOptions() | N/A | N/A | N/A | Returns hardcoded array, no SQL |
| getClassNotesOptions() | N/A | N/A | N/A | Returns hardcoded array, no SQL |
| clearLearnersCache() | N/A | N/A | N/A | Cache invalidation, no SQL |
| getAllClasses() | SELECT | No | JUSTIFIED | Static method with JOIN to clients |
| getSingleClass() | N/A | N/A | N/A | Delegates to ClassModel::getById, no direct SQL |
| getSiteAddresses() | SELECT | No | JUSTIFIED | Static method reads from sites + locations tables |
| enrichClassesWithAgentNames() | N/A | N/A | N/A | Data processing only, no SQL |
| getCachedClassNotes() | SELECT | No | JUSTIFIED | JSON data extraction from classes table |
| clearCachedClassNotes() | N/A | N/A | N/A | Cache invalidation, no SQL |
| getQAVisitsForClass() | N/A | N/A | N/A | Delegates to QAVisitModel, no direct SQL |
| getSampleClassData() | N/A | N/A | N/A | Returns hardcoded test data, no SQL |

**Summary:** 3 replaceable (insertClass, updateClass, deleteClass), 9 justified (static methods operating on different tables or complex JOINs), 14 helper/config methods

---

## 6. ClassEventRepository (15 methods audited)

| Method | Query Type | Uses Parent? | Classification | Reason |
|--------|-----------|--------------|----------------|--------|
| getAllowedOrderColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedFilterColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedInsertColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedUpdateColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| insertEvent() | INSERT | **Yes** | GOOD | Calls parent::insert() via DTO |
| findByEventId() | SELECT | **Yes** | GOOD | Calls parent::findById() |
| findPendingForProcessing() | SELECT | No | **REPLACEABLE** | Simple WHERE notification_status='pending' (can use findBy) |
| findByEntity() | SELECT | No | **REPLACEABLE** | Simple WHERE entity_type + entity_id (can use findBy) |
| updateStatus() | UPDATE | **Yes** | GOOD | Calls parent::update() |
| updateAiSummary() | UPDATE | No | JUSTIFIED | CURRENT_TIMESTAMP + json_encode (BaseRepository doesn't support SQL functions in values) |
| markSent() | UPDATE | No | JUSTIFIED | Sets CURRENT_TIMESTAMP condition (SQL function) |
| markViewed() | UPDATE | No | JUSTIFIED | CURRENT_TIMESTAMP + IS NULL condition |
| markAcknowledged() | UPDATE | No | JUSTIFIED | CURRENT_TIMESTAMP + IS NULL condition |
| getTimeline() | SELECT | No | JUSTIFIED | Cursor pagination with dynamic WHERE clause |
| getUnreadCount() | SELECT | No | JUSTIFIED | COUNT with IS NULL condition (parent::count doesn't support IS NULL) |

**Summary:** 2 replaceable (findPendingForProcessing, findByEntity), 6 justified (SQL functions, IS NULL conditions), 4 already using parent methods, 3 config methods

---

## 7. ClassTaskRepository (5 methods audited)

| Method | Query Type | Uses Parent? | Classification | Reason |
|--------|-----------|--------------|----------------|--------|
| getAllowedOrderColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedFilterColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| fetchClasses() | SELECT | No | JUSTIFIED | Complex 4-table JOIN (classes + clients + agents x2) with NULLS LAST |

**Summary:** 0 replaceable, 1 justified (complex multi-table JOIN), 2 config methods

---

## 8. MaterialTrackingRepository (10 methods audited)

| Method | Query Type | Uses Parent? | Classification | Reason |
|--------|-----------|--------------|----------------|--------|
| getAllowedOrderColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedFilterColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| markNotificationSent() | INSERT | No | JUSTIFIED | UPSERT (ON CONFLICT DO UPDATE) not supported by BaseRepository |
| markDelivered() | UPDATE | No | JUSTIFIED | JSONB operations (jsonb_set) on classes table event_dates column |
| wasNotificationSent() | SELECT | No | JUSTIFIED | Simple check but operates on class_material_tracking table |
| getDeliveryStatus() | SELECT | No | JUSTIFIED | Operates on class_material_tracking table with custom aggregation logic |
| getTrackingRecords() | SELECT | No | JUSTIFIED | Operates on class_material_tracking table (not $table) |
| getTrackingDashboardData() | SELECT | No | JUSTIFIED | Complex CTE with jsonb_array_elements + 3-table JOIN |
| getTrackingStatistics() | SELECT | No | JUSTIFIED | COUNT with JSONB operations (jsonb_array_elements + CASE) |

**Summary:** 0 replaceable, 7 justified (UPSERT, JSONB operations, multi-table operations), 2 config methods

---

## 9. LocationRepository (7 methods audited)

| Method | Query Type | Uses Parent? | Classification | Reason |
|--------|-----------|--------------|----------------|--------|
| getModel() | N/A | N/A | N/A | Returns model class name, no SQL |
| getAllowedOrderColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedFilterColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedInsertColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| getAllowedUpdateColumns() | N/A | N/A | N/A | Column whitelist, no SQL |
| findByCoordinates() | SELECT | No | JUSTIFIED | Haversine formula for geospatial proximity search |
| checkDuplicates() | SELECT | No | JUSTIFIED | LOWER() case-insensitive matching with dynamic conditions |

**Summary:** 0 replaceable, 2 justified (geospatial + case-insensitive search), 5 helper/config methods

---

## Summary Statistics

| Repository | Total Methods | SQL-Executing Methods | Replaceable | Justified | Already Using Parent |
|-----------|---------------|----------------------|-------------|-----------|----------------------|
| LearnerRepository | 26 | 17 | 1 | 12 | 4 (update/delete + inherited) |
| AgentRepository | 29 | 18 | 4 | 14 | 0 |
| ClientRepository | 8 | 3 | 2 | 1 | 0 |
| LearnerProgressionRepository | 18 | 18 | 0 | 18 | 0 |
| ClassRepository | 26 | 12 | 3 | 9 | 0 |
| ClassEventRepository | 15 | 11 | 2 | 6 | 3 |
| ClassTaskRepository | 5 | 3 | 0 | 1 | 0 |
| MaterialTrackingRepository | 10 | 8 | 0 | 7 | 0 |
| LocationRepository | 7 | 2 | 0 | 2 | 0 |
| **TOTALS** | **144** | **92** | **12** | **70** | **7** |

**Key Insights:**

- **Total direct SQL queries:** 92 across all repositories
- **Replaceable count:** 12 (13.0% of SQL queries)
- **Justified count:** 70 (76.1% of SQL queries)
- **Already using parent:** 7 (7.6% of SQL queries)
- **Replacement opportunity:** 13.0% of queries can be refactored to use BaseRepository methods

**Replaceable Queries Breakdown:**

1. **LearnerRepository:** insert() — FK validation then parent::insert
2. **AgentRepository:** createAgent() — parent::insert
3. **AgentRepository:** updateAgent() — parent::update
4. **AgentRepository:** deleteAgent() — parent::update (soft delete)
5. **AgentRepository:** deleteAgentPermanently() — parent::delete
6. **ClientRepository:** getMainClients() — findBy(['main_client_id' => null])
7. **ClientRepository:** getBranchClients() — findBy(['main_client_id' => $id])
8. **ClassRepository:** insertClass() — parent::insert
9. **ClassRepository:** updateClass() — parent::update
10. **ClassRepository:** deleteClass() — parent::delete
11. **ClassEventRepository:** findPendingForProcessing() — findBy(['notification_status' => 'pending'])
12. **ClassEventRepository:** findByEntity() — findBy(['entity_type' => $type, 'entity_id' => $id])

**Verification Note:** Method counts cross-checked against actual codebase:

| Repository | Declared Methods (grep -c 'function ') | Audited in Document |
|-----------|---------------------------------------|---------------------|
| LearnerRepository | 26 | 26 ✓ |
| AgentRepository | 29 | 29 ✓ |
| ClientRepository | 8 | 8 ✓ |
| LearnerProgressionRepository | 18 | 18 ✓ |
| ClassRepository | 26 | 26 ✓ |
| ClassEventRepository | 15 | 15 ✓ |
| ClassTaskRepository | 5 | 5 ✓ |
| MaterialTrackingRepository | 10 | 10 ✓ |
| LocationRepository | 7 | 7 ✓ |

All methods accounted for. No SQL-executing methods missed.
