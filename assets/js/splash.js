// Splash Screen Timer & Automatic Authentication Redirect
document.addEventListener('DOMContentLoaded', () => {
    const splash = document.getElementById('splash-screen');
    
    // Smooth Splash Animation Timer (1.5 Seconds)
    setTimeout(() => {
        if (splash) {
            splash.style.opacity = '0';
            splash.style.visibility = 'hidden';
            
            // Redirect to Home Dashboard / Login
            setTimeout(() => {
                window.location.href = 'home.php';
            }, 300);
        }
    }, 1500);
});

// Register PWA Service Worker for Desktop/Mobile PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('./service-worker.js')
            .then(reg => console.log('XPenz PWA SW Registered!', reg))
            .catch(err => console.error('PWA Registration Error:', err));
    });
}