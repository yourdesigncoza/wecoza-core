# WEC-186 — Exam & GETC Workflow (Mario's Feedback 2026-03-10)

## Scope Correction

Exams are NOT limited to Level 4 / GETC learners. Any AET level can write exams:
- CLIB to CL4
- NL1 to NL4
- GETC subjects (compulsory — no client choice)
- REALLL (being implemented)

Other levels are optional per client — hence the "exam class" option when creating a class, then selecting which learners write.

**Exam option in WeCoza should only apply to: AET, GETC AET, REALLL**

## Exam Types

### 1. Mock Exams
- Capture percentage achieved
- Attempts matter most — pass/fail is just a readiness indicator
- Office records the marks

### 2. SBA (Site-Based Assessment)
- "SBA" in this industry = Site-Based Assessment (not School-Based)
- Marks are entered and scan uploaded (same workflow as PoE)
- Office marks

### 3. Final Exam
- Managed by an external examination body
- We capture: final mark + upload certificate

## Flow

Mario confirmed the proposed flow is correct. From step 3 onward, all steps should become **events/tasks** so that:
- Reminders are sent
- Office staff must complete the tasks

## Implementation Notes

- Mock exam: simple percentage + attempt counter, no complex grading
- SBA: reuse PoE upload pattern (mark entry + file upload)
- Final exam: mark entry + certificate upload
- Events integration is critical — steps 3+ must generate events with reminders
