document.addEventListener('DOMContentLoaded', function () {
    const tabBtnOverview = document.getElementById('tab-btn-overview');
    const tabBtnEdit = document.getElementById('tab-btn-edit');
    const tabBtnMembership = document.getElementById('tab-btn-membership');
    const tabContentOverview = document.getElementById('tab-content-overview');
    const tabContentEdit = document.getElementById('tab-content-edit');
    const tabContentMembership = document.getElementById('tab-content-membership');
    const tabBtnProgress = document.getElementById('tab-btn-progress');
    const tabContentProgress = document.getElementById('tab-content-progress');
    const tabBtnBookings = document.getElementById('tab-btn-bookings');
    const tabContentBookings = document.getElementById('tab-content-bookings');

    function switchTab(activeTab) {
        if (activeTab === 'overview') {
            if (tabContentOverview) tabContentOverview.style.display = 'block';
            if (tabContentEdit) tabContentEdit.style.display = 'none';
            if (tabContentMembership) tabContentMembership.style.display = 'none';
            if (tabBtnOverview) tabBtnOverview.classList.add('profile-sidebar__nav-link--active');
            if (tabBtnEdit) tabBtnEdit.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnMembership) tabBtnMembership.classList.remove('profile-sidebar__nav-link--active');
            if (tabContentBookings) tabContentBookings.style.display = 'none';
            if (tabBtnBookings)     tabBtnBookings.classList.remove('profile-sidebar__nav-link--active');
        } else if (activeTab === 'membership') {
            if (tabContentOverview) tabContentOverview.style.display = 'none';
            if (tabContentEdit) tabContentEdit.style.display = 'none';
            if (tabContentMembership) tabContentMembership.style.display = 'block';
            if (tabBtnOverview) tabBtnOverview.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnEdit) tabBtnEdit.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnMembership) tabBtnMembership.classList.add('profile-sidebar__nav-link--active');
            if (tabContentBookings) tabContentBookings.style.display = 'none';
            if (tabBtnBookings)     tabBtnBookings.classList.remove('profile-sidebar__nav-link--active');
        } else if (activeTab === 'progress') {
            if (tabContentOverview)   tabContentOverview.style.display   = 'none';
            if (tabContentEdit)       tabContentEdit.style.display       = 'none';
            if (tabContentMembership) tabContentMembership.style.display = 'none';
            if (tabContentProgress)   tabContentProgress.style.display   = 'block';
            if (tabBtnOverview)       tabBtnOverview.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnEdit)           tabBtnEdit.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnMembership)     tabBtnMembership.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnProgress)       tabBtnProgress.classList.add('profile-sidebar__nav-link--active');
            if (tabContentBookings) tabContentBookings.style.display = 'none';
            if (tabBtnBookings)     tabBtnBookings.classList.remove('profile-sidebar__nav-link--active');
            } else if (activeTab === 'bookings') {
            if (tabContentOverview)   tabContentOverview.style.display   = 'none';
            if (tabContentEdit)       tabContentEdit.style.display       = 'none';
            if (tabContentMembership) tabContentMembership.style.display = 'none';
            if (tabContentProgress)   tabContentProgress.style.display   = 'none';
            if (tabContentBookings)   tabContentBookings.style.display   = 'block';
            if (tabBtnOverview)       tabBtnOverview.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnEdit)           tabBtnEdit.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnMembership)     tabBtnMembership.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnProgress)       tabBtnProgress.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnBookings)       tabBtnBookings.classList.add('profile-sidebar__nav-link--active');
        } else {
            if (tabContentOverview) tabContentOverview.style.display = 'none';
            if (tabContentEdit) tabContentEdit.style.display = 'block';
            if (tabContentMembership) tabContentMembership.style.display = 'none';
            if (tabBtnOverview) tabBtnOverview.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnEdit) tabBtnEdit.classList.add('profile-sidebar__nav-link--active');
            if (tabBtnMembership) tabBtnMembership.classList.remove('profile-sidebar__nav-link--active');
            if (tabContentProgress) tabContentProgress.style.display = 'none';
            if (tabBtnProgress)     tabBtnProgress.classList.remove('profile-sidebar__nav-link--active');
        }
    }

    if (tabBtnOverview) tabBtnOverview.addEventListener('click', () => switchTab('overview'));
    if (tabBtnEdit) tabBtnEdit.addEventListener('click', () => switchTab('edit'));
    if (tabBtnMembership) tabBtnMembership.addEventListener('click', () => switchTab('membership'));
    if (tabBtnProgress) tabBtnProgress.addEventListener('click', () => switchTab('progress'));
    if (tabBtnBookings) tabBtnBookings.addEventListener('click', () => switchTab('bookings'));


    // Check query params for active tab on load
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab');
    if (initialTab && ['overview', 'membership', 'progress', 'bookings', 'edit'].includes(initialTab)) {
        switchTab(initialTab);
    }

    const fileInput = document.getElementById('profile-photo');
    const preview = document.getElementById('photo-preview');

    if (fileInput && preview) {
        fileInput.addEventListener('change', function () {
            const file = fileInput.files?.[0];

            if (!file) {
                preview.textContent = 'No preview available.';
                return;
            }

            if (!file.type.startsWith('image/')) {
                preview.textContent = 'Please choose an image file.';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                preview.innerHTML = '';
                const img = document.createElement('img');
                img.src = event.target?.result;
                img.alt = 'Photo preview';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    }

    function initStars(widget) {
        if (widget.classList.contains('star-rating--readonly')) return;
        const stars = widget.querySelectorAll('.star');
        stars.forEach(star => {
            star.addEventListener('mouseenter', () => {
                const val = +star.dataset.value;
                stars.forEach(s => s.classList.toggle('star--on', +s.dataset.value <= val));
            });
            star.addEventListener('click', () => {
                widget.dataset.current = star.dataset.value;
                stars.forEach(s => s.classList.toggle('star--on', +s.dataset.value <= +star.dataset.value));
            });
        });
        widget.addEventListener('mouseleave', () => {
            const cur = +widget.dataset.current;
            stars.forEach(s => s.classList.toggle('star--on', +s.dataset.value <= cur));
        });
    }

    document.querySelectorAll('.star-rating').forEach(initStars);

    document.querySelectorAll('.profile-review-submit').forEach(btn => {
        btn.addEventListener('click', () => {
            const enrollmentId = btn.dataset.enrollmentId;
            const row    = btn.closest('.profile-review-row');
            const editor = row.querySelector('.profile-review-editor');
            const widget = editor.querySelector('.star-rating');
            const rating = +(widget?.dataset.current ?? 0);
            if (rating < 1) { alert('Please select a rating.'); return; }
            const comment = editor.querySelector('.profile-review-textarea')?.value ?? '';
            btn.disabled = true;

            const body = new FormData();
            body.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
            body.append('enrollment_id', enrollmentId);
            body.append('action', 'save');
            body.append('rating', rating);
            body.append('comment', comment);

            fetch('/api/review.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) { alert(data.msg); btn.disabled = false; return; }
                    removeReviewRow(row);
                })
                .catch(() => { btn.disabled = false; });
        });
    });

    document.querySelectorAll('.profile-review-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const form   = btn.closest('.profile-review-form');
            const display = form.querySelector('.profile-review-display');
            const editor  = form.querySelector('.profile-review-editor');
            display.style.display = 'none';
            editor.style.display = '';
        });
    });

    document.querySelectorAll('.profile-review-cancel-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const form    = btn.closest('.profile-review-form');
            const display = form.querySelector('.profile-review-display');
            const editor  = form.querySelector('.profile-review-editor');
            editor.style.display = 'none';
            display.style.display = '';
        });
    });

    function removeReviewRow(row) {
        const list = row.closest('.profile-review-list');
        row.remove();
        if (list && list.children.length === 0) {
            list.closest('.profile-reviews')?.remove();
        }
    }

    function bindDelete(btn) {
        btn.addEventListener('click', () => {
            const enrollmentId = btn.dataset.enrollmentId;
            const row = btn.closest('.profile-review-row');
            btn.disabled = true;

            const body = new FormData();
            body.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
            body.append('enrollment_id', enrollmentId);
            body.append('action', 'delete');

            fetch('/api/review.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) { btn.disabled = false; return; }
                    removeReviewRow(row);
                })
                .catch(() => { btn.disabled = false; });
        });
    }

    document.querySelectorAll('.profile-review-delete-btn').forEach(bindDelete);

    document.querySelectorAll('.profile-review-dismiss').forEach(btn => {
        btn.addEventListener('click', () => removeReviewRow(btn.closest('.profile-review-row')));
    });

    document.querySelectorAll('.profile-enrollment-cancel').forEach(btn => {
        btn.addEventListener('click', function () {
            const sessionId = btn.dataset.sessionId;

            btn.disabled = true;

            const body = new FormData();
            body.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
            body.append('session_id', sessionId);
            body.append('action', 'cancel');

            fetch('/api/enroll.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) { alert(data.msg); btn.disabled = false; return; }
                    const row = btn.closest('.profile-enrollment-row');
                    row?.remove();
                    const list = document.querySelector('.profile-enrollment-list');
                    if (list && list.children.length === 0) {
                        list.insertAdjacentHTML('afterend', '<p class="profile-enrollments__empty">You\'re not enrolled in any sessions yet. <a href="/classes.php">Browse classes</a></p>');
                        list.remove();
                    }
                })
                .catch(() => { btn.disabled = false; });
        });
    });

    // Membership Plans Subscriptions
    document.querySelectorAll('.subscribe-plan-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const planId = btn.dataset.planId;
            const originalText = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            const body = new FormData();
            body.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
            body.append('plan_id', planId);
            
            fetch('/api/subscribe.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) {
                        alert(data.msg);
                        btn.disabled = false;
                        btn.textContent = originalText;
                        return;
                    }
                    // Redirect directly to the membership tab
                    window.location.href = '/profile.php?tab=membership';
                })
                .catch(() => {
                    alert('An error occurred. Please try again.');
                    btn.disabled = false;
                    btn.textContent = originalText;
                });
        });
    });

    // Cancel Subscription Handler (premium double-click confirmation without native confirm)
    const cancelSubBtn = document.querySelector('.cancel-subscription-btn');
    if (cancelSubBtn) {
        cancelSubBtn.addEventListener('click', () => {
            if (!cancelSubBtn.classList.contains('confirm-state')) {
                cancelSubBtn.classList.add('confirm-state');
                cancelSubBtn.textContent = 'Confirm Cancellation?';
                cancelSubBtn.dataset.originalColor = cancelSubBtn.style.backgroundColor;
                cancelSubBtn.style.backgroundColor = '#b91c1c'; // darker red
                
                // Automatically reset after 3 seconds if not clicked again
                setTimeout(() => {
                    if (cancelSubBtn && cancelSubBtn.classList.contains('confirm-state') && !cancelSubBtn.disabled) {
                        cancelSubBtn.classList.remove('confirm-state');
                        cancelSubBtn.textContent = 'Cancel Subscription';
                        cancelSubBtn.style.backgroundColor = cancelSubBtn.dataset.originalColor || '';
                    }
                }, 3000);
                return;
            }
            
            cancelSubBtn.disabled = true;
            cancelSubBtn.textContent = 'Cancelling...';
            
            const body = new FormData();
            body.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
            body.append('plan_id', '0');
            
            fetch('/api/subscribe.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) {
                        alert(data.msg);
                        cancelSubBtn.disabled = false;
                        cancelSubBtn.classList.remove('confirm-state');
                        cancelSubBtn.textContent = 'Cancel Subscription';
                        cancelSubBtn.style.backgroundColor = cancelSubBtn.dataset.originalColor || '';
                        return;
                    }
                    window.location.href = '/profile.php?tab=membership';
                })
                .catch(() => {
                    alert('An error occurred. Please try again.');
                    cancelSubBtn.disabled = false;
                    cancelSubBtn.classList.remove('confirm-state');
                    cancelSubBtn.textContent = 'Cancel Subscription';
                    cancelSubBtn.style.backgroundColor = cancelSubBtn.dataset.originalColor || '';
                });
        });
    }
    // Booking slot picker 
