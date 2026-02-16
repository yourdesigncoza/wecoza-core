---
phase: 40-return-type-hints-constants
plan: 02
subsystem: Models
tags: [type-safety, php8, ide-support, static-analysis]
dependency_graph:
  requires: [40-01]
  provides: [typed-model-layer]
  affects: [all-models, base-model, agent-model, client-models]
tech_stack:
  added: []
  patterns: [return-type-hints, union-types, nullable-types]
key_files:
  created: []
  modified:
    - src/Agents/Models/AgentModel.php
    - src/Clients/Models/SitesModel.php
    - src/Clients/Models/ClientsModel.php
    - src/Clients/Models/LocationsModel.php
    - src/Clients/Models/ClientCommunicationsModel.php
    - src/Classes/Models/QAVisitModel.php
    - core/Abstract/BaseModel.php
decisions:
  - "Use mixed return type for magic getters (__get) - can return any type or null"
  - "Use static return type for fluent setters (set()) - enables method chaining"
  - "Use void for setters with no return value - explicit about no-return contract"
  - "Use union types (int|false, array|false) for operations that can fail"
  - "Use nullable types (?array, ?string) for optional lookups"
metrics:
  duration_seconds: 435
  tasks_completed: 2
  files_modified: 7
  methods_typed: 73
  commits: 2
  completed_date: 2026-02-16
---

# Phase 40 Plan 02: Model Return Type Hints Summary

**JWT auth with refresh rotation using jose library**

## What Was Done

Added explicit return type hints to all 73 untyped public methods across 7 model classes in the model layer.

### Task 1: AgentModel Type Hints (23 methods)

Added return types to all public methods in AgentModel:

**Data access methods:**
- `load(int $id): bool` - Load agent by ID
- `set_data(array $data): void` - Set agent data
- `get_data(): array` - Get agent data

**Magic methods:**
- `__get(string $key): mixed` - Magic getter (any type or null)
- `__set(string $key, mixed $value): void` - Magic setter
- `__isset(string $key): bool` - Property existence check

**Property methods:**
- `get(string $key, mixed $default = null): mixed` - Generic getter
- `set(string $key, mixed $value): static` - Fluent setter
- `is_modified(?string $key = null): bool` - Check modifications
- `get_modified_fields(): array` - Get modified field names

**Validation:**
- `validate(?array $context = null): bool` - Validate agent data
- `get_errors(): array` - Get validation errors

**Form helpers:**
- `get_form_field(string $form_field_name, mixed $default = null): mixed`
- `set_form_field(string $form_field_name, mixed $value): void`
- `set_form_data(array $form_data): void`
- `get_form_data(): array`

**Domain methods:**
- `get_display_name(): string` - Format full name
- `get_initials(): string` - Generate initials
- `get_preferred_areas(): array` - Get working areas
- `set_preferred_areas(array $areas): void` - Set working areas
- `has_quantum_qualification(?string $type = null): bool` - Check qualifications
- `get_status_label(): string` - Get status label
- `to_json(): string` - JSON serialization

**Commit:** 3d77bb8

### Task 2: Client Models + BaseModel + QAVisitModel (50 methods)

**BaseModel (1 method):**
- `__get(string $name): mixed` - Magic getter with snake_case/camelCase support

**SitesModel (21 methods):**

Cache management:
- `refreshLocationCache(): void` - Rebuild location cache
- `clearLocationCache(): void` - Clear location cache
- `rebuildLocationCache(): array` - Fetch and build location cache
- `refreshHeadSiteCache($clientIds = null): void` - Refresh head site cache
- `clearHeadSiteCache($clientIds = null): void` - Clear head site cache

Site retrieval:
- `getHeadSitesForClients(array $clientIds): array` - Bulk head sites
- `getHeadSite(int $clientId): ?array` - Single head site
- `getSitesByClient(int $clientId): array` - All sites for client
- `getSiteById(int $siteId): ?array` - Site by ID
- `ensureSiteBelongsToClient(int $siteId, int $clientId): bool` - Ownership check

Site operations:
- `saveHeadSite(int $clientId, array $data): int|false` - Save/update head site
- `validateHeadSite(array $data): array` - Validate head site data
- `saveSubSite(int $clientId, int $parentSiteId, array $data, array $options = array()): array|false`
- `validateSubSite(int $clientId, int $parentSiteId, array $data, ?int $expectedClientId = null): array`
- `getHeadSitesForClient(int $clientId): array` - Head sites for dropdown
- `getSubSites(int $parentSiteId): array` - Sub-sites for parent
- `getAllSitesWithHierarchy(int $clientId): array` - Full hierarchy
- `deleteSubSite(int $siteId, int $clientId): bool` - Delete sub-site

Hydration:
- `hydrateClients(array &$clients): void` - Enrich clients with site data

Location access:
- `getLocationHierarchy(bool $useCache = true): array` - Location hierarchy
- `getLocationById(int $locationId): ?array` - Location by ID

