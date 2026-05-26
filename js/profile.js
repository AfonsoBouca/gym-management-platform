document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('profile-photo');
    const preview = document.getElementById('photo-preview');

    if (!fileInput || !preview) return;

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
});