const trainerSelect  = document.getElementById('trainer-select');
const weekStrip      = document.getElementById('week-strip');
const bookingDays    = document.getElementById('booking-days');
const slotSection    = document.getElementById('slot-section');
const slotGrid       = document.getElementById('slot-grid');
const slotDateLabel  = document.getElementById('slot-date-label');
const bookingSummary = document.getElementById('booking-summary');
const summaryText    = document.getElementById('summary-text');
const bookingSubmit  = document.getElementById('booking-submit');
const hiddenTrainer  = document.getElementById('booking-trainer-id');
const hiddenDate     = document.getElementById('booking-date-val');
const hiddenTime     = document.getElementById('booking-time-val');

const DAY_NAMES = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const DAY_FULL  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

function resetFrom(level) {
    // level: 'trainer' | 'day' | 'slot'
    if (level === 'trainer') {
        weekStrip.style.display  = 'none';
        bookingDays.innerHTML    = '';
        hiddenTrainer.value      = '';
    }
    if (level === 'trainer' || level === 'day') {
        slotSection.style.display = 'none';
        slotGrid.innerHTML        = '';
        hiddenDate.value          = '';
    }
    if (level === 'trainer' || level === 'day' || level === 'slot') {
        bookingSummary.style.display = 'none';
        bookingSubmit.style.display  = 'none';
        hiddenTime.value             = '';
    }
}

