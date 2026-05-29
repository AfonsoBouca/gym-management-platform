document.addEventListener('DOMContentLoaded', function () {
    const tabBtnOverview = document.getElementById('tab-btn-overview');
    const tabBtnEdit = document.getElementById('tab-btn-edit');
    const tabContentOverview = document.getElementById('tab-content-overview');
    const tabContentEdit = document.getElementById('tab-content-edit');

    function switchTab(activeTab) {
        if (activeTab === 'overview') {
            if (tabContentOverview) tabContentOverview.style.display = 'block';
            if (tabContentEdit) tabContentEdit.style.display = 'none';
            if (tabBtnOverview) tabBtnOverview.classList.add('profile-sidebar__nav-link--active');
            if (tabBtnEdit) tabBtnEdit.classList.remove('profile-sidebar__nav-link--active');
        } else {
            if (tabContentOverview) tabContentOverview.style.display = 'none';
            if (tabContentEdit) tabContentEdit.style.display = 'block';
            if (tabBtnOverview) tabBtnOverview.classList.remove('profile-sidebar__nav-link--active');
            if (tabBtnEdit) tabBtnEdit.classList.add('profile-sidebar__nav-link--active');
        }
    }

    if (tabBtnOverview && tabBtnEdit) {
        tabBtnOverview.addEventListener('click', () => switchTab('overview'));
        tabBtnEdit.addEventListener('click', () => switchTab('edit'));
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
});
