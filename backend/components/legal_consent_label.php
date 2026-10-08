<?php
/**
 * Чекбоксы согласий для форм (152-ФЗ / 38-ФЗ): ПД отдельно, реклама отдельно, документы — ссылками.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/legal.php';

if (!function_exists('th_legal_consent_checkbox_html')) {
    /** Подпись обязательного чекбокса ПД. Слова «согласие на обработку персональных данных» — ссылка. */
    function th_legal_consent_checkbox_html(): string
    {
        return 'Даю <a href="/frontend/window/consent.php" target="_blank" rel="noopener">согласие на обработку персональных данных</a>';
    }
}

if (!function_exists('th_ad_consent_checkbox_html')) {
    function th_ad_consent_checkbox_html(): string
    {
        $op = th_legal_operator();
        $who = htmlspecialchars($op['advertiser_short'], ENT_QUOTES, 'UTF-8');
        return 'Даю <a href="/frontend/window/advertising.php" target="_blank" rel="noopener">согласие на получение рекламы</a>'
            . ' (SMS, email, звонки, мессенджеры) от ' . $who;
    }
}

if (!function_exists('th_terms_consent_checkbox_html')) {
    function th_terms_consent_checkbox_html(): string
    {
        return 'Принимаю <a href="/frontend/window/terms.php" target="_blank" rel="noopener">Пользовательское соглашение</a>';
    }
}

if (!function_exists('th_legal_docs_links_html')) {
    function th_legal_docs_links_html(): string
    {
        return 'Документы: '
            . '<a href="/frontend/window/privacy.php" target="_blank" rel="noopener">Политика в отношении обработки персональных данных</a>'
            . ' · <a href="/frontend/window/consent.php" target="_blank" rel="noopener">Согласие на обработку ПД</a>'
            . ' · <a href="/frontend/window/advertising.php" target="_blank" rel="noopener">Согласие на рекламу</a>'
            . ' · <a href="/frontend/window/terms.php" target="_blank" rel="noopener">Пользовательское соглашение</a>';
    }
}

if (!function_exists('th_legal_form_consents_html')) {
    /**
     * Блок согласий для любой формы с ПД.
     *
     * @param array{
     *   id_prefix?: string,
     *   include_terms?: bool,
     *   include_ads?: bool,
     *   variant?: string
     * } $opts
     */
    function th_legal_form_consents_html(array $opts = []): string
    {
        $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($opts['id_prefix'] ?? 'th-legal')) ?: 'th-legal';
        $includeTerms = !empty($opts['include_terms']);
        $includeAds = !array_key_exists('include_ads', $opts) || !empty($opts['include_ads']);
        $variant = (string) ($opts['variant'] ?? '');
        $class = 'th-legal-consents';
        if ($variant === 'on-dark') {
            $class .= ' th-legal-consents--on-dark';
        }
        $op = th_legal_operator();
        $version = htmlspecialchars($op['consent_version'], ENT_QUOTES, 'UTF-8');
        $pdId = $prefix . '-agree';
        $adsId = $prefix . '-agree-ads';
        $termsId = $prefix . '-agree-terms';

        $html = '<div class="' . $class . '" data-th-legal-consents="1">';
        $html .= '<input type="hidden" name="consent_version" value="' . $version . '">';
        $html .= '<div class="th-legal-consents__item"><label class="th-legal-consents__row" for="' . htmlspecialchars($pdId, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<input type="checkbox" id="' . htmlspecialchars($pdId, ENT_QUOTES, 'UTF-8') . '" name="agree" value="1" required>';
        $html .= '<span>' . th_legal_consent_checkbox_html() . '</span></label></div>';
        if ($includeAds) {
            $html .= '<div class="th-legal-consents__item"><label class="th-legal-consents__row" for="' . htmlspecialchars($adsId, ENT_QUOTES, 'UTF-8') . '">';
            $html .= '<input type="checkbox" id="' . htmlspecialchars($adsId, ENT_QUOTES, 'UTF-8') . '" name="agree_ads" value="1">';
            $html .= '<span>' . th_ad_consent_checkbox_html() . '</span></label></div>';
            $html .= '<p class="th-legal-consents__hint">Необязательно. Без этой отметки рекламу не отправим.</p>';
        }
        if ($includeTerms) {
            $html .= '<div class="th-legal-consents__item"><label class="th-legal-consents__row" for="' . htmlspecialchars($termsId, ENT_QUOTES, 'UTF-8') . '">';
            $html .= '<input type="checkbox" id="' . htmlspecialchars($termsId, ENT_QUOTES, 'UTF-8') . '" name="agree_terms" value="1" required>';
            $html .= '<span>' . th_terms_consent_checkbox_html() . '</span></label></div>';
        }
        $html .= '<p class="th-legal-consents__docs">' . th_legal_docs_links_html() . '</p>';
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('th_legal_consents_assets')) {
    function th_legal_consents_assets(): void
    {
        if (defined('TH_LEGAL_CONSENTS_ASSETS')) {
            return;
        }
        define('TH_LEGAL_CONSENTS_ASSETS', true);
        $css = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'th-legal-consents.css';
        $v = is_file($css) ? (string) filemtime($css) : '1';
        echo '<link rel="stylesheet" href="/frontend/css/th-legal-consents.css?v=' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        $tpl = th_legal_form_consents_html(['id_prefix' => '__PREFIX__', 'include_terms' => false]);
        $tplTerms = th_legal_form_consents_html(['id_prefix' => '__PREFIX__', 'include_terms' => true]);
        $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        echo '<script>window.THLegal=' . json_encode([
            'formHtmlTpl' => $tpl,
            'formHtmlTplTerms' => $tplTerms,
            'consentVersion' => th_legal_operator()['consent_version'],
        ], $flags) . ';window.THLegal.formHtml=function(p,o){o=o||{};var t=(o.terms?window.THLegal.formHtmlTplTerms:window.THLegal.formHtmlTpl)||\'\';return String(t).split(\'__PREFIX__\').join(p||\'th-legal\');};</script>' . "\n";
    }
}
