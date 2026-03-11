---
phase: 61
slug: all-absent-confirmation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-11
---

# Phase 61 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Manual browser testing (no automated JS test suite) |
| **Config file** | none |
| **Quick run command** | Open single class page, trigger capture modal, set all hours to 0, click Submit |
| **Full suite command** | Same — one scenario covers both ATT-01 and ATT-02 |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run quick manual smoke test
- **After every plan wave:** Full manual test (all 4 scenarios)
- **Before `/gsd:verify-work`:** All manual scenarios must pass
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 61-01-01 | 01 | 1 | ATT-01 | manual | Browser: set all hours to 0, submit | N/A | ⬜ pending |
| 61-01-02 | 01 | 1 | ATT-01 | manual | Browser: set one learner hours > 0, submit | N/A | ⬜ pending |
| 61-01-03 | 01 | 1 | ATT-02 | manual | Browser: cancel dialog, verify button re-enables | N/A | ⬜ pending |
| 61-01-04 | 01 | 1 | ATT-02 | manual | Browser: confirm dialog, verify session saves | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No new test framework needed — this phase modifies a single existing JS function.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Dialog appears when all learners have 0 hours present | ATT-01 | Browser UI interaction (window.confirm) | Set all hours-present inputs to 0, click Submit — confirm dialog appears |
| Dialog does NOT appear with mixed hours | ATT-01 | Negative test requires visual confirmation | Set one learner hours to 1, others to 0, click Submit — no dialog |
| Cancel re-enables button, no AJAX | ATT-02 | Network + UI state verification | Click Cancel — button re-enables, no network request (DevTools) |
| Confirm proceeds to submit | ATT-02 | Full round-trip verification | Click OK — session saved as usual |

---

## Validation Sign-Off

- [ ] All tasks have manual verification steps documented
- [ ] Sampling continuity: single-task phase, no gaps possible
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
