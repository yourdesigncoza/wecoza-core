---
phase: 13-notification-card-agent-name-display-ack
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/Events/Services/NotificationDashboardService.php
  - src/Events/Views/Presenters/AISummaryPresenter.php
  - src/Events/Shortcodes/AISummaryShortcode.php
  - src/Events/Repositories/ClassEventRepository.php
  - views/events/ai-summary/card.php
  - views/events/ai-summary/timeline.php
  - views/events/ai-summary/item.php
  - views/events/ai-summary/main.php
autonomous: false
requirements: [NOTIF-AGENT-NAME, NOTIF-ACK-BADGE, NOTIF-DELETE]

must_haves:
  truths:
    - "Notification cards display agent Name & Surname (e.g. 'Lebogang Van der Merwe') instead of agent ID"
    - "Clicking Acknowledge button changes the NEW badge to a Read badge and disables the button"
    - "Notification header count shows total and read count (e.g. '4 Notifications, 1 Read')"
    - "Each notification card has a Delete button"
    - "Deleting a notification records the WordPress user ID who deleted it"
    - "Deleted notifications disappear from the view"
  artifacts:
    - path: "src/Events/Services/NotificationDashboardService.php"
      provides: "Agent name resolution via JOIN to agents table"
    - path: "views/events/ai-summary/card.php"
      provides: "Agent name display, Acknowledge badge swap, Delete button"
    - path: "views/events/ai-summary/main.php"
      provides: "Updated notification count showing total and read count"
    - path: "src/Events/Shortcodes/AISummaryShortcode.php"
      provides: "AJAX handler for delete notification"
    - path: "src/Events/Repositories/ClassEventRepository.php"
      provides: "Soft-delete method recording deleted_by WP user ID"
  key_links:
    - from: "views/events/ai-summary/card.php"
      to: "src/Events/Views/Presenters/AISummaryPresenter.php"
      via: "agent_name key in summary array"
      pattern: "agent_name"
    - from: "src/Events/Views/Presenters/AISummaryPresenter.php"
      to: "src/Events/Services/NotificationDashboardService.php"
      via: "agent_name passed through from transformForDisplay"
      pattern: "agent_name"
    - from: "AISummaryShortcode.php JS markAsAcknowledged"
      to: "card.php NEW badge"
      via: "DOM manipulation on data-role=status-badge"
      pattern: "status-badge"
    - from: "AISummaryShortcode.php JS deleteNotification"
      to: "ajaxDeleteNotification PHP handler"
      via: "AJAX POST wecoza_delete_notification"
      pattern: "wecoza_delete_notification"
---

<objective>
Enhance the wecoza_insert_update_ai_summary notification cards with three improvements:
1. Display agent Name & Surname instead of raw agent ID
2. Make Acknowledge button fully functional (NEW -> Read badge swap, count updates)
3. Add Delete notification with WordPress user ID tracking

Purpose: Improve notification usability by showing human-readable agent names, providing visual feedback on acknowledgment, and allowing notification cleanup with audit trail.
Output: Updated notification card views, service layer with agent name resolution, delete AJAX handler with soft-delete recording deleted_by user.
</objective>