function buildWeekStrip(trainerId) {
    resetFrom('trainer');
    hiddenTrainer.value = trainerId;
    bookingDays.innerHTML = '';

    // Build the next 14 days (skip today if past noon, always show today)
    const today = new Date();
    for (let i = 0; i < 14; i++) {
        const d = new Date(today);
        d.setDate(today.getDate() + i);
        const iso  = d.toISOString().slice(0, 10);
        const btn  = document.createElement('button');
        btn.type        = 'button';
        btn.className   = 'booking-day-btn';
        btn.dataset.date = iso;
        btn.innerHTML   = `<strong>${DAY_NAMES[d.getDay()]}</strong><br>${d.getDate()}/${d.getMonth()+1}`;
        btn.addEventListener('click', () => loadSlots(trainerId, iso, btn));
        bookingDays.appendChild(btn);
    }
    weekStrip.style.display = 'block';
}

function loadSlots(trainerId, date, activeDayBtn) {
    resetFrom('day');
    // highlight active day
    document.querySelectorAll('.booking-day-btn').forEach(b => b.classList.remove('is-active'));
    activeDayBtn.classList.add('is-active');

    slotGrid.innerHTML = '<p style="color:var(--text-muted);font-size:0.85rem;">Loading…</p>';
    slotSection.style.display = 'block';

    const d = new Date(date + 'T00:00:00');
    slotDateLabel.textContent = `${DAY_FULL[d.getDay()]}, ${d.getDate()}/${d.getMonth()+1}/${d.getFullYear()}`;

    fetch(`/api/trainer_slots.php?trainer_id=${trainerId}&date=${date}`)
        .then(r => r.json())
        .then(data => {
            slotGrid.innerHTML = '';
            if (!data.ok) {
                slotGrid.innerHTML = `<p style="color:var(--danger)">${data.msg}</p>`;
                return;
            }
            data.slots.forEach(slot => {
                const btn = document.createElement('button');
                btn.type      = 'button';
                btn.textContent = slot.time;
                btn.className = 'booking-slot' + (slot.available ? '' : ' is-booked');
                if (!slot.available) {
                    btn.disabled = true;
                } else {
                    btn.addEventListener('click', () => selectSlot(date, slot.time, btn));
                }
                slotGrid.appendChild(btn);
            });
        })
        .catch(() => {
            slotGrid.innerHTML = '<p style="color:var(--danger)">Failed to load slots.</p>';
        });
}

function selectSlot(date, time, activeSlotBtn) {
    document.querySelectorAll('.booking-slot').forEach(b => b.classList.remove('is-active'));
    activeSlotBtn.classList.add('is-active');

    hiddenDate.value = date;
    hiddenTime.value = time;

    const trainerName = trainerSelect.options[trainerSelect.selectedIndex].text;
    const d = new Date(date + 'T00:00:00');
    summaryText.textContent =
        `${trainerName} · ${DAY_FULL[d.getDay()]} ${d.getDate()}/${d.getMonth()+1}/${d.getFullYear()} at ${time}`;

    bookingSummary.style.display = 'block';
    bookingSubmit.style.display  = 'block';
}

if (trainerSelect) {
    trainerSelect.addEventListener('change', function () {
        if (this.value) {
            buildWeekStrip(this.value);
        } else {
            resetFrom('trainer');
        }
    });
}
});