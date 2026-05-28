document.addEventListener('DOMContentLoaded', function () {

    // ── Roster accordion toggle with lazy-load ─────────────────────
    var rosterCache = {};

    document.querySelectorAll('.trainer-roster-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item      = btn.closest('.trainer-roster-item');
            var sessionId = btn.dataset.sessionId;
            var panel     = item.querySelector('.trainer-roster-panel');
            var isOpen    = item.classList.contains('trainer-roster-item--open');

            // close all others
            document.querySelectorAll('.trainer-roster-item--open').forEach(function (open) {
                if (open !== item) open.classList.remove('trainer-roster-item--open');
            });

            if (isOpen) {
                item.classList.remove('trainer-roster-item--open');
                return;
            }

            item.classList.add('trainer-roster-item--open');

            // already loaded?
            if (rosterCache[sessionId]) {
                renderRoster(panel, rosterCache[sessionId]);
                return;
            }

            // show loading
            panel.innerHTML = '<p class="trainer-roster-panel__loading">Loading roster…</p>';

            fetch('/api/trainer/roster.php?session_id=' + encodeURIComponent(sessionId))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        panel.innerHTML = '<p class="trainer-roster-empty">' + escapeHtml(data.msg) + '</p>';
                        return;
                    }
                    rosterCache[sessionId] = data.members;
                    renderRoster(panel, data.members);
                })
                .catch(function () {
                    panel.innerHTML = '<p class="trainer-roster-empty">Failed to load roster.</p>';
                });
        });
    });

    function renderRoster(panel, members) {
        if (!members || members.length === 0) {
            panel.innerHTML = '<p class="trainer-roster-empty">No members enrolled in this session.</p>';
            return;
        }

        var html = '<ul class="trainer-roster-members">';
        members.forEach(function (m) {
            var attendedClass = m.attended == 1
                ? 'trainer-roster-member__status--yes'
                : 'trainer-roster-member__status--no';
            var attendedLabel = m.attended == 1 ? 'Attended' : 'Pending';

            html += '<li class="trainer-roster-member">'
                  + '  <div>'
                  + '    <span class="trainer-roster-member__name">' + escapeHtml(m.name) + '</span>'
                  + '    <span class="trainer-roster-member__user">@' + escapeHtml(m.username) + '</span>'
                  + '  </div>'
                  + '  <div style="display:flex;align-items:center;gap:0.75rem;">'
                  + '    <span class="trainer-roster-member__status ' + attendedClass + '">'
                  +        attendedLabel
                  + '    </span>'
                  + '    <span class="trainer-roster-member__date">' + escapeHtml(m.enrolled_at) + '</span>'
                  + '  </div>'
                  + '</li>';
        });
        html += '</ul>';
        panel.innerHTML = html;
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ── Session status actions (complete / cancel / reactivate) ─────
    document.querySelectorAll('.schedule-action-btn[data-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sessionId = btn.dataset.sessionId;
            var action    = btn.dataset.action;

            btn.disabled = true;

            var body = new FormData();
            body.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
            body.append('session_id', sessionId);
            body.append('action', action);

            fetch('/api/trainer/session.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        alert(data.msg);
                        btn.disabled = false;
                        return;
                    }
                    // update the row
                    var row = document.querySelector('.schedule-row[data-session-id="' + sessionId + '"]');
                    if (row) {
                        row.dataset.status = data.status;
                        // update status badge
                        var badge = row.querySelector('.schedule-status');
                        if (badge) {
                            badge.className = 'schedule-status schedule-status--' + data.status;
                            badge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                        }
                        // swap action buttons
                        var actionsCell = row.querySelector('.schedule-actions');
                        if (actionsCell) {
                            var notesBtn = '<button type="button" class="schedule-action-btn schedule-action-btn--notes" '
                                         + 'data-session-id="' + sessionId + '" title="Edit notes">✎</button>';
                            if (data.status === 'scheduled') {
                                actionsCell.innerHTML =
                                    '<button type="button" class="schedule-action-btn schedule-action-btn--complete" '
                                  + 'data-session-id="' + sessionId + '" data-action="complete" title="Mark as completed">✓</button>'
                                  + '<button type="button" class="schedule-action-btn schedule-action-btn--cancel" '
                                  + 'data-session-id="' + sessionId + '" data-action="cancel" title="Cancel session">✕</button>'
                                  + notesBtn;
                            } else {
                                actionsCell.innerHTML =
                                    '<button type="button" class="schedule-action-btn schedule-action-btn--reactivate" '
                                  + 'data-session-id="' + sessionId + '" data-action="reactivate" title="Reactivate session">↺</button>'
                                  + notesBtn;
                            }
                            // re-bind new buttons
                            actionsCell.querySelectorAll('.schedule-action-btn[data-action]').forEach(function (b) {
                                b.addEventListener('click', arguments.callee);
                            }.bind(null));
                            // re-bind action listeners on new buttons
                            rebindActionButtons(actionsCell);
                            rebindNotesButtons(actionsCell);
                        }
                    }
                })
                .catch(function () {
                    alert('Network error.');
                    btn.disabled = false;
                });
        });
    });

    function rebindActionButtons(container) {
        container.querySelectorAll('.schedule-action-btn[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sessionId = btn.dataset.sessionId;
                var action    = btn.dataset.action;
                btn.disabled = true;

                var body = new FormData();
                body.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
                body.append('session_id', sessionId);
                body.append('action', action);

                fetch('/api/trainer/session.php', { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.ok) { alert(data.msg); btn.disabled = false; return; }
                        location.reload();
                    })
                    .catch(function () { alert('Network error.'); btn.disabled = false; });
            });
        });
    }

    function rebindNotesButtons(container) {
        container.querySelectorAll('.schedule-action-btn--notes').forEach(function (btn) {
            btn.addEventListener('click', function () {
                toggleNotes(btn.dataset.sessionId);
            });
        });
    }

    // ── Notes toggle & save ────────────────────────────────────────
    function toggleNotes(sessionId) {
        var notesRow = document.querySelector('.schedule-notes-row[data-session-id="' + sessionId + '"]');
        if (!notesRow) return;
        var visible = notesRow.style.display !== 'none';
        // close all other notes rows
        document.querySelectorAll('.schedule-notes-row').forEach(function (r) {
            r.style.display = 'none';
        });
        if (!visible) {
            notesRow.style.display = '';
        }
    }

    document.querySelectorAll('.schedule-action-btn--notes').forEach(function (btn) {
        btn.addEventListener('click', function () {
            toggleNotes(btn.dataset.sessionId);
        });
    });

    document.querySelectorAll('.schedule-notes-cancel').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.schedule-notes-row');
            if (row) row.style.display = 'none';
        });
    });

    document.querySelectorAll('.schedule-notes-save').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sessionId = btn.dataset.sessionId;
            var notesRow  = btn.closest('.schedule-notes-row');
            var textarea  = notesRow.querySelector('.schedule-notes-input');
            var notes     = textarea ? textarea.value : '';

            btn.disabled = true;

            var body = new FormData();
            body.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
            body.append('session_id', sessionId);
            body.append('action', 'save_notes');
            body.append('notes', notes);

            fetch('/api/trainer/session.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.disabled = false;
                    if (!data.ok) { alert(data.msg); return; }
                    // brief success feedback
                    btn.textContent = 'Saved ✓';
                    setTimeout(function () {
                        btn.textContent = 'Save Notes';
                        notesRow.style.display = 'none';
                    }, 1200);
                })
                .catch(function () { alert('Network error.'); btn.disabled = false; });
        });
    });
});
