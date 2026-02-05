---
phase: quick
plan: 003
type: execute
wave: 1
depends_on: []
files_modified:
  - views/events/event-tasks/main.php
autonomous: true

must_haves:
  truths:
    - "Edit Class button visible in Actions column for each class row"
    - "Clicking Edit Class navigates to class edit page with correct class_id"
  artifacts:
    - path: "views/events/event-tasks/main.php"
      provides: "Edit Class button in Actions column"
      contains: "mode=update&class_id"
  key_links:
    - from: "views/events/event-tasks/main.php"
      to: "/wecoza/app/new-class/"
      via: "href attribute with mode=update query param"
      pattern: "mode=update&class_id"
---

<objective>
Add Edit Class button to the Actions column in the Events/Tasks view

Purpose: Allow quick navigation to edit a class directly from the tasks overview
Output: Pencil icon button linking to /wecoza/app/new-class/?mode=update&class_id={id}
</objective>

<execution_context>
@/home/laudes/.claude/get-shit-done/workflows/execute-plan.md
</execution_context>

<context>
@views/events/event-tasks/main.php (target file - Actions column at line 181-192)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add Edit Class link to Actions column</name>
  <files>views/events/event-tasks/main.php</files>
  <action>
In the Actions column cell (line 181-192), add an Edit Class link BEFORE the existing toggle button.

Current structure:
```php
<td class="text-end pe-4">
    <button type="button" class="btn btn-link btn-sm px-1 ..." ...>
        <i class="bi bi-eye"></i>
    </button>
</td>
```

New structure:
```php
<td class="text-end pe-4">
    <a
        href="/wecoza/app/new-class/?mode=update&class_id=<?php echo esc_attr((string) $class['id']); ?>"
        class="btn btn-link btn-sm px-1 text-decoration-none"
        title="<?php echo esc_attr__('Edit Class', 'wecoza-events'); ?>"
    >
        <span class="visually-hidden"><?php echo esc_html__('Edit Class', 'wecoza-events'); ?></span>
        <i class="bi bi-pencil-square"></i>
    </a>
    <button type="button" class="btn btn-link btn-sm px-1 ..." ...>
        <i class="bi bi-eye"></i>
    </button>
</td>
```

Use `bi-pencil-square` icon (Bootstrap Icons) for edit action.
Include accessibility: title attribute, visually-hidden span for screen readers.
Match existing button styling (btn-link btn-sm px-1 text-decoration-none).
  </action>
  <verify>
1. Open the Events/Tasks view page
2. Each class row should show two icons in Actions column: pencil (edit) and eye (toggle)
3. Click pencil icon - should navigate to /wecoza/app/new-class/?mode=update&class_id={id}
  </verify>
  <done>Edit Class button appears in Actions column and links to correct edit URL</done>
</task>

</tasks>

<verification>
- Load Events/Tasks view in browser
- Verify pencil icon appears before eye icon in Actions column
- Click pencil icon on any class row
- Confirm URL is /wecoza/app/new-class/?mode=update&class_id={correct_id}
- Confirm class edit form loads with correct data
</verification>

<success_criteria>
- Edit Class pencil icon visible in Actions column for all class rows
- Link URL correctly includes mode=update and class_id parameters
- Existing toggle button (eye icon) still functions correctly
- Consistent styling with existing action buttons
</success_criteria>

<output>
After completion, create `.planning/quick/003-add-edit-class-button-to-actions-column-/003-SUMMARY.md`
</output>
