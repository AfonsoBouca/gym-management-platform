
const viewList = document.getElementById('view-list');
const viewTimetable = document.getElementById('view-timetable');
const toggleBtns = document.querySelectorAll('.view-toggle__btn');
const filterGroups = document.querySelectorAll('.filters__group');
const cards = document.querySelectorAll('.crow');
const switcherBtns = document.querySelectorAll('.class-switcher__btn');
const ttCells = document.querySelectorAll('.timetable__cell');
const bookBtns = document.querySelectorAll('.crow__book-btn');
const selectType = document.getElementById('filter-type');
const selectTrainer = document.getElementById('filter-trainer');

let activeDay = 'all';
let activeTime = 'all';
let activeType = 'all';
let activeTrainer = 'all';

function switchView(view) {
    const isList = view === 'list';

    viewList.classList.toggle('classes-view--hidden', !isList);
    viewTimetable.classList.toggle('classes-view--hidden', isList);

    toggleBtns.forEach(btn => {
        const isActive = btn.dataset.view === view;
        btn.classList.toggle('view-toggle__btn--active', isActive);
        btn.setAttribute('aria-selected', String(isActive));
    });

    const url = new URL(window.location);
    if (isList) {
        url.searchParams.delete('class');
    }
    history.replaceState(null, '', url);
}

toggleBtns.forEach(btn => {
    btn.addEventListener('click', () => switchView(btn.dataset.view));
});


function filterCards() {
    cards.forEach(card => {
        const cardDays = card.dataset.days.split(',');
        const cardTime = card.dataset.time;
        const cardType = card.dataset.className;
        const cardTrainer = card.dataset.trainerId;

        const dayOk = activeDay === 'all' || cardDays.includes(activeDay);
        const timeOk = activeTime === 'all' || cardTime === activeTime;
        const typeOk = activeType === 'all' || cardType === activeType;
        const trainerOk = activeTrainer === 'all' || cardTrainer === activeTrainer;

        card.style.display = (dayOk && timeOk && typeOk && trainerOk) ? '' : 'none';
    });
}

filterGroups.forEach(group => {
    const filterType = group.dataset.filter;
    const btns = group.querySelectorAll('.filter-btn');

    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            btns.forEach(b => b.classList.remove('filter-btn--active'));
            btn.classList.add('filter-btn--active');

            if (filterType === 'day') activeDay = btn.dataset.value;
            if (filterType === 'time') activeTime = btn.dataset.value;

            filterCards();
        });
    });
});

if (selectType) {
    selectType.addEventListener('change', () => {
        activeType = selectType.value;
        filterCards();
    });
}

if (selectTrainer) {
    selectTrainer.addEventListener('change', () => {
        activeTrainer = selectTrainer.value;
        filterCards();
    });
}


function highlightClass(classId) {
    const id = Number(classId);

    switcherBtns.forEach(btn => {
        btn.classList.toggle(
            'class-switcher__btn--active',
            Number(btn.dataset.classId) === id
        );
    });

    ttCells.forEach(cell => {
        const cellClass = Number(cell.dataset.classId);

        if (id === 0) {
            cell.classList.remove('timetable__cell--active', 'timetable__cell--dim');
        } else if (cellClass === id) {
            cell.classList.add('timetable__cell--active');
            cell.classList.remove('timetable__cell--dim');
        } else {
            cell.classList.remove('timetable__cell--active');
            cell.classList.add('timetable__cell--dim');
        }
    });

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

ttCells.forEach(cell => {
    cell.addEventListener('click', () => highlightClass(cell.dataset.classId));
});


bookBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const classId = btn.dataset.classId;
        switchView('timetable');
        highlightClass(classId);
    });
});

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

    const sessionId = btn.dataset.sessionId;
    const isEnrolled = btn.dataset.enrolled === '1';
    const isWaitlisted = btn.dataset.waitlisted === '1';

    let action;
    if (isEnrolled) {
        action = 'cancel';
    } else if (isWaitlisted) {
        action = 'leave_waitlist';
    } else {
        const row = btn.closest('.crow__session-row');
        const spotsEl = row?.querySelector('.crow__spots');
        const isFull = spotsEl && (spotsEl.textContent.trim() === 'Full' || spotsEl.classList.contains('crow__spots--full'));
        action = isFull ? 'waitlist' : 'enroll';
    }

    btn.disabled = true;

    const body = new FormData();
    body.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
    body.append('session_id', sessionId);
    body.append('action', action);

    fetch('/api/enroll.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) {
                if (data.full) {
                    btn.disabled = false;
                    btn.dataset.enrolled = '0';
                    btn.dataset.waitlisted = '0';
                    btn.textContent = 'Join Waitlist';
                    btn.classList.remove('crow__enroll-btn--enrolled', 'crow__enroll-btn--waitlisted');

                    const row = btn.closest('.crow__session-row');
                    const spotsEl = row?.querySelector('.crow__spots');
                    if (spotsEl) {
                        spotsEl.textContent = 'Full';
                        spotsEl.className = 'crow__spots crow__spots--full';
                    }
                    return;
                }
                alert(data.msg);
                btn.disabled = false;
                return;
            }

            const row = btn.closest('.crow__session-row');
            const spotsEl = row?.querySelector('.crow__spots');

            if (data.waitlisted) {
                btn.dataset.enrolled = '0';
                btn.dataset.waitlisted = '1';
                btn.dataset.waitlistPos = data.waitlist_pos;
                btn.textContent = 'Leave Waitlist';
                btn.classList.remove('crow__enroll-btn--enrolled');
                btn.classList.add('crow__enroll-btn--waitlisted');

                if (spotsEl) {
                    spotsEl.textContent = `Waitlisted (#${data.waitlist_pos})`;
                    spotsEl.className = 'crow__spots crow__spots--waitlisted';
                }
                btn.disabled = false;

            } else if (data.enrolled) {
                btn.dataset.enrolled = '1';
                btn.dataset.waitlisted = '0';
                btn.textContent = 'Cancel';
                btn.classList.add('crow__enroll-btn--enrolled');
                btn.classList.remove('crow__enroll-btn--waitlisted');

                if (spotsEl) {
                    spotsEl.textContent = 'Enrolled';
                    spotsEl.className = 'crow__spots';
                }
                btn.disabled = false;

            } else {
                btn.dataset.enrolled = '0';
                btn.dataset.waitlisted = '0';
                btn.classList.remove('crow__enroll-btn--enrolled', 'crow__enroll-btn--waitlisted');

                if (spotsEl && data.capacity != null) {
                    const free = data.capacity - data.count;
                    if (free <= 0) {
                        spotsEl.textContent = 'Full';
                        spotsEl.className = 'crow__spots crow__spots--full';
                        btn.textContent = 'Join Waitlist';
                    } else {
                        spotsEl.textContent = `${free} spot${free === 1 ? '' : 's'} left`;
                        spotsEl.className = 'crow__spots';
                        btn.textContent = 'Enroll';
                    }
                } else {
                    btn.textContent = 'Enroll';
                }
                btn.disabled = false;
            }
        })
        .catch(() => { btn.disabled = false; });
});
