# Project

## What This Is

WeCoza Core is a WordPress plugin providing core infrastructure for the WeCoza learning management platform. It manages learners, agents (facilitators), clients, classes, and learning programme (LP) progression tracking against a PostgreSQL database. The system tracks attendance, hours, portfolio uploads, and generates events/tasks with reminders for office staff.

## Core Value

Accurate learner progression tracking through learning programmes — from enrolment through hours logging, assessments, and completion — with event-driven task management so nothing falls through the cracks.

## Current State

- Full CRUD for learners, agents, clients, classes, and locations
- LP progression tracking (WEC-168): hours_trained/present/absent, progress calculation, portfolio upload for completion
- Event/task system: generates tasks from class events, reminders, task completion by office staff
- Classes already support `exam_class` (boolean), `exam_type` (varchar), and `exam_learners` (JSONB) fields
- **Exam data layer complete (M001/S01):** `learner_exam_results` schema, ExamStep enum, ExamRepository, ExamUploadService, ExamService — all verified with 66 automated checks
- **Exam task integration complete (M001/S02):** ExamTaskProvider generates virtual exam tasks from DB state, TaskManager routes exam complete/reopen through ExamService, ClassTaskPresenter formats exam tasks with no-note UI — 83 automated checks passing. Non-exam flows unchanged.

## Architecture / Key Patterns

- **MVC + Repository pattern** with PSR-4 namespaces
- **PostgreSQL** via singleton `PostgresConnection`
- **Shortcode-driven UI** rendered via `wecoza_view()` / `wecoza_component()`
- **AJAX handlers** with nonce verification via `AjaxSecurity`
- **Event system** dispatches class events, generates tasks, sends notifications
- **Services** encapsulate business logic (ProgressionService, PortfolioUploadService, TaskManager, ExamService, ExamTaskProvider)
- CSS goes in child theme's `ydcoza-styles.css`, JS in `assets/js/`

## Milestone Sequence

- [ ] M001: Exam & Assessment Workflow — Build mock exam, SBA, and final exam tracking with event/task integration (WEC-186) — S01+S02 complete, S03 next