<execution_context>
@/home/laudes/.claude/get-shit-done/workflows/execute-plan.md
@/home/laudes/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/Events/Shortcodes/AISummaryShortcode.php
@src/Events/Services/NotificationDashboardService.php
@src/Events/Views/Presenters/AISummaryPresenter.php
@src/Events/Repositories/ClassEventRepository.php
@views/events/ai-summary/card.php
@views/events/ai-summary/timeline.php
@views/events/ai-summary/item.php
@views/events/ai-summary/main.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Agent name resolution + acknowledge badge swap + notification counts</name>
  <files>
    src/Events/Services/NotificationDashboardService.php
    src/Events/Views/Presenters/AISummaryPresenter.php
    views/events/ai-summary/card.php
    views/events/ai-summary/timeline.php
    views/events/ai-summary/item.php
    views/events/ai-summary/main.php
    src/Events/Shortcodes/AISummaryShortcode.php
  </files>
  <action>
  **1. Agent name resolution in NotificationDashboardService::transformForDisplay():**

  The `event_data->new_row->class_agent` field holds the agent ID (int). Add agent name lookup:
  - Add a private method `resolveAgentName(int $agentId): string` that queries `SELECT first_name, surname FROM agents WHERE agent_id = :id` using `wecoza_db()->getPdo()`. Return "First Last" string or "Unknown Agent" on failure.
  - In `transformForDisplay()`, extract `$agentId = (int)($newRow['class_agent'] ?? 0)` and call `resolveAgentName($agentId)`.
  - Add `'agent_id' => $agentId` and `'agent_name' => $resolvedName` to the returned array.
  - For efficiency, add a simple `private array $agentNameCache = []` property to cache agent names within a single request (avoid repeated DB queries for same agent_id across multiple notifications).

  **2. Pass agent_name through AISummaryPresenter::presentSingle():**

  In the returned array from `presentSingle()`, add:
  ```php
  'agent_id' => $record['agent_id'] ?? null,
  'agent_name' => esc_html($record['agent_name'] ?? ''),
  ```
  Also add agent_name to the `buildSearchIndex()` call so notifications are searchable by agent name.

  **3. Update card.php to display agent name:**

  In the card header area (after class_subject `<p>` tag, around line 53-59), add a new line:
  ```php
  <?php if (!empty($summary['agent_name'])): ?>
      <p class="mb-0 fs-10 text-body-tertiary">
          <i class="bi bi-person"></i> <?php echo esc_html($summary['agent_name']); ?>
      </p>
  <?php endif; ?>
  ```

  **4. Update card.php acknowledge button to swap NEW badge to Read:**

  Replace the static `<?php if (!$isRead): ?>` NEW badge block (lines 42-47) with a dynamic element that can be updated by JS:
  ```php
  <span data-role="status-badge" data-event-id="<?php echo esc_attr($eventId); ?>">
      <?php if ($isAcknowledged): ?>
          <span class="badge badge-phoenix badge-phoenix-success fs-10">Read</span>
      <?php elseif (!$isRead): ?>
          <span class="badge badge-phoenix badge-phoenix-primary fs-10">NEW</span>
      <?php else: ?>
          <span class="badge badge-phoenix badge-phoenix-info fs-10">Read</span>
      <?php endif; ?>
  </span>
  ```

  **5. Update main.php notification count to show total and read:**

  In the shortcode render method, add `$acknowledgedCount` to the data passed to the template. In `AISummaryShortcode::render()`, after `$unreadCount = $this->service->getUnreadCount()`, add:
  ```php
  $totalCount = count($records);
  $acknowledgedCount = $this->service->getAcknowledgedCount();
  ```

  Add `getAcknowledgedCount()` to `NotificationDashboardService` delegating to repository.
  Add `getAcknowledgedCount()` to `ClassEventRepository`:
  ```sql
  SELECT COUNT(*) FROM class_events WHERE acknowledged_at IS NOT NULL AND deleted_at IS NULL
  ```

  In main.php, update the header count badge area (around line 36-38) to display:
  ```php
  <span class="badge badge-phoenix fs-10 badge-phoenix-primary" data-role="notification-count">
      <?php echo esc_html($totalCount); ?> <?php echo esc_html(_n('Notification', 'Notifications', $totalCount, 'wecoza-events')); ?><?php if ($acknowledgedCount > 0): ?>, <?php echo esc_html($acknowledgedCount); ?> Read<?php endif; ?>
  </span>
  ```

  Pass `$totalCount` and `$acknowledgedCount` to the template renderer data array.

  **6. Update JS in AISummaryShortcode::getAssets() for acknowledge badge swap:**

  In the `markAsAcknowledged()` JS function, after the successful response, update the status badge:
  ```javascript
  var statusBadge = item.querySelector('[data-role="status-badge"]');
  if (statusBadge) {
      statusBadge.innerHTML = '<span class="badge badge-phoenix badge-phoenix-success fs-10">Read</span>';
  }
  ```

  Also update the notification count badge:
  ```javascript
  var countBadge = container.querySelector('[data-role="notification-count"]');
  if (countBadge && data.data.acknowledged_count !== undefined) {
      var total = container.querySelectorAll('[data-role="summary-item"]').length;
      var ackCount = data.data.acknowledged_count;
      var text = total + (total === 1 ? ' Notification' : ' Notifications');
      if (ackCount > 0) text += ', ' + ackCount + ' Read';
      countBadge.textContent = text;
  }
  ```

  In the `ajaxMarkAcknowledged` PHP handler, add `'acknowledged_count' => $this->service->getAcknowledgedCount()` to the success response data.

  **7. Apply same agent name + badge changes to timeline.php and item.php:**

  - timeline.php: Add agent name display after class_subject (same pattern as card.php). Update NEW badge to use `data-role="status-badge"` wrapper.
  - item.php: Add agent name display after class_subject. Update NEW badge to use `data-role="status-badge"` wrapper.
  </action>
  <verify>
  Load the notification shortcode page in browser. Verify:
  1. Each card shows agent name (e.g., "Lebogang Van der Merwe") instead of just class info
  2. Click Acknowledge on a notification - badge changes from "NEW" to "Read"
  3. Header count updates to show "4 Notifications, 1 Read" format
  </verify>
  <done>
  Agent names display on all notification layouts (card, timeline, item). Acknowledge button swaps NEW to Read badge. Header count shows "N Notifications, M Read" format. Agent names are searchable via the filter.
  </done>
