/**
 * Entity History Module
 *
 * Loads and renders entity relationship history via AJAX.
 * Used on single-class and single-agent display pages.
 *
 * Expects WeCozaEntityHistory localized object with:
 *   - ajaxUrl: admin-ajax.php URL
 *   - historyNonce: nonce for wecoza_history_nonce
 *   - entityType: 'class' | 'agent' | 'learner' | 'client'
 *   - entityId: integer ID
 *
 * @package WeCoza
 * @since 1.1.0
 */

(function ($) {
    'use strict';

    if (typeof WeCozaEntityHistory === 'undefined') {
        return;
    }

    const config = WeCozaEntityHistory;
    let loaded = false;

    /**
     * Load history data via AJAX and render into the container.
     */
    function loadHistory() {
        if (loaded) return;
        loaded = true;

        const $container = $('#entity-history-content');
        if (!$container.length) return;

        $container.html(
            '<div class="text-center py-3">' +
            '<div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>' +
            '<span class="text-muted">Loading history...</span></div>'
        );

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_get_entity_history',
                nonce: config.historyNonce,
                entity_type: config.entityType,
                entity_id: config.entityId
            },
            success: function (response) {
                if (response.success && response.data && response.data.timeline) {
                    renderTimeline(response.data.timeline, config.entityType, $container);
                } else {
                    $container.html(
                        '<div class="alert alert-warning mb-0">' +
                        '<i class="bi bi-info-circle me-1"></i> No history data available.</div>'
                    );
                }
            },
            error: function () {
                $container.html(
                    '<div class="alert alert-danger mb-0">' +
                    '<i class="bi bi-exclamation-triangle me-1"></i> Failed to load history.</div>'
                );
            }
        });
    }

    /**
     * Render timeline data into tables based on entity type.
     */
    function renderTimeline(timeline, entityType, $container) {
        let html = '';

        switch (entityType) {
            case 'class':
                html = renderClassTimeline(timeline);
                break;
            case 'agent':
                html = renderAgentTimeline(timeline);
                break;
            case 'learner':
                html = renderLearnerTimeline(timeline);
                break;
            case 'client':
                html = renderClientTimeline(timeline);
                break;
        }

        if (!html) {
            html = '<div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i> No history records found.</div>';
        }

        $container.html(html);
    }

    // ─── Class Timeline ──────────────────────────────────────

    function renderClassTimeline(t) {
        let sections = [];

        // Agent assignments
        if (t.agent_assignments && t.agent_assignments.length) {
            sections.push(buildSection('Agent Assignments', 'bi-person-badge',
                buildTable(['Agent ID', 'Type', 'Class'], t.agent_assignments.map(function(a) {
                    return [a.agent_id || '—', a.assignment_type || '—', '#' + (a.class_id || '—')];
                }))
            ));
        }

        // Learner assignments
        if (t.learner_assignments && t.learner_assignments.length) {
            sections.push(buildSection('Learner Enrollments', 'bi-people',
                buildTable(['Learner', 'Status', 'Level'], t.learner_assignments.map(function(l) {
                    return [l.name || ('ID ' + (l.id || '—')), l.status || '—', l.level || '—'];
                }))
            ));
        }

        // Status changes
        if (t.status_changes && t.status_changes.length) {
            sections.push(buildSection('Status Changes', 'bi-arrow-left-right',
                buildTable(['From', 'To', 'Date', 'Reason'], t.status_changes.map(function(s) {
                    return [
                        statusBadge(s.old_status),
                        statusBadge(s.new_status),
                        formatDate(s.changed_at),
                        s.reason || '—'
                    ];
                }))
            ));
        }

        // Stop/restart dates
        if (t.stop_restart_dates && t.stop_restart_dates.length) {
            sections.push(buildSection('Stop/Restart Periods', 'bi-pause-circle',
                buildTable(['Stop Date', 'Restart Date', 'Reason'], t.stop_restart_dates.map(function(sr) {
                    return [formatDate(sr.stopDate || sr.stop_date), formatDate(sr.restartDate || sr.restart_date) || 'Ongoing', sr.reason || '—'];
                }))
            ));
        }

        // QA visits
        if (t.qa_visits && t.qa_visits.length) {
            sections.push(buildSection('QA Visits', 'bi-clipboard-check',
                buildTable(['Date', 'Type', 'Officer'], t.qa_visits.map(function(q) {
                    return [formatDate(q.visit_date), q.visit_type || '—', q.officer_name || '—'];
                }))
            ));
        }

        // Events
        if (t.events && t.events.length) {
            sections.push(buildSection('Events', 'bi-calendar-event',
                buildTable(['Level', 'Action', 'Date'], t.events.map(function(e) {
                    return [e.level || '—', e.action || '—', formatDate(e.created_at)];
                }))
            ));
        }

        // Notes
        if (t.notes && t.notes.length) {
            sections.push(buildSection('Notes', 'bi-sticky',
                buildTable(['Date', 'Note'], t.notes.map(function(n) {
                    return [formatDate(n.date || n.created_at), truncate(n.note || n.text || '—', 120)];
                }))
            ));
        }

        return sections.join('') || '';
    }

    // ─── Agent Timeline ──────────────────────────────────────

    function renderAgentTimeline(t) {
        let sections = [];

        // Primary classes
        if (t.primary_classes && t.primary_classes.length) {
            sections.push(buildSection('Primary Classes', 'bi-journal-bookmark',
                buildTable(['Class ID', 'Client', 'Subject', 'Status'], t.primary_classes.map(function(c) {
                    return ['#' + (c.class_id || '—'), c.client_id || '—', c.class_type || '—', statusBadge(c.class_status)];
                }))
            ));
        }

        // Backup classes
        if (t.backup_classes && t.backup_classes.length) {
            sections.push(buildSection('Backup Classes', 'bi-journal',
                buildTable(['Class ID', 'Client', 'Subject'], t.backup_classes.map(function(c) {
                    return ['#' + (c.class_id || '—'), c.client_id || '—', c.class_type || '—'];
                }))
            ));
        }

        // Subjects
        if (t.subjects && t.subjects.length) {
            sections.push(buildSection('Subjects Facilitated', 'bi-book',
                buildTable(['Subject', 'Classes', 'First', 'Last'], t.subjects.map(function(s) {
                    return [s.class_type || '—', s.class_count || 0, formatDate(s.first_facilitated), formatDate(s.last_facilitated)];
                }))
            ));
        }

        // QA visits
        if (t.qa_visits && t.qa_visits.length) {
            sections.push(buildSection('QA Visits', 'bi-clipboard-check',
                buildTable(['Date', 'Type', 'Class ID'], t.qa_visits.map(function(q) {
                    return [formatDate(q.visit_date), q.visit_type || '—', '#' + (q.class_id || '—')];
                }))
            ));
        }

        // Notes
        if (t.notes && t.notes.length) {
            sections.push(buildSection('Notes', 'bi-sticky',
                buildTable(['Date', 'Note'], t.notes.map(function(n) {
                    return [formatDate(n.created_at), truncate(n.note_content || '—', 120)];
                }))
            ));
        }

        // Absences
        if (t.absences && t.absences.length) {
            sections.push(buildSection('Absences', 'bi-calendar-x',
                buildTable(['Start', 'End', 'Reason'], t.absences.map(function(a) {
                    return [formatDate(a.start_date), formatDate(a.end_date), a.reason || '—'];
                }))
            ));
        }

        // Clients
        if (t.clients && t.clients.length) {
            sections.push(buildSection('Associated Clients', 'bi-building',
                buildTable(['Client ID'], t.clients.map(function(c) {
                    return ['#' + (c.client_id || '—')];
                }))
            ));
        }

        return sections.join('') || '';
    }

    // ─── Learner Timeline ────────────────────────────────────

    function renderLearnerTimeline(t) {
        let sections = [];

        if (t.class_enrollments && t.class_enrollments.length) {
            sections.push(buildSection('Class Enrollments', 'bi-journal-bookmark',
                buildTable(['Class ID', 'Subject', 'Status', 'Level'], t.class_enrollments.map(function(e) {
                    return ['#' + (e.class_id || '—'), e.class_type || '—', e.status || '—', e.level || '—'];
                }))
            ));
        }

        if (t.hours_logged && t.hours_logged.length) {
            sections.push(buildSection('Hours Logged', 'bi-clock',
                buildTable(['Class ID', 'Trained', 'Present', 'Absent'], t.hours_logged.map(function(h) {
                    return ['#' + (h.class_id || '—'), h.hours_trained || 0, h.hours_present || 0, h.hours_absent || 0];
                }))
            ));
        }

        if (t.portfolios && t.portfolios.length) {
            sections.push(buildSection('Portfolios', 'bi-folder',
                buildTable(['File', 'Uploaded'], t.portfolios.map(function(p) {
                    return [p.file_name || '—', formatDate(p.uploaded_at)];
                }))
            ));
        }

        if (t.progression_dates && t.progression_dates.length) {
            sections.push(buildSection('LP Progression', 'bi-graph-up',
                buildTable(['Class', 'Status', 'Started', 'Completed'], t.progression_dates.map(function(p) {
                    return ['#' + (p.class_id || '—'), p.status || '—', formatDate(p.started_at), formatDate(p.completed_at) || '—'];
                }))
            ));
        }

        if (t.clients && t.clients.length) {
            sections.push(buildSection('Associated Clients', 'bi-building',
                buildTable(['Client ID'], t.clients.map(function(c) {
                    return ['#' + (c.client_id || '—')];
                }))
            ));
        }

        return sections.join('') || '';
    }

    // ─── Client Timeline ─────────────────────────────────────

    function renderClientTimeline(t) {
        let sections = [];

        if (t.classes && t.classes.length) {
            sections.push(buildSection('Classes', 'bi-journal-bookmark',
                buildTable(['Class ID', 'Subject', 'Agent', 'Status'], t.classes.map(function(c) {
                    return ['#' + (c.class_id || '—'), c.class_type || '—', c.class_agent || '—', statusBadge(c.class_status)];
                }))
            ));
        }

        if (t.locations && t.locations.length) {
            sections.push(buildSection('Locations', 'bi-geo-alt',
                buildTable(['Name', 'City', 'Province'], t.locations.map(function(l) {
                    return [l.location_name || '—', l.city || '—', l.province || '—'];
                }))
            ));
        }

        if (t.agents && t.agents.length) {
            sections.push(buildSection('Agents', 'bi-person-badge',
                buildTable(['Agent ID', 'Via Class'], t.agents.map(function(a) {
                    return ['#' + (a.agent_id || '—'), '#' + (a.class_id || '—')];
                }))
            ));
        }

        if (t.learners && t.learners.length) {
            sections.push(buildSection('Learners', 'bi-people',
                buildTable(['Learner', 'Via Class'], t.learners.map(function(l) {
                    var name = l.name || ('ID ' + (l.learner_id || '—'));
                    return [name, '#' + (l.class_id || '—')];
                }))
            ));
        }

        return sections.join('') || '';
    }

    // ─── Helpers ─────────────────────────────────────────────

    function buildSection(title, icon, bodyHtml) {
        return '<div class="mb-3">' +
            '<h6 class="fw-bold text-body-secondary mb-2"><i class="bi ' + icon + ' me-2"></i>' + escHtml(title) + '</h6>' +
            bodyHtml +
            '</div>';
    }

    function buildTable(headers, rows) {
        var html = '<div class="table-responsive"><table class="table table-sm table-hover fs-9 mb-0"><thead class="table-light"><tr>';
        for (var i = 0; i < headers.length; i++) {
            html += '<th>' + escHtml(headers[i]) + '</th>';
        }
        html += '</tr></thead><tbody>';
        for (var r = 0; r < rows.length; r++) {
            html += '<tr>';
            for (var c = 0; c < rows[r].length; c++) {
                html += '<td>' + (rows[r][c] || '—') + '</td>';
            }
            html += '</tr>';
        }
        html += '</tbody></table></div>';
        return html;
    }

    function statusBadge(status) {
        if (!status) return '—';
        var cls = 'secondary';
        if (status === 'active') cls = 'success';
        else if (status === 'stopped' || status === 'inactive') cls = 'danger';
        else if (status === 'draft') cls = 'warning';
        else if (status === 'in_progress') cls = 'primary';
        else if (status === 'completed') cls = 'success';
        return '<span class="badge bg-' + cls + '">' + escHtml(status) + '</span>';
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        try {
            var d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleDateString('en-ZA', { year: 'numeric', month: 'short', day: 'numeric' });
        } catch (e) {
            return dateStr;
        }
    }

    function truncate(str, max) {
        if (!str) return '—';
        return str.length > max ? str.substring(0, max) + '…' : str;
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ─── Init ────────────────────────────────────────────────

    $(function () {
        // Lazy load: trigger on accordion expand
        var $collapse = $('#entityHistoryCollapse');
        if ($collapse.length) {
            $collapse.on('show.bs.collapse', function () {
                loadHistory();
            });
        } else {
            // Fallback: load immediately if no accordion wrapper
            loadHistory();
        }
    });

})(jQuery);
