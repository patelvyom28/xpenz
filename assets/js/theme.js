// Prevent Theme Flicker on Instant Load
(function () {
    const savedTheme = localStorage.getItem('xpenz_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
})();

// Theme Switcher Logic
document.addEventListener("DOMContentLoaded", function () {
    const themeBtn = document.getElementById('themeToggleBtn');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('xpenz_theme', theme);

        if (themeIcon && themeText) {
            if (theme === 'light') {
                themeIcon.className = 'fas fa-moon text-warning';
                themeText.textContent = 'Dark';
            } else {
                themeIcon.className = 'fas fa-sun text-warning';
                themeText.textContent = 'Light';
            }
        }
    }

    // Initial Sync
    const currentTheme = localStorage.getItem('xpenz_theme') || 'dark';
    applyTheme(currentTheme);

    // Toggle Click Event
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            const activeTheme = document.documentElement.getAttribute('data-theme');
            const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);
        });
    }
});