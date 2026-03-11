# Project

## What This Is

WeCoza Core is a WordPress plugin providing core infrastructure for the WeCoza learning management platform. It manages learners, agents (facilitators), clients, classes, and learning programme (LP) progression tracking against a PostgreSQL database. The system tracks attendance, hours, portfolio uploads, and generates events/tasks with reminders for office staff.

## Core Value

Accurate learner progression tracking through learning programmes — from enrolment through hours logging, assessments, and completion — with event-driven task management so nothing falls through the cracks.

## Current State

- Full CRUD for learners, agents, clients, classes, and locations
- LP progression tracking (WEC-168): hours_trained/present/absent, progress calculation, portfolio upload for completion
- Event/task system: generates tasks from class events, reminders, task completion by office staff
- Classes support `exam_class` (boolean), `exam_type` (varchar), and `exam_learners` (JSONB) fields
- **Exam & Assessment Workflow (M001) — COMPLETE:** Full exam tracking pipeline for exam-track learners (AET, GETC AET, REALLL). 3 mock exams → SBA (marks + scan upload) → final exam (mark + certificate upload). ExamTaskProvider generates virtual tasks on dashboard. LP auto-completes when all 5 steps recorded with certificate. Conditional UI renders exam flow for exam-class learners, POE flow for others. 223 automated checks across 5 test suites. 14 architectural decisions documented.

## Architecture / Key Patterns

- **MVC + Repository pattern** with PSR-4 namespaces
- **PostgreSQL** via singleton `PostgresConnection`
- **Shortcode-driven UI** rendered via `wecoza_view()` / `wecoza_component()`
- **AJAX handlers** with nonce verification via `AjaxSecurity`
- **Event system** dispatches class events, generates tasks, sends notifications
- **Services** encapsulate business logic (ProgressionService, PortfolioUploadService, TaskManager, ExamService, ExamTaskProvider)
- **Virtual task generation** — ExamTaskProvider creates Task objects from DB state without JSONB storage (D007)
- **Constructor injection** with null-coalescing defaults for testability without DI container (D005)
- CSS goes in child theme's `ydcoza-styles.css`, JS in `assets/js/`

## Milestone Sequence

- [x] M001: Exam & Assessment Workflow — Mock exam, SBA, and final exam tracking with event/task integration and LP auto-completion (WEC-186). 4 slices, 223 automated checks, browser-verified. Completed 2026-03-11.
