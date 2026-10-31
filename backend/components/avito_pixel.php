<?php
/**
 * Пиксель Avito (ac.avito.ru/tag.js) для всех публичных страниц через footer.
 * Переопределение ID: переменная окружения AVITO_PIXEL_ID (только цифры). По умолчанию 3930704.
 *
 * Загрузка после согласия на cookies — как у Яндекс.Метрики.
 */
declare(strict_types=1);

if (!function_exists('th_avito_pixel_id')) {
    function th_avito_pixel_id(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $raw = trim((string) (getenv('AVITO_PIXEL_ID') ?: ($_ENV['AVITO_PIXEL_ID'] ?? '')));
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        $cached = $digits !== '' ? $digits : '3930704';
        return $cached;
    }
}

if (!function_exists('th_avito_pixel_print_snippet')) {
    function th_avito_pixel_print_snippet(): void
    {
        $id = th_avito_pixel_id();
        if ($id === '' || $id === '0') {
            return;
        }
        $idInt = (int) $id;
        $tagSrc = 'https://ac.avito.ru/tag.js';
        $noscriptImg = 'https://www.avito.st/static/ims/235e1f90-308c-4bf5-91a3-07239fde0f2f_ai_icon_common_12x16.png';
        ?>
<!-- Avito Pixel (loads after cookie consent) -->
<script>
(function () {
    var AVITO_PIXEL_ID = <?php echo $idInt; ?>;
    var TAG_SRC = <?php echo json_encode($tagSrc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;

    function hasCookieAnalyticsConsent() {
        try {
            if (localStorage.getItem('cookie_consent_accepted') === 'accepted') {
                return true;
            }
        } catch (e) { /* private mode */ }
        return document.cookie.split(';').some(function (part) {
            return part.trim().indexOf('cookie_consent_accepted=accepted') === 0;
        });
    }

    function loadAvitoPixel() {
        if (window.__thAvitoPixelLoaded) {
            return;
        }
        window.__thAvitoPixelLoaded = true;
        var _apxl = window._apxl || (window._apxl = []);
        _apxl.push({
            id: AVITO_PIXEL_ID,
            start: (new Date()).getTime()
        });
        _apxl.q = _apxl.q || [];
        _apxl.sendEvent = _apxl.sendEvent || function (args) {
            _apxl.q.push([args, location.href, (new Date()).getTime()]);
        };
        (function (d, w, scriptId) {
            if (d.getElementById(scriptId)) {
                return;
            }
            var ts = d.createElement('script');
            ts.type = 'text/javascript';
            ts.async = true;
            ts.id = scriptId;
            ts.src = TAG_SRC;
            var f = function () {
                var s = d.getElementsByTagName('script')[0];
                if (s && s.parentNode) {
                    s.parentNode.insertBefore(ts, s);
                } else {
                    d.head.appendChild(ts);
                }
            };
            if (w.opera === '[object Opera]') {
                d.addEventListener('DOMContentLoaded', f, false);
            } else {
                f();
            }
        })(document, window, 'apxl-code');
    }

    window.thLoadAvitoPixel = loadAvitoPixel;
    window.addEventListener('th:cookie-consent-accepted', loadAvitoPixel);
    if (hasCookieAnalyticsConsent()) {
        loadAvitoPixel();
    }
})();
</script>
<noscript>
    <div>
        <img src="<?php echo htmlspecialchars($noscriptImg, ENT_QUOTES, 'UTF-8'); ?>"
             style="position:absolute;left:-9999px;"
             alt="Avito Pixel" />
    </div>
</noscript>
<!-- /Avito Pixel -->
<?php
    }
}
