document.addEventListener('DOMContentLoaded', function () {
    const tabBtnOverview = document.getElementById('tab-btn-overview');
    const tabBtnEdit = document.getElementById('tab-btn-edit');
    const tabBtnMembership = document.getElementById('tab-btn-membership');
    const tabContentOverview = document.getElementById('tab-content-overview');
    const tabContentEdit = document.getElementById('tab-content-edit');
    const tabContentMembership = document.getElementById('tab-content-membership');

    function switchTab(activeTab) {
        if (activeTab === 'overview') {
            if (tabContentOverview) tabContentOverview.style.display = 'block';
            if (tabContentEdit) tabContentEdit.style.display = 'none';
            if (tabContentMembership) tabContentMembership.style.display = 'none';
            if (tabBtnOverview) tabBtnOverview.classList.add('profile-sidebar__nav-link--active');
            if (tabBtnEdit) tabBtnEdit.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnMembership) tabBtnMembership.classList.remove('profile-sidebar__nav-link--active');
        } else if (activeTab === 'membership') {
            if (tabContentOverview) tabContentOverview.style.display = 'none';
            if (tabContentEdit) tabContentEdit.style.display = 'none';
            if (tabContentMembership) tabContentMembership.style.display = 'block';
            if (tabBtnOverview) tabBtnOverview.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnEdit) tabBtnEdit.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnMembership) tabBtnMembership.classList.add('profile-sidebar__nav-link--active');
        } else {
            if (tabContentOverview) tabContentOverview.style.display = 'none';
            if (tabContentEdit) tabContentEdit.style.display = 'block';
            if (tabContentMembership) tabContentMembership.style.display = 'none';
            if (tabBtnOverview) tabBtnOverview.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnEdit) tabBtnEdit.classList.add('profile-sidebar__nav-link--active');
            if (tabBtnMembership) tabBtnMembership.classList.remove('profile-sidebar__nav-link--active');
        }
    }

    if (tabBtnOverview) tabBtnOverview.addEventListener('click', () => switchTab('overview'));
    if (tabBtnEdit) tabBtnEdit.addEventListener('click', () => switchTab('edit'));
    if (tabBtnMembership) tabBtnMembership.addEventListener('click', () => switchTab('membership'));

    // Check query params for active tab on load
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab');
    if (initialTab && ['overview', 'membership', 'edit'].includes(initialTab)) {
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
});
