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
});
