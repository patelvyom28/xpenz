document.addEventListener("DOMContentLoaded", function () {
    // Force Permanent Dark Theme across Bootstrap & custom CSS
    document.documentElement.setAttribute("data-bs-theme", "dark");
    document.documentElement.setAttribute("data-theme", "dark");
    localStorage.setItem("xpenz_theme", "dark");
});