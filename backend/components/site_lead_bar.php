<?php
/**
 * Глобальный мобильный бар: Звонок / MAX / Чат поддержки.
 * Подключается из footer на всех страницах.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/contacts.php';
$thc = th_contacts();

/* Логин / регистрация — бар перекрывает submit; не рендерим вовсе */
$_th_page = isset($current_page) ? (string) $current_page : '';
$_th_auth_pages = ['login', 'registration', 'register', 'forgot-password', 'reset-password'];
if (!empty($th_hide_site_lead) || in_array($_th_page, $_th_auth_pages, true)) {
    return;
}

if (!defined('TH_SITE_LEAD_CSS')) {
    define('TH_SITE_LEAD_CSS', true);
    $_th_sl_css = dirname(__DIR__, 2) . '/frontend/css/th-site-lead.css';
    $_th_sl_v = is_file($_th_sl_css) ? (string) filemtime($_th_sl_css) : '1';
    echo '<link rel="stylesheet" href="/frontend/css/th-site-lead.css?v=' . htmlspecialchars($_th_sl_v, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
if (!defined('TH_LEAD_CAPTURE_JS')) {
    define('TH_LEAD_CAPTURE_JS', true);
    $_th_lc = dirname(__DIR__, 2) . '/frontend/js/th-lead-capture.js';
    $_th_lc_v = is_file($_th_lc) ? (string) filemtime($_th_lc) : '1';
    echo '<script src="/frontend/js/th-lead-capture.js?v=' . htmlspecialchars($_th_lc_v, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
}
?>
<nav class="th-site-lead-bar" aria-label="Быстрая связь" data-th-site-lead-bar>
    <a class="th-site-lead-bar__btn th-site-lead-bar__btn--call"
       href="tel:<?php echo htmlspecialchars($thc['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>"
       data-th-track="call_bar">
        <i class="fas fa-phone" aria-hidden="true"></i>
        <span>Позвонить</span>
    </a>
    <a class="th-site-lead-bar__btn th-site-lead-bar__btn--max"
       href="<?php echo htmlspecialchars($thc['max_url'], ENT_QUOTES, 'UTF-8'); ?>"
       target="_blank" rel="noopener noreferrer"
       data-th-track="max_bar">
        <span>MAX</span>
    </a>
    <button type="button" class="th-site-lead-bar__btn th-site-lead-bar__btn--lead"
            data-th-open-support-chat
            data-th-track="chat_bar"
            aria-label="Открыть чат поддержки">
        <i class="fas fa-comments" aria-hidden="true"></i>
        <span>Чат</span>
    </button>
</nav>
<script>
(function () {
  try { document.body.classList.add('has-th-lead-bar'); } catch (e) {}
  var bar = document.querySelector('[data-th-site-lead-bar]');
  if (bar && bar.parentNode !== document.body) {
    try { document.body.appendChild(bar); } catch (eBar) {}
  }
  document.addEventListener('click', function (e) {
    var chatBtn = e.target && e.target.closest ? e.target.closest('[data-th-open-support-chat]') : null;
    if (chatBtn) {
      e.preventDefault();
      if (window.THSupportChat && typeof window.THSupportChat.open === 'function') {
        window.THSupportChat.open();
      } else {
        var toggle = document.getElementById('th-support-chat-toggle');
        if (toggle) toggle.click();
      }
      if (window.THLeadCapture) window.THLeadCapture.reachGoal('chat_bar_click');
      return;
    }
    var el = e.target && e.target.closest ? e.target.closest('[data-th-track]') : null;
    if (!el || !window.THLeadCapture) return;
    var g = el.getAttribute('data-th-track');
    if (g === 'max_bar' || g === 'wa_bar') THLeadCapture.reachGoal('max_click');
  }, true);
})();
</script>