**ClientsModel (17 methods):**
- `getAllClients(array $params = []): array` - Query clients with filters
- `getByRegistrationNumber(string $regNr): array|false` - Find by reg number
- `create(array $data): int|false` - Create new client
- `updateById(int $id, array $data): bool` - Update client
- `deleteById(int $id): bool` - Soft delete client
- `count(array $params = []): int` - Count clients
- `getStatistics(): array` - Client statistics
- `getForDropdown(): array` - Dropdown options
- `validate(array $data, ?int $id = null): array` - Validate client data
- `getLocationHierarchy(bool $useCache = true): array` - Location hierarchy proxy
- `getLocationById(int $locationId): ?array` - Location by ID proxy
- `getSitesModel(): SitesModel` - Get sites model instance
- `getCommunicationsModel(): ClientCommunicationsModel` - Get comms model
- `getMainClients(): array` - Get main clients only
- `getSubClients(int $mainClientId): array` - Get sub-clients
- `getAllWithHierarchy(): array` - All clients with hierarchy
- `updateClientHierarchy(int $clientId, ?int $mainClientId = null): bool`

**LocationsModel (5 methods):**
- `validate(array $data, ?int $id = null): array` - Validate location data
- `create(array $data): array|false` - Create location
- `checkDuplicates(string $streetAddress, string $suburb, string $town): array`
- `count(array $params = array()): int` - Count locations
- `updateById(int $id, array $data): array|false` - Update location

**ClientCommunicationsModel (5 methods):**
- `logCommunication(int $clientId, int $siteId, string $type, ?string $subject = null, ?string $content = null, ?int $userId = null): bool`
- `getLatestCommunication(int $clientId): ?array` - Latest single communication
- `getLatestCommunications(array $clientIds): array` - Bulk latest communications
- `getLatestCommunicationType(int $clientId): ?string` - Latest type only
- `getLatestCommunicationTypes(array $clientIds): array` - Bulk types with dates

**QAVisitModel (1 method):**
- `getLatestDocument(): ?string` - Get document path or null

**Commit:** 98b451c

## Type Patterns Used

### Union Types
- `int|false` - Create operations that return ID or fail
- `array|false` - Operations that return data or fail

### Nullable Types
- `?array` - Optional lookups (may return null)
- `?string` - Optional string fields
- `?int` - Optional parameters

### Special Types
- `mixed` - Magic getters (can return any type)
- `static` - Fluent setters (enables method chaining)
- `void` - Setters with no return value

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

### Syntax Check
All 7 files passed `php -l` syntax validation.

### Type Coverage Check
```bash
grep 'public function' src/*/Models/*.php src/Classes/Models/QAVisitModel.php core/Abstract/BaseModel.php | grep -v '): ' | grep -v '__construct'
```
Returns: 0 lines (SUCCESS - all public methods typed)

### Union Type Usage
Correctly applied:
- `int|false` for create operations (ClientsModel::create, LocationsModel::create, SitesModel::saveHeadSite)
- `array|false` for operations that fetch-or-fail (ClientsModel::getByRegistrationNumber, SitesModel::saveSubSite)
- `?array` for nullable lookups (SitesModel::getHeadSite, getLocationById)
- `mixed` for magic getters (AgentModel::__get, BaseModel::__get)

## Impact

**IDE Support:**
- Full autocomplete for all model methods
- Type checking in PhpStorm/VSCode
- Inline documentation shows expected return types

**Static Analysis:**
- PHPStan/Psalm can now validate model usage
- Catch type mismatches at analysis time
- Reduce runtime errors

**Developer Experience:**
- Clear contracts for all model methods
- No need to read docblocks to know return types
- Method chaining works correctly with `static` returns

**Model Layer Coverage:**
- 7 files modified
- 73 methods typed
- 100% public method coverage in modified files

## Files Modified

| File | Methods | Type Patterns |
|------|---------|---------------|
| src/Agents/Models/AgentModel.php | 23 | mixed, static, void, bool, array, string |
| src/Clients/Models/SitesModel.php | 21 | void, array, bool, int\|false, array\|false, ?array |
| src/Clients/Models/ClientsModel.php | 17 | array, bool, int, int\|false, array\|false, ?array |
| src/Clients/Models/LocationsModel.php | 5 | array, int, bool, array\|false |
| src/Clients/Models/ClientCommunicationsModel.php | 5 | bool, array, ?array, ?string |
| src/Classes/Models/QAVisitModel.php | 1 | ?string |
| core/Abstract/BaseModel.php | 1 | mixed |

## Next Steps

Phase 40-03 (if exists) should add return type hints to:
- Repository classes
- Service classes
- Controller classes
- AJAX handlers

## Self-Check: PASSED

### Created Files
None expected - metadata only.

### Modified Files
- [x] src/Agents/Models/AgentModel.php exists
- [x] src/Clients/Models/SitesModel.php exists
- [x] src/Clients/Models/ClientsModel.php exists
- [x] src/Clients/Models/LocationsModel.php exists
- [x] src/Clients/Models/ClientCommunicationsModel.php exists
- [x] src/Classes/Models/QAVisitModel.php exists
- [x] core/Abstract/BaseModel.php exists

### Commits
- [x] 3d77bb8 exists: feat(40-02): add return type hints to AgentModel
- [x] 98b451c exists: feat(40-02): add return type hints to Client models, QAVisitModel, and BaseModel

All files and commits verified. Self-check PASSED.
