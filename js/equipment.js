document.addEventListener('DOMContentLoaded', () => {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const sections = document.querySelectorAll('.equipment-group-section');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove active class from all buttons and add to the clicked one
            filterBtns.forEach(b => b.classList.remove('filter-btn--active'));
            btn.classList.add('filter-btn--active');

            const targetGroup = btn.dataset.group;

            sections.forEach(section => {
                const groupName = section.dataset.groupName;
                if (targetGroup === 'all' || groupName === targetGroup) {
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });
        });
    });
});
