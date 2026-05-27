/**
 * js/classes.js
 *
 * Client-side logic for the classes page:
 *  1. View toggle  (grid ↔ timetable)
 *  2. Card grid filtering  (day / time)
 *  3. Timetable class-switcher  (highlight one class)
 *  4. Card "View Schedule" → timetable with highlight
 */

/* ── DOM refs ───────────────────────────────────────────────────── */
const viewList      = document.getElementById('view-list');
const viewTimetable = document.getElementById('view-timetable');
const toggleBtns    = document.querySelectorAll('.view-toggle__btn');
const filterGroups  = document.querySelectorAll('.filters__group');
const cards         = document.querySelectorAll('.crow');
const switcherBtns  = document.querySelectorAll('.class-switcher__btn');
const ttCells       = document.querySelectorAll('.timetable__cell');
const bookBtns      = document.querySelectorAll('.crow__book-btn');

/* ── state ──────────────────────────────────────────────────────── */
let activeDay  = 'all';
let activeTime = 'all';

/* ================================================================
   1.  VIEW TOGGLE
   ================================================================ */
function switchView(view) {
    const isList = view === 'list';

    viewList.classList.toggle('classes-view--hidden', !isList);
    viewTimetable.classList.toggle('classes-view--hidden', isList);

    toggleBtns.forEach(btn => {
        const isActive = btn.dataset.view === view;
        btn.classList.toggle('view-toggle__btn--active', isActive);
        btn.setAttribute('aria-selected', String(isActive));
    });

    // update URL without reload
    const url = new URL(window.location);
    if (isList) {
        url.searchParams.delete('class');
    }
    history.replaceState(null, '', url);
}

toggleBtns.forEach(btn => {
    btn.addEventListener('click', () => switchView(btn.dataset.view));
});


/* ================================================================
   2.  CARD GRID FILTERING
   ================================================================ */
function filterCards() {
    cards.forEach(card => {
        const cardDays = card.dataset.days.split(',');
        const cardTime = card.dataset.time;

        const dayOk  = activeDay  === 'all' || cardDays.includes(activeDay);
        const timeOk = activeTime === 'all' || cardTime === activeTime;

        card.style.display = (dayOk && timeOk) ? '' : 'none';
    });
}

filterGroups.forEach(group => {
    const filterType = group.dataset.filter;          // 'day' | 'time'
    const btns       = group.querySelectorAll('.filter-btn');

    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            // activate this button, deactivate siblings
            btns.forEach(b => b.classList.remove('filter-btn--active'));
            btn.classList.add('filter-btn--active');

            if (filterType === 'day')  activeDay  = btn.dataset.value;
            if (filterType === 'time') activeTime = btn.dataset.value;

            filterCards();
        });
    });
});


/* ================================================================
   3.  TIMETABLE CLASS SWITCHER
   ================================================================ */
function highlightClass(classId) {
    const id = Number(classId);

    // update switcher buttons
    switcherBtns.forEach(btn => {
        btn.classList.toggle(
            'class-switcher__btn--active',
            Number(btn.dataset.classId) === id
        );
    });

    // update timetable cells
    ttCells.forEach(cell => {
        const cellClass = Number(cell.dataset.classId);

        if (id === 0) {
            // "All" — show everything equally
            cell.classList.remove('timetable__cell--active', 'timetable__cell--dim');
        } else if (cellClass === id) {
            cell.classList.add('timetable__cell--active');
            cell.classList.remove('timetable__cell--dim');
        } else {
            cell.classList.remove('timetable__cell--active');
            cell.classList.add('timetable__cell--dim');
        }
    });

    // update URL
    const url = new URL(window.location);
    if (id === 0) {
        url.searchParams.delete('class');
    } else {
        url.searchParams.set('class', id);
    }
    history.replaceState(null, '', url);
}

switcherBtns.forEach(btn => {
    btn.addEventListener('click', () => highlightClass(btn.dataset.classId));
});

// clicking a timetable cell also highlights its class
ttCells.forEach(cell => {
    cell.addEventListener('click', () => highlightClass(cell.dataset.classId));
});


/* ================================================================
   4.  CARD "VIEW SCHEDULE" → TIMETABLE
   ================================================================ */
bookBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const classId = btn.dataset.classId;
        switchView('timetable');
        highlightClass(classId);
    });
});

// clicking the card itself (not the button) also opens timetable
cards.forEach(card => {
    card.addEventListener('click', (e) => {
        if (e.target.closest('.crow__book-btn') || e.target.closest('.crow__enroll-btn') || e.target.closest('.crow__login-prompt')) return;
        const classId = card.dataset.classId;
        switchView('timetable');
        highlightClass(classId);
    });
});


document.addEventListener('click', e => {
    const btn = e.target.closest('.crow__enroll-btn');
    if (!btn || btn.disabled) return;
    e.stopPropagation();

    const sessionId  = btn.dataset.sessionId;
    const isEnrolled = btn.dataset.enrolled === '1';
    const action     = isEnrolled ? 'cancel' : 'enroll';

    btn.disabled = true;

    const body = new FormData();
    body.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
    body.append('session_id', sessionId);
    body.append('action', action);

    fetch('/api/enroll.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) {
                alert(data.msg);
                btn.disabled = false;
                return;
            }

            const nowEnrolled = data.enrolled;
            btn.dataset.enrolled = nowEnrolled ? '1' : '0';
            btn.textContent = nowEnrolled ? 'Cancel' : 'Enroll';
            btn.classList.toggle('crow__enroll-btn--enrolled', nowEnrolled);

            const row     = btn.closest('.crow__session-row');
            const spotsEl = row?.querySelector('.crow__spots');
            if (spotsEl && data.capacity != null) {
                const free = data.capacity - data.count;
                if (nowEnrolled) {
                    spotsEl.textContent = 'Enrolled';
                    spotsEl.classList.remove('crow__spots--full');
                } else if (free <= 0) {
                    spotsEl.textContent = 'Full';
                    spotsEl.classList.add('crow__spots--full');
                } else {
                    spotsEl.textContent = `${free} spot${free === 1 ? '' : 's'} left`;
                    spotsEl.classList.remove('crow__spots--full');
                }
            }

            btn.disabled = nowEnrolled ? false : (data.count >= data.capacity);
        })
        .catch(() => { btn.disabled = false; });
});