</task>

<task type="auto">
  <name>Task 2: Delete notification with WordPress user ID tracking</name>
  <files>
    src/Events/Repositories/ClassEventRepository.php
    src/Events/Services/NotificationDashboardService.php
    src/Events/Shortcodes/AISummaryShortcode.php
    views/events/ai-summary/card.php
    views/events/ai-summary/timeline.php
    views/events/ai-summary/item.php
  </files>
  <action>
  **1. DDL for soft-delete columns (provide SQL for user to run):**

  The class_events table needs two new columns. Provide this SQL for the user to execute:
  ```sql
  ALTER TABLE class_events ADD COLUMN deleted_at TIMESTAMPTZ DEFAULT NULL;
  ALTER TABLE class_events ADD COLUMN deleted_by INTEGER DEFAULT NULL;
  ```

  **2. Add soft-delete method to ClassEventRepository:**

  ```php
  public function softDelete(int $eventId, int $deletedByUserId): bool
  {
      $sql = <<<SQL
  UPDATE class_events
  SET deleted_at = CURRENT_TIMESTAMP,
      deleted_by = :deleted_by
  WHERE event_id = :event_id AND deleted_at IS NULL
  SQL;
      $stmt = $this->db->getPdo()->prepare($sql);
      if (!$stmt) return false;
      $stmt->bindValue(':deleted_by', $deletedByUserId, \PDO::PARAM_INT);
      $stmt->bindValue(':event_id', $eventId, \PDO::PARAM_INT);
      return $stmt->execute() && $stmt->rowCount() > 0;
  }
  ```

  **3. Update getTimeline() and getUnreadCount() and getAcknowledgedCount() in ClassEventRepository:**

  Add `AND deleted_at IS NULL` to the WHERE clause of:
  - `getTimeline()`: Add to WHERE clause (both with and without afterId)
  - `getUnreadCount()`: Add to WHERE clause
  - `getAcknowledgedCount()`: Add to WHERE clause

  For `getTimeline()`, the WHERE clause should become:
  ```php
  $whereClause = 'WHERE deleted_at IS NULL';
  if ($afterId !== null) {
      $whereClause .= ' AND event_id < :after_id';
  }
  ```

  **4. Add deleteNotification() to NotificationDashboardService:**

  ```php
  public function deleteNotification(int $eventId, int $deletedByUserId): bool
  {
      return $this->repository->softDelete($eventId, $deletedByUserId);
  }
  ```

  **5. Register AJAX handler in AISummaryShortcode::register():**

  Add: `add_action('wp_ajax_wecoza_delete_notification', [$instance, 'ajaxDeleteNotification']);`

  **6. Add ajaxDeleteNotification() handler in AISummaryShortcode:**

  ```php
  public function ajaxDeleteNotification(): void
  {
      if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
          wp_send_json_error(['message' => 'Invalid nonce'], 403);
          return;
      }
      if (!current_user_can('read')) {
          wp_send_json_error(['message' => 'Unauthorized'], 403);
          return;
      }
      $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
      if ($eventId === null || $eventId === false || $eventId <= 0) {
          wp_send_json_error(['message' => 'Invalid event ID'], 400);
          return;
      }
      $wpUserId = get_current_user_id();
      $success = $this->service->deleteNotification($eventId, $wpUserId);
      if ($success) {
          wp_send_json_success([
              'message' => 'Notification deleted',
              'event_id' => $eventId,
              'deleted_by' => $wpUserId,
              'unread_count' => $this->service->getUnreadCount(),
          ]);
      } else {
          wp_send_json_error(['message' => 'Failed to delete notification'], 500);
      }
  }
  ```

  **7. Add Delete button to card.php:**

  In the card-footer, next to the Acknowledge button area (around line 123-144), add a Delete button:
  ```php
  <?php if ($eventId): ?>
      <button type="button"
          class="btn btn-sm btn-outline-danger py-0 px-2 fs-10"
          data-role="delete-btn"
          data-event-id="<?php echo esc_attr($eventId); ?>"
          title="Delete notification">
          <i class="bi bi-trash"></i>
      </button>
  <?php endif; ?>
  ```

  **8. Add Delete button to timeline.php and item.php** (same pattern as card.php, placed next to Acknowledge button).

  **9. Add JS deleteNotification function in AISummaryShortcode::getAssets():**

  ```javascript
  function deleteNotification(container, eventId) {
      if (!confirm('Delete this notification?')) return;
      var nonce = container.getAttribute('data-nonce');
      if (!nonce || !eventId) return;
      var formData = new FormData();
      formData.append('action', 'wecoza_delete_notification');
      formData.append('nonce', nonce);
      formData.append('event_id', eventId);
      fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
      })
      .then(function(response) { return response.json(); })
      .then(function(data) {
          if (data.success) {
              var item = container.querySelector('[data-event-id="' + eventId + '"]');
              if (item) {
                  item.style.transition = 'opacity 0.3s';
                  item.style.opacity = '0';
                  setTimeout(function() { item.remove(); }, 300);
              }
              updateUnreadBadge(container, data.data.unread_count);
              // Update total count
              var countBadge = container.querySelector('[data-role="notification-count"]');
              if (countBadge) {
                  var remaining = container.querySelectorAll('[data-role="summary-item"]').length;
                  var ackCount = container.querySelectorAll('.notification-acknowledged').length;
                  var text = remaining + (remaining === 1 ? ' Notification' : ' Notifications');
                  if (ackCount > 0) text += ', ' + ackCount + ' Read';
                  countBadge.textContent = text;
              }
          }
      })
      .catch(function(error) {
          console.error('Failed to delete notification:', error);
      });
  }
  ```

  **10. Wire delete button click in the container event listener (in initFilters):**

  In the existing `container.addEventListener('click', ...)` handler, add before the acknowledge-btn check:
  ```javascript
  if (target.closest('[data-role="delete-btn"]')) {
      var deleteBtn = target.closest('[data-role="delete-btn"]');
      var eventId = deleteBtn.getAttribute('data-event-id');
      if (eventId) {
          deleteNotification(container, eventId);
      }
      return;
  }
  ```
  </action>
  <verify>
  1. User runs the ALTER TABLE SQL to add deleted_at and deleted_by columns
  2. Load notification page - delete buttons visible on each card
  3. Click Delete - confirmation dialog appears, notification fades out on confirm
  4. Verify in database: `SELECT event_id, deleted_at, deleted_by FROM class_events WHERE deleted_at IS NOT NULL` shows the deleted row with WP user ID
  5. Refresh page - deleted notification no longer appears
  </verify>
  <done>
  Delete button on all notification layouts. Soft-delete with deleted_at timestamp and deleted_by WordPress user ID. Deleted notifications excluded from all queries. Notification counts update after deletion.
  </done>
