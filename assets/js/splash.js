window.addEventListener('load', () => {
    const splashContainer = document.getElementById('splash-container');
    const targetPage = splashContainer.getAttribute('data-target');
    const delay = 3000; // 3 seconds delay for splash

    setTimeout(() => {
        splashContainer.style.opacity = '0';
        
        setTimeout(() => {
            window.location.href = targetPage;
        }, 1000);
    }, delay);
});