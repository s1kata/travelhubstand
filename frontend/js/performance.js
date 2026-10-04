/**
 * Travel Hub - Оптимизация производительности
 * Lazy loading, preloading критических ресурсов, оптимизация загрузки
 */

(function() {
    'use strict';

    // Lazy loading для изображений (fallback для старых браузеров)
    if ('loading' in HTMLImageElement.prototype === false) {
        const images = document.querySelectorAll('img[loading="lazy"]');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src || img.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });

        images.forEach(img => {
            if (img.dataset.src) {
                imageObserver.observe(img);
            }
        });
    }

    // Preload критических ресурсов
    function preloadCriticalResources() {
        const criticalImages = document.querySelectorAll('img[fetchpriority="high"]');
        criticalImages.forEach(img => {
            if (img.src && !img.complete) {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'image';
                link.href = img.src;
                document.head.appendChild(link);
            }
        });
    }

    // Шрифты загружаются через <link href="fonts.googleapis.com/css2?..."> — не дублируем

    // Отложенная загрузка не критических скриптов
    function loadDeferredScripts() {
        const deferredScripts = document.querySelectorAll('script[data-defer]');
        deferredScripts.forEach(script => {
            const newScript = document.createElement('script');
            newScript.src = script.src;
            newScript.async = true;
            script.parentNode.replaceChild(newScript, script);
        });
    }

    // Оптимизация изображений при скролле (не трогаем фото туров и прокси — там только eager)
    function optimizeImageLoading() {
        const images = document.querySelectorAll(
            'img[loading="lazy"]:not(.th-tour-card__carousel-slide):not(.th-tour-card__strip-img):not(.th-tour-card__img):not(.th-detail__gallery-slide)'
        );
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    // Добавляем blur-up эффект
                    if (img.dataset.blur) {
                        img.style.filter = 'blur(5px)';
                        img.style.transition = 'filter 0.3s';
                        img.onload = () => {
                            img.style.filter = 'none';
                        };
                    }
                }
            });
        }, {
            rootMargin: '50px'
        });

        images.forEach(img => imageObserver.observe(img));
    }

    // Инициализация при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            preloadCriticalResources();
            optimizeImageLoading();
        });
    } else {
        preloadCriticalResources();
        optimizeImageLoading();
    }

    // Отложенная загрузка скриптов после полной загрузки страницы
    window.addEventListener('load', () => {
        loadDeferredScripts();
    });

    // Service Worker для кэширования (опционально)
    if ('serviceWorker' in navigator) {
        // Раскомментируйте для включения Service Worker
        // navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    // Отслеживание производительности
    if ('PerformanceObserver' in window) {
        try {
            const perfObserver = new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    // Логирование метрик производительности (можно отправить на сервер)
                    if (entry.entryType === 'largest-contentful-paint') {
                        console.log('LCP:', entry.renderTime || entry.loadTime);
                    }
                    if (entry.entryType === 'first-input') {
                        console.log('FID:', entry.processingStart - entry.startTime);
                    }
                }
            });
            perfObserver.observe({ entryTypes: ['largest-contentful-paint', 'first-input'] });
        } catch (e) {
            // Игнорируем ошибки в старых браузерах
        }
    }
})();