</task>

<task type="checkpoint:human-verify">
  <name>Task 3: Human verification of all notification card enhancements</name>
  <files>views/events/ai-summary/card.php</files>
  <action>
  Human verifies all three enhancements are working correctly on the live shortcode page.

  What was built:
  1. Agent Name and Surname displayed instead of agent ID on all layouts
  2. Acknowledge button swaps "NEW" badge to "Read" badge, count updates to "N Notifications, M Read"
  3. Delete button with soft-delete recording WordPress user ID

  How to verify:
  1. Navigate to the page with [wecoza_insert_update_ai_summary] shortcode
  2. Verify each notification card shows agent name (e.g., "Lebogang Van der Merwe") below the class subject
  3. Click "Acknowledge" on one notification - verify "NEW" badge changes to "Read" badge, button disables
  4. Check header count updates (e.g., "4 Notifications, 1 Read")
  5. Click the trash icon on a notification - confirm dialog appears
  6. Confirm deletion - notification fades out, counts update
  7. Refresh page - deleted notification stays gone, acknowledged notification shows "Read" badge
  8. Check database: SELECT event_id, deleted_at, deleted_by FROM class_events WHERE deleted_at IS NOT NULL

  Resume signal: Type "approved" or describe issues.
  </action>
  <verify>User confirms all three features work correctly in browser</verify>
  <done>All notification card enhancements verified by human: agent names, acknowledge badge swap, delete with audit trail.</done>
</task>

</tasks>

<verification>
- Agent names resolve from agents table via class_agent field in event_data JSONB
- Acknowledge AJAX handler returns acknowledged_count for badge sync
- Delete AJAX handler records get_current_user_id() as deleted_by
- All queries (getTimeline, getUnreadCount, getAcknowledgedCount) filter out deleted_at IS NOT NULL
- All three view layouts (card, timeline, item) have consistent agent name, badge, and delete button rendering
</verification>

<success_criteria>
1. Notification cards show "Lebogang Van der Merwe" (not agent ID 10)
2. Acknowledge button: NEW -> Read badge transition + "N Notifications, M Read" count
3. Delete button: soft-deletes with deleted_by = WP user ID, notification removed from view
4. All three layouts (card, timeline, item) updated consistently
</success_criteria>

<output>
After completion, create `.planning/quick/13-notification-card-agent-name-display-ack/13-SUMMARY.md`
</output>
