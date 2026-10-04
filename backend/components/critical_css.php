<?php
/**
 * Критический CSS для первого экрана
 * Встраивается inline в <head> для ускорения отрисовки
 */
?>
<style>
/* Critical CSS - Above the fold styles */
/* Попапы ночей: обязательно скрыты при загрузке, открываются ТОЛЬКО по клику */
#tv-nights-popup,
#country-tv-nights-popup { display: none !important; }
#tv-nights-popup:not(.hidden),
#country-tv-nights-popup:not(.hidden) { display: flex !important; }
:root {
    --font-sans: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --bg-body: #F9FAFB;
    --bg-surface: #ffffff;
    --accent-primary: #1A1A40;
    --text-primary: #111827;
    --text-secondary: #6B7280;
    --border-soft: rgba(17, 24, 39, 0.08);
    --shadow-soft: 0 4px 24px rgba(17, 24, 39, 0.06);
}
body {
    font-family: var(--font-sans);
    background: var(--bg-body);
    min-height: 100vh;
    color: var(--text-primary);
    position: relative;
    overflow-x: hidden;
    margin: 0;
    padding: 0;
    line-height: 1.65;
    -webkit-font-smoothing: antialiased;
}
section {
    position: relative;
    z-index: 10;
}
header {
    position: relative;
    z-index: 1000 !important;
    overflow: visible;
}
/* Бургер-панель: скрыта по умолчанию (офф-скрин справа). При открытии — .translate-x-0 сдвигает на место */
#burger-panel {
    transform: translateX(calc(100% + 1.5rem));
}
#burger-panel.translate-x-0 {
    transform: translateX(0);
}
#burger-overlay {
    pointer-events: none;
    opacity: 0;
}
.heading-font {
    font-family: var(--font-sans);
    font-weight: 600;
}
.th-container {
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 1rem;
}
.flex { display: flex; }
.relative { position: relative; }
.min-h-screen { min-height: 100vh; }
.w-full { width: 100%; }
/* Hero section critical styles */
.home-hero-section {
    min-height: 100vh;
    min-height: 100dvh;
    background-color: #1a2744;
    background-image: linear-gradient(165deg, #243b6e 0%, #1a2744 45%, #121830 100%);
}
.hero-background-img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 0;
    opacity: 0;
    transition: opacity 0.35s ease;
}
.hero-background-img.is-ready,
.home-hero-section.hero-bg-ready .hero-background-img {
    opacity: 1;
}
.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        180deg,
        rgba(0, 0, 0, 0.55) 0%,
        rgba(15, 23, 42, 0.35) 45%,
        rgba(15, 23, 42, 0.55) 100%
    );
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
    z-index: 1;
    pointer-events: none;
}
.hero-content {
    text-shadow: 0 2px 16px rgba(0, 0, 0, 0.55), 0 6px 32px rgba(0, 0, 0, 0.35);
}
/* Loading state */
.loading-placeholder {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}
@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
/* CLS: зарезервировать место для карточек туров до загрузки Tailwind */
.tv-card-img-wrap,
.aspect-\[4\/3\] { aspect-ratio: 4/3; }
</style>
