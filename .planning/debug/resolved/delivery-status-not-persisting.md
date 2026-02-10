---
status: resolved
trigger: "delivery-status-not-persisting"
created: 2026-02-10T00:00:00Z
updated: 2026-02-10T00:15:00Z
---

## Current Focus

hypothesis: AJAX handler returns success but doesn't update event_dates JSONB column properly
test: Find AJAX handler and trace database update logic
expecting: Handler likely missing event_index parameter support for JSONB path update
next_action: Check debug log and find AJAX handler code

## Symptoms

expected: After marking delivery as delivered and refreshing the page, status should remain COMPLETED
actual: Status shows COMPLETED momentarily (with success toast), but reverts to PENDING after page refresh
errors: No visible errors - the AJAX returns success
reproduction: 1. Go to Material Delivery Tracking page 2. Check the checkbox for a delivery row (e.g. class 10-GETC-SMME4-2025-12-10-15-32) 3. It shows "Materials marked as delivered successfully" and status changes to COMPLETED 4. Refresh the page 5. Status is back to PENDING
started: Known tech debt from v1.3 milestone - "AJAX handler needs event_index parameter support — Mark-as-delivered doesn't update event_dates JSONB yet"

## Eliminated

## Evidence

- timestamp: 2026-02-10T00:05:00Z
  checked: MaterialTrackingController.php handleMarkDelivered()
  found: AJAX handler only accepts class_id parameter, no event_index parameter
  implication: Handler cannot update specific JSONB array element

- timestamp: 2026-02-10T00:06:00Z
  checked: MaterialTrackingRepository.php markDelivered()
  found: Updates class_material_tracking table (lines 87-100), NOT the event_dates JSONB column in classes table
  implication: Wrong table being updated - this is a legacy notification tracking table, not the source of truth

- timestamp: 2026-02-10T00:07:00Z
  checked: MaterialTrackingRepository.php getTrackingDashboardData()
  found: Reads from event_dates JSONB column in classes table (lines 228-289), specifically elem->>'status' field
  implication: Dashboard reads from event_dates JSONB, but markDelivered() writes to class_material_tracking table - data source mismatch!

- timestamp: 2026-02-10T00:08:00Z
  checked: JavaScript in dashboard.php (lines 357-404)
  found: Sends event_index parameter (line 373) but controller doesn't use it
  implication: UI correctly identifies which delivery event, but backend ignores it

## Resolution

root_cause: The markDelivered() method updates the wrong table. Dashboard reads delivery status from classes.event_dates JSONB column, but markDelivered() writes to the legacy class_material_tracking table. The JavaScript sends event_index to identify which delivery event in the JSONB array, but the AJAX handler ignores it and updates the wrong table entirely.

fix: Updated three files to properly handle event_index and update the correct JSONB column:
1. MaterialTrackingController: Added event_index parameter extraction and validation
2. MaterialTrackingDashboardService: Added event_index parameter to markAsDelivered()
3. MaterialTrackingRepository: Completely rewrote markDelivered() to use jsonb_set() to update the specific event in classes.event_dates JSONB array, setting status to 'completed', adding completed_by and completed_at fields

verification: Fix implemented and PHP syntax validated. All three files pass syntax checks. The SQL now properly uses jsonb_set() to update the specific event in the classes.event_dates JSONB array at the correct index, setting status to 'completed', completed_by to current user ID, and completed_at to current timestamp. Ready for user testing.
files_changed:
  - src/Events/Controllers/MaterialTrackingController.php
  - src/Events/Services/MaterialTrackingDashboardService.php
  - src/Events/Repositories/MaterialTrackingRepository.php
