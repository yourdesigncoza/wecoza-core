<?php
/**
 * WeCoza Core - Contextual Help Content for Forms
 *
 * Each key maps to a form identifier used by the form-help-panel component.
 * Section types: 'checklist' (prerequisites), 'ordered' (workflow), 'tips' (field hints).
 *
 * @package WeCoza\Core
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Create Class Form
    |--------------------------------------------------------------------------
    */
    'create-class' => [
        'title' => 'Creating a New Class',
        'icon'  => 'bi-mortarboard',
        'sections' => [
            [
                'heading' => 'Before You Begin',
                'type'    => 'checklist',
                'icon'    => 'bi-check2-circle',
                'items'   => [
                    'The <strong>client</strong> must already exist in the system',
                    'At least one <strong>site/location</strong> must be set up for the client',
                    'You need the <strong>order number</strong> if the class should go active immediately',
                    'Know the <strong>class type</strong> (e.g. Skills Programme, Learnership, Short Course)',
                    'Have the <strong>start date</strong> ready (end date is calculated by the system)',
                ],
            ],
            [
                'heading' => 'Step-by-Step Workflow',
                'type'    => 'ordered',
                'icon'    => 'bi-signpost-split',
                'items'   => [
                    'Select the <strong>client</strong> &mdash; this loads their sites automatically',
                    'Pick the <strong>site/location</strong> where training will happen',
                    'Choose the <strong>class type</strong> &mdash; this determines which subjects are available',
                    'Set the <strong>schedule</strong> (start date, training days) &mdash; the end date is calculated automatically',
                    'Assign the <strong>primary agent</strong> (facilitator)',
                    'Optionally add <strong>backup agents</strong>',
                    'Click <strong>Create Draft Class</strong> &mdash; the class is saved as a draft',
                ],
            ],
            [
                'heading' => 'Field Tips',
                'type'    => 'tips',
                'icon'    => 'bi-lightbulb',
                'items'   => [
                    'Class Type'      => 'Determines which subjects/unit standards are available. Cannot be changed after creation.',
                    'Order Number'    => 'Leave blank to create as draft. Add later to activate the class.',
                    'Training Days'   => 'Select all days of the week when training occurs. This affects schedule calculations.',
                    'Primary Agent'   => 'The main facilitator responsible for this class. Must be an active agent.',
                    'Backup Agents'   => 'Optional stand-in facilitators. Add as many as needed.',
                    'Start Date'       => 'Set the training start date. The end date is calculated automatically based on the product duration and training days.',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Update Class Form
    |--------------------------------------------------------------------------
    */
    'update-class' => [
        'title' => 'Editing a Class',
        'icon'  => 'bi-pencil-square',
        'sections' => [
            [
                'heading' => 'What You Can Change',
                'type'    => 'checklist',
                'icon'    => 'bi-check2-circle',
                'items'   => [
                    'Order number, schedule, and training days',
                    'Primary and backup agent assignments',
                    'Class status (draft / active / stopped)',
                    'Subject and unit standard selections',
                    'Notes, comments, and file attachments',
                ],
            ],
            [
                'heading' => 'Update Workflow',
                'type'    => 'ordered',
                'icon'    => 'bi-signpost-split',
                'items'   => [
                    'Review the <strong>class details summary</strong> at the top of the page',
                    'Scroll to the section you need to update',
                    'Make your changes &mdash; required fields are marked with <span class="text-danger">*</span>',
                    'Click <strong>Update Class</strong> to save',
                ],
            ],
            [
                'heading' => 'Field Tips',
                'type'    => 'tips',
                'icon'    => 'bi-lightbulb',
                'items'   => [
                    'Class Status'  => 'Changing to <em>stopped</em> will end all active schedules for this class.',
                    'Order Number'  => 'Adding an order number to a draft class will set it to <em>active</em>.',
                    'Agent Changes' => 'Changing the primary agent does not affect past attendance records.',
                    'QA Visits'     => 'Use the QA tab to log quality assurance visit details and upload reports.',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Progression Admin
    |--------------------------------------------------------------------------
    */
    'progression-admin' => [
        'title' => 'Progression Administration',
        'icon'  => 'bi-mortarboard',
        'sections' => [
            [
                'heading' => 'Purpose',
                'type'    => 'checklist',
                'icon'    => 'bi-check2-circle',
                'items'   => [
                    'Manage all learner Learning Programme (LP) progressions from one place &mdash; start new LPs, update statuses, and track hours',
                    'Filter by client, class, Learning Programme, or status to find exactly what you need',
                    'Use bulk actions to complete multiple LPs at once, saving time on end-of-programme admin',
                    'Review a learner&rsquo;s full hours audit trail before signing off on completion',
                ],
            ],
            [
                'heading' => 'How to Use This Page',
                'type'    => 'ordered',
                'icon'    => 'bi-signpost-split',
                'items'   => [
                    'Use the <strong>filter dropdowns</strong> at the top to narrow the table by client, class, LP, or status',
                    'Click the <strong>Start New LP</strong> button to begin a new Learning Programme for a learner',
                    'Select the <strong>checkboxes</strong> next to learners you want to update, then use <strong>Bulk Complete</strong> to mark them all as done',
                    'Click the <strong>three-dot menu</strong> (&vellip;) on any row to put a learner on hold, resume training, mark complete, or view their hours log',
                    'Open the <strong>Hours Log</strong> from the action menu to see every hours entry, who captured it, and when',
                ],
            ],
            [
                'heading' => 'Table Columns',
                'type'    => 'tips',
                'icon'    => 'bi-lightbulb',
                'items'   => [
                    'Learner'            => 'Name of the learner enrolled on this LP',
                    'Learning Programme' => 'The LP/subject the learner is working through',
                    'Class'              => 'The class this progression is linked to (may be blank if started without a class)',
                    'Status'             => 'Current state of the LP &mdash; In Progress, Completed, or On Hold',
                    'Progress'           => 'Visual bar showing how far through the LP the learner is (hours trained &divide; programme duration) &mdash; use this to spot learners falling behind',
                    'Start Date'         => 'When this LP was started for the learner',
                ],
            ],
            [
                'heading' => 'Statuses & Actions',
                'type'    => 'tips',
                'icon'    => 'bi-info-circle',
                'items'   => [
                    'In Progress' => 'Learner is actively training &mdash; use the action menu to put on hold or mark complete if needed',
                    'Completed'   => 'LP is finished &mdash; this row cannot be selected for bulk actions',
                    'On Hold'     => 'Training paused &mdash; use the action menu to resume when the learner returns',
                ],
                'note' => 'Bulk complete skips portfolio uploads. If a portfolio is required, complete the LP from the learner&rsquo;s own progression page instead.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Learner Progression Report
    |--------------------------------------------------------------------------
    */
    'progression-report' => [
        'title' => 'Learner Progression Report',
        'icon'  => 'bi-graph-up',
        'sections' => [
            [
                'heading' => 'Purpose',
                'type'    => 'checklist',
                'icon'    => 'bi-check2-circle',
                'items'   => [
                    'See the progress of all learners and Learning Programmes (LPs) in one place',
                    'Answers: &ldquo;Are we on track?&rdquo; &mdash; compare completion rate and average progress at a glance',
                    'Quickly find learners who are falling behind or on hold so you can follow up',
                    'Use the data on this page as evidence for client updates and SETA compliance reports',
                ],
            ],
            [
                'heading' => 'What You Can Do',
                'type'    => 'ordered',
                'icon'    => 'bi-signpost-split',
                'items'   => [
                    'Use the <strong>employer dropdown</strong> to filter the table to a single client',
                    'Use the <strong>search bar</strong> to find a specific learner by name or ID number',
                    'Click the <strong>On Hold</strong> pill to see only learners whose training has stalled',
                    'Use the stats bar to prepare monthly or quarterly progress summaries',
                    'Click a <strong>learner row</strong> to expand their timeline and verify hours and LP history before sign-off',
                ],
            ],
            [
                'heading' => 'Summary Stats Explained',
                'type'    => 'tips',
                'icon'    => 'bi-lightbulb',
                'items'   => [
                    'Total Learners'  => 'Number of learners with at least one LP record on this report',
                    'Completion Rate' => 'Percentage of LPs completed &mdash; key metric for SETA reporting and client SLAs',
                    'Avg. Progress'   => 'Average percentage of completion across active LPs &mdash; shows if learners are on schedule',
                    'Active LPs'      => 'LPs currently in progress &mdash; helps you understand the current training workload',
                ],
            ],
            [
                'heading' => 'Statuses & What They Mean for You',
                'type'    => 'tips',
                'icon'    => 'bi-info-circle',
                'items'   => [
                    'In Progress' => 'Learner is actively training &mdash; no action needed unless their progress shows they are falling behind',
                    'Completed'   => 'Learner has met all LP requirements (attendance, portfolio) &mdash; ready for certification',
                    'On Hold'     => 'Training paused &mdash; requires follow-up to determine if learner will resume or exit',
                ],
                'note' => 'These status pills are clickable &mdash; use them to filter the table to only that status.',
            ],
        ],
    ],

];
