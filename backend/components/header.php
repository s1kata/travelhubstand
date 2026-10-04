<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = $current_page ?? '';
$more_menu_active = in_array($current_page, [
    'about', 'services', 'tour-calendar', 'video-tutorials', 'contacts',
    'privacy', 'terms', 'vip-hotels', 'banks_rekvesit',
], true);
$hotels_nav_active = in_array($current_page, ['popular-hotels', 'vip-hotels'], true);
$isLoggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
$normalizedRole = strtolower(trim((string)($_SESSION['user_role'] ?? '')));
$isAdmin = ($normalizedRole === 'admin');
$isManager = !empty($_SESSION['user_is_manager']) || ($normalizedRole === 'manager');
$canOpenManagerWindow = $isAdmin || $isManager;
$userName = trim((string)($_SESSION['user_name'] ?? ''));
require_once __DIR__ . '/tourvisor_proxy_url.php';
require_once dirname(__DIR__) . '/config/departure_defaults.php';
require_once __DIR__ . '/security_helper.php';
$th_tv_api_base = get_tourvisor_proxy_base_url();
$th_tv_image_proxy_base = get_tourvisor_image_proxy_base_url();
$th_departure_id = th_departure_default_id();
$th_departure_name = th_departure_default_name();
$th_csrf_token = security_csrf_token();
?>
<?php if (!defined('TRAVELHUB_HEADER_FALLBACK_STYLES')): ?>
<?php define('TRAVELHUB_HEADER_FALLBACK_STYLES', true); ?>
<?php /* v2: тема и микровзаимодействия на всех страницах (header подключается везде) */ ?>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/frontend/css/v2-theme.css?v=5">
<script src="/frontend/js/v2-theme.js?v=1" defer></script>
<style>
    /* Fallback styles: header works even without design-system.css */
    #site-header.header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 0;
        padding: 10px clamp(16px, 3vw, 40px);
        min-height: 56px;
        box-sizing: border-box;
        border-bottom: 1px solid transparent;
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    #site-header .header__logo {
        color: #ffffff;
        text-decoration: none;
        font-size: 24px;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }

    #site-header .header__nav {
        display: flex;
        gap: clamp(12px, 1.8vw, 24px);
        align-items: center;
        justify-content: flex-start;
        flex: 0 1 auto;
        margin-left: clamp(16px, 2.5vw, 36px);
    }

    #site-header .header__nav a {
        color: #ffffff;
        text-decoration: none;
        font-weight: 500;
        opacity: 1;
        white-space: nowrap;
    }

    #site-header .header__nav a.is-active,
    #site-header .header__nav a:hover {
        color: #ffffff;
        text-decoration: underline;
        text-underline-offset: 4px;
    }

    #site-header .header__more {
        position: relative;
    }

    #site-header .header__more-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 0;
        padding: 0;
        font: inherit;
        font-weight: 500;
        /* Цвет как у соседних пунктов nav — задаёт v2-theme / home.css */
        color: inherit;
        background: none;
        border: none;
        cursor: pointer;
        text-decoration: none;
        font-family: inherit;
    }

    #site-header .header__more-toggle:hover,
    #site-header .header__more-toggle:focus-visible {
        text-decoration: underline;
        text-underline-offset: 4px;
        outline: none;
    }

    #site-header .header__more-menu {
        position: absolute;
        /* Привязка к правому краю «Ещё» — не уезжает под Акции/Страны */
        left: auto;
        right: 0;
        /* Без зазора с кнопкой — иначе при движении курсора :hover пропадает */
        top: calc(100% - 2px);
        min-width: 220px;
        padding: 8px;
        padding-top: 10px;
        border-radius: 12px;
        /* Один стиль меню на всех страницах (баг Даника: разный цвет на Главной / остальных) */
        background: #ffffff;
        border: 1px solid rgba(26, 26, 64, 0.12);
        box-shadow: 0 18px 42px rgba(2, 6, 23, 0.18);
        display: none;
        z-index: 1200;
    }

    /* Невидимый «мост» над меню — курсор не выходит из зоны hover */
    #site-header .header__more-menu::before {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: 100%;
        height: 14px;
    }

    #site-header .header__more-menu a,
    #site-header[data-home="1"] .header__more-menu a,
    #site-header[data-home="1"]:not(.scrolled) .header__nav .header__more-menu a,
    #site-header[data-home="1"].scrolled .header__nav .header__more-menu a {
        display: block;
        padding: 9px 10px;
        border-radius: 8px;
        text-decoration: none;
        /* !important + выше specificity, чем home nav white */
        color: #1A1A40 !important;
        -webkit-text-fill-color: #1A1A40 !important;
        font-weight: 500;
        opacity: 1 !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    #site-header .header__more-menu a:hover,
    #site-header[data-home="1"]:not(.scrolled) .header__nav .header__more-menu a:hover {
        background: rgba(93, 169, 164, 0.18) !important;
        text-decoration: none;
        color: #1A1A40 !important;
        -webkit-text-fill-color: #1A1A40 !important;
    }

    #site-header .header__more:hover .header__more-menu,
    #site-header .header__more:focus-within .header__more-menu {
        display: block;
    }

    #site-header .th-promo-header-slot {
        flex: 1 1 auto;
        min-width: 0;
    }

    #site-header .header__actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-shrink: 0;
        margin-left: auto;
    }

    #site-header .header__burger {
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        #site-header .header__burger {
            margin-left: auto;
        }
    }

    /* UX: телефон в шапке — всегда на виду, кликабельный */
    #site-header .header__phone {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 14px;
        border-radius: 9999px;
        font-size: 14px;
        font-weight: 700;
        color: #fff;
        text-decoration: none;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.25);
        white-space: nowrap;
        transition: background 0.2s ease, border-color 0.2s ease;
    }
    #site-header .header__phone:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.45);
    }
    #site-header .header__phone i {
        font-size: 12px;
        color: #79BCB7;
    }
    @media (max-width: 1240px) {
        #site-header .header__phone-num { display: none; }
        #site-header .header__phone { padding: 9px 12px; }
        #site-header .header__phone i { font-size: 14px; }
    }
    @media (max-width: 768px) {
        #site-header .header__phone { display: none; }
    }

    #site-header .th-departure-pill {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.42rem 0.85rem;
        border-radius: 9999px;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: #fff;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.3);
        cursor: pointer;
        transition: background 0.2s ease, border-color 0.2s ease;
        max-width: 11rem;
    }

    #site-header .th-departure-pill:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.45);
    }

    #site-header .th-departure-pill__text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    @media (max-width: 768px) {
        #site-header .th-departure-pill {
            display: none;
        }
    }

    #site-header .header__actions .button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        padding: 10px 18px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        line-height: 1.1;
        border: 1px solid transparent;
        transition: all 0.2s ease;
    }

    #site-header .header__actions .button-secondary {
        background: rgba(255, 255, 255, 0.14);
        border-color: rgba(255, 255, 255, 0.45);
        color: #ffffff;
    }

    #site-header .header__actions .button-secondary:hover {
        background: rgba(255, 255, 255, 0.24);
        color: #ffffff;
    }

    #site-header .header__actions .button-primary {
        background: linear-gradient(135deg, #FF6B6B, #F65252);
        border-color: rgba(93, 169, 164, 0.65);
        color: #ffffff;
    }

    #site-header .header__actions .button-primary:hover {
        filter: brightness(1.04);
        transform: translateY(-1px);
        color: #ffffff;
    }

    #site-header .header__actions .button-ghost {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.28);
        color: #ffffff;
    }

    #site-header .header__actions .button-ghost:hover {
        background: rgba(255, 255, 255, 0.16);
        color: #ffffff;
    }

    @media (max-width: 1024px) {
        #site-header.header {
            padding: 12px 16px;
            gap: 14px;
        }

        #site-header .header__nav {
            gap: 14px;
        }
    }

    #site-header .header__burger {
        display: none;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        padding: 0;
        margin: 0;
        border: 1px solid rgba(255, 255, 255, 0.55);
        border-radius: 14px;
        background: linear-gradient(152deg, rgba(255, 255, 255, 0.28) 0%, rgba(255, 255, 255, 0.08) 100%);
        color: #fff;
        cursor: pointer;
        flex-shrink: 0;
        overflow: visible;
        box-sizing: border-box;
        touch-action: manipulation;
        -webkit-tap-highlight-color: transparent;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.38) inset,
            0 4px 18px rgba(0, 0, 0, 0.2);
        transition:
            background 0.22s ease,
            border-color 0.22s ease,
            box-shadow 0.22s ease,
            transform 0.2s ease;
    }
    #site-header .header__burger:hover {
        background: linear-gradient(152deg, rgba(255, 255, 255, 0.38) 0%, rgba(255, 255, 255, 0.14) 100%);
        border-color: rgba(255, 255, 255, 0.78);
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.45) inset,
            0 6px 22px rgba(0, 0, 0, 0.22);
        transform: scale(1.04);
    }
    #site-header .header__burger:active {
        transform: scale(0.96);
    }
    #site-header .header__burger:focus-visible {
        outline: none;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.38) inset,
            0 4px 18px rgba(0, 0, 0, 0.2),
            0 0 0 2px rgba(255, 107, 107, 0.75),
            0 0 0 5px rgba(255, 107, 107, 0.18);
    }
    #site-header .header__burger.is-open {
        background: linear-gradient(152deg, rgba(255, 107, 107, 0.42) 0%, rgba(255, 140, 60, 0.22) 100%);
        border-color: rgba(255, 200, 160, 0.9);
        box-shadow:
            0 1px 0 rgba(255, 230, 210, 0.35) inset,
            0 4px 22px rgba(255, 107, 107, 0.35);
    }
    #site-header .header__burger-line {
        display: block;
        width: 20px;
        height: 2px;
        background: currentColor;
        border-radius: 2px;
        position: relative;
        flex-shrink: 0;
        transition: background 0.26s cubic-bezier(0.4, 0, 0.2, 1);
    }
    #site-header .header__burger-line::before,
    #site-header .header__burger-line::after {
        content: '';
        position: absolute;
        left: 0;
        width: 20px;
        height: 2px;
        background: currentColor;
        border-radius: 2px;
        transition:
            transform 0.28s cubic-bezier(0.4, 0, 0.2, 1),
            top 0.28s cubic-bezier(0.4, 0, 0.2, 1),
            background 0.22s ease;
    }
    #site-header .header__burger-line::before { top: -6px; }
    #site-header .header__burger-line::after { top: 6px; }
    #site-header .header__burger.is-open .header__burger-line {
        background: transparent;
    }
    #site-header .header__burger.is-open .header__burger-line::before {
        top: 0;
        transform: rotate(45deg);
    }
    #site-header .header__burger.is-open .header__burger-line::after {
        top: 0;
        transform: rotate(-45deg);
    }

    @media (min-width: 769px) {
        #site-header .header__burger {
            display: none;
        }
    }

    @media (max-width: 768px) {
        #site-header .header__nav {
            display: none;
        }
        #site-header .header__actions {
            display: none;
        }
        #site-header .th-promo-header-slot {
            display: none !important;
        }
        #site-header .header__burger {
            display: inline-flex;
            margin-left: auto;
            order: 10;
        }
        #site-header .header__logo {
            flex: 0 0 auto;
            min-width: max-content;
            overflow: visible;
        }
    }

    .site-header-mobile-overlay {
        position: fixed;
        inset: 0;
        z-index: 1090;
        background: rgba(11, 11, 34, 0.55);
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .site-header-mobile-overlay.is-visible {
        opacity: 1;
        visibility: visible;
    }
    .site-header-mobile-panel {
        position: fixed;
        top: 0;
        right: 0;
        z-index: 1100;
        width: min(100vw - 1.5rem, 320px);
        max-width: 100%;
        max-height: 100vh;
        max-height: 100dvh;
        height: 100%;
        padding-top: env(safe-area-inset-top, 0px);
        padding-right: env(safe-area-inset-right, 0px);
        padding-bottom: env(safe-area-inset-bottom, 0px);
        background: rgba(16, 16, 46, 0.98);
        border-left: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: -12px 0 40px rgba(0, 0, 0, 0.35);
        transform: translate3d(100%, 0, 0);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.3s ease;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        visibility: hidden;
        pointer-events: none;
        will-change: transform;
    }
    .site-header-mobile-panel.is-open {
        transform: translate3d(0, 0, 0);
        visibility: visible;
        pointer-events: auto;
    }
    .site-header-mobile-panel__inner {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        max-height: none !important;
        height: auto !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        padding: 1rem 1rem max(1.5rem, env(safe-area-inset-bottom, 0px));
        -webkit-overflow-scrolling: touch;
        touch-action: pan-y;
    }
    .site-header-mobile-close {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
    }
    .site-header-mobile-close:hover {
        background: rgba(255, 255, 255, 0.15);
    }
    .site-header-mobile-nav {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        padding-top: 2.75rem;
    }
    .site-header-mobile-nav a {
        display: block;
        padding: 0.75rem 0.75rem;
        border-radius: 10px;
        color: rgba(255, 255, 255, 0.95);
        text-decoration: none;
        font-weight: 500;
    }
    .site-header-mobile-nav a:hover,
    .site-header-mobile-nav a.is-active {
        background: rgba(93, 169, 164, 0.3);
    }
    .site-header-mobile-nav .site-header-mobile-muted {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: rgba(255, 255, 255, 0.78);
        padding: 0.75rem 0.75rem 0.35rem;
        margin-top: 0.5rem;
    }
    .site-header-mobile-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    .site-header-mobile-actions a {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.65rem 1rem;
        border-radius: 9999px;
        font-weight: 600;
        text-decoration: none;
        text-align: center;
    }
    .site-header-mobile-actions .btn-m-secondary {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.35);
        color: #fff;
    }
    .site-header-mobile-actions .btn-m-primary {
        background: linear-gradient(135deg, #FF6B6B, #F65252);
        color: #fff;
        border: 1px solid rgba(93, 169, 164, 0.5);
    }
    .site-header-mobile-actions .btn-m-ghost {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: rgba(255, 255, 255, 0.9);
    }
</style>
<?php endif; ?>
<header id="site-header" class="header"<?php echo $current_page === 'home' ? ' data-home="1"' : ''; ?>>
    <a href="/index.php" class="header__logo">Travel Hub</a>

    <nav class="header__nav" aria-label="Главная навигация">
        <a href="/index.php" class="<?php echo $current_page === 'home' ? 'is-active' : ''; ?>">Главная</a>
        <a href="/frontend/window/promotions.php" class="<?php echo $current_page === 'promotions' ? 'is-active' : ''; ?>">Акции</a>
        <a href="/frontend/window/countries-list.php" class="<?php echo $current_page === 'countries' ? 'is-active' : ''; ?>">Страны</a>
        <?php /* Временно скрыто: раздел «Отели» (popular-hotels) — пока разбираем hotel-only цены
        <a href="/frontend/window/popular-hotels.php" class="<?php echo !empty($hotels_nav_active) ? 'is-active' : ''; ?>">Отели</a>
        */ ?>
        <a href="/frontend/window/offices.php" class="<?php echo $current_page === 'offices' ? 'is-active' : ''; ?>">Офисы</a>
        <div class="header__more">
            <button type="button" class="header__more-toggle <?php echo $more_menu_active ? 'is-active' : ''; ?>" aria-label="Ещё разделы" aria-haspopup="true" aria-controls="site-header-more-menu">
                Ещё <span aria-hidden="true">▾</span>
            </button>
            <div class="header__more-menu" id="site-header-more-menu" role="menu" aria-label="Дополнительные разделы">
                <a href="/frontend/window/about.php">О нас</a>
                <a href="/frontend/window/services.php">Услуги</a>
                <a href="/frontend/window/tour-calendar.php">Календарь выгодных дат</a>
                <a href="/frontend/window/turkey-vip-hotels.php">VIP отели Турции</a>
                <a href="/frontend/window/contacts.php">Контакты</a>
                <a href="/frontend/window/banks_rekvesit.php">Реквизиты</a>
            </div>
        </div>
    </nav>

    <!-- Плашка «Вылет» в шапке отключена
    <button type="button" id="th-departure-header-btn" class="th-departure-pill" hidden title="Город вылета">
        <span class="th-departure-pill__icon" aria-hidden="true">✈</span>
        <span class="th-departure-pill__text">Вылет</span>
    </button>
    -->

    <div id="th-promo-header-slot" class="th-promo-header-slot" aria-hidden="true"></div>

    <div class="header__actions">
        <a href="tel:+78462541656" class="header__phone" title="Позвонить нам">
            <i class="fas fa-phone" aria-hidden="true"></i>
            <span class="header__phone-num">+7 (846) 254-16-56</span>
        </a>
        <?php if ($isLoggedIn): ?>
            <a href="/frontend/window/profile.php" class="button button-secondary" title="<?php echo htmlspecialchars($userName !== '' ? $userName : 'Профиль', ENT_QUOTES, 'UTF-8'); ?>">
                <span aria-hidden="true">👤</span> Профиль
            </a>
            <?php if ($canOpenManagerWindow): ?>
                <a href="/frontend/window/for-operators.php" class="button button-secondary">Менеджерам</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <a href="/backend/admin/admin.php" class="button button-primary">Админ</a>
            <?php endif; ?>
            <a href="/backend/scripts/logout.php" class="button button-ghost">Выход</a>
        <?php else: ?>
            <a href="/frontend/window/login-desktop.php" class="button button-secondary">Войти</a>
            <a href="/frontend/window/registration-desktop.php" class="button button-primary">Регистрация</a>
        <?php endif; ?>
    </div>

    <button type="button" class="header__burger" id="site-header-burger" aria-expanded="false" aria-controls="site-header-mobile-panel" aria-label="Открыть меню">
        <span class="header__burger-line" aria-hidden="true"></span>
    </button>
</header>

<div id="site-header-mobile-overlay" class="site-header-mobile-overlay" aria-hidden="true"></div>
<div id="site-header-mobile-panel" class="site-header-mobile-panel" role="dialog" aria-modal="true" aria-label="Меню сайта" aria-hidden="true">
    <div class="site-header-mobile-panel__inner" style="position:relative">
        <button type="button" class="site-header-mobile-close" id="site-header-mobile-close" aria-label="Закрыть меню">&times;</button>
        <nav class="site-header-mobile-nav" aria-label="Разделы">
            <a href="/index.php" class="<?php echo $current_page === 'home' ? 'is-active' : ''; ?>">Главная</a>
            <a href="/frontend/window/promotions.php" class="<?php echo $current_page === 'promotions' ? 'is-active' : ''; ?>">Акции</a>
            <a href="/frontend/window/countries-list.php" class="<?php echo $current_page === 'countries' ? 'is-active' : ''; ?>">Страны</a>
            <?php /* Временно скрыто: раздел «Отели»
            <a href="/frontend/window/popular-hotels.php" class="<?php echo !empty($hotels_nav_active) ? 'is-active' : ''; ?>">Отели</a>
            */ ?>
            <a href="/frontend/window/offices.php" class="<?php echo $current_page === 'offices' ? 'is-active' : ''; ?>">Офисы</a>
            <span class="site-header-mobile-muted">Ещё</span>
            <a href="/frontend/window/about.php" class="<?php echo $current_page === 'about' ? 'is-active' : ''; ?>">О нас</a>
            <a href="/frontend/window/services.php" class="<?php echo $current_page === 'services' ? 'is-active' : ''; ?>">Услуги</a>
            <a href="/frontend/window/tour-calendar.php" class="<?php echo $current_page === 'tour-calendar' ? 'is-active' : ''; ?>">Календарь выгодных дат</a>
            <a href="/frontend/window/turkey-vip-hotels.php" class="<?php echo $current_page === 'vip-hotels' ? 'is-active' : ''; ?>">VIP отели Турции</a>
            <a href="/frontend/window/contacts.php" class="<?php echo $current_page === 'contacts' ? 'is-active' : ''; ?>">Контакты</a>
            <span class="site-header-mobile-muted">Документы</span>
            <a href="/frontend/window/banks_rekvesit.php" class="<?php echo $current_page === 'banks_rekvesit' ? 'is-active' : ''; ?>">Реквизиты</a>
            <a href="/frontend/window/consent.php" class="<?php echo ($current_page ?? '') === 'consent' ? 'is-active' : ''; ?>">Согласие на обработку ПД</a>
            <a href="/frontend/window/privacy.php" class="<?php echo $current_page === 'privacy' ? 'is-active' : ''; ?>">Политика конфиденциальности</a>
            <a href="/frontend/window/terms.php" class="<?php echo $current_page === 'terms' ? 'is-active' : ''; ?>">Пользовательское соглашение</a>
        </nav>
        <div class="site-header-mobile-actions">
            <a href="tel:+78462541656" class="btn-m-secondary" style="text-align:center"><i class="fas fa-phone" aria-hidden="true"></i> +7 (846) 254-16-56</a>
            <?php if ($isLoggedIn): ?>
                <a href="/frontend/window/profile.php" class="btn-m-secondary"><?php echo htmlspecialchars($userName !== '' ? $userName : 'Профиль', ENT_QUOTES, 'UTF-8'); ?></a>
                <?php if ($canOpenManagerWindow): ?>
                    <a href="/frontend/window/for-operators.php" class="btn-m-secondary">Менеджерам</a>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <a href="/backend/admin/admin.php" class="btn-m-primary">Админ</a>
                <?php endif; ?>
                <a href="/backend/scripts/logout.php" class="btn-m-ghost">Выход</a>
            <?php else: ?>
                <a href="/frontend/window/login.php" class="btn-m-secondary">Войти</a>
                <a href="/frontend/window/registration-desktop.php" class="btn-m-primary">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var burger = document.getElementById('site-header-burger');
    var overlay = document.getElementById('site-header-mobile-overlay');
    var panel = document.getElementById('site-header-mobile-panel');
    var closeBtn = document.getElementById('site-header-mobile-close');
    if (!burger || !overlay || !panel) return;

    if (overlay.parentNode !== document.body) document.body.appendChild(overlay);
    if (panel.parentNode !== document.body) document.body.appendChild(panel);

    var scrollLockY = 0;

    function setOpen(open) {
        var wasOpen = panel.classList.contains('is-open');
        overlay.classList.toggle('is-visible', open);
        panel.classList.toggle('is-open', open);
        burger.classList.toggle('is-open', open);
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        burger.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
        overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (open) {
            scrollLockY = window.scrollY || window.pageYOffset || 0;
            document.body.classList.add('th-modal-open');
            document.body.style.position = 'fixed';
            document.body.style.top = '-' + scrollLockY + 'px';
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.width = '100%';
            try { if (closeBtn) closeBtn.focus(); } catch (e) {}
        } else {
            document.body.classList.remove('th-modal-open');
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.width = '';
            if (wasOpen) {
                try { window.scrollTo(0, scrollLockY); } catch (e2) {}
            }
            try { burger.focus(); } catch (e3) {}
        }
        if (window.THMobile && window.THMobile.sync) window.THMobile.sync();
    }

    burger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        setOpen(!panel.classList.contains('is-open'));
    });
    overlay.addEventListener('click', function () { setOpen(false); });
    overlay.addEventListener('touchmove', function (e) { e.preventDefault(); }, { passive: false });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    panel.addEventListener('click', function (e) { e.stopPropagation(); });
    panel.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () { setOpen(false); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('is-open')) {
            e.preventDefault();
            setOpen(false);
        }
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768 && panel.classList.contains('is-open')) {
            setOpen(false);
        }
    });
})();
</script>
<script>
(function () {
    var b = <?php echo json_encode($th_tv_api_base, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof b === 'string' && b.indexOf('http://') === 0) {
        b = 'https:' + b.substring(5);
    }
    window.TH_TV_API_BASE = b;
    var imgP = <?php echo json_encode($th_tv_image_proxy_base, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof imgP === 'string' && imgP.indexOf('http://') === 0) {
        imgP = 'https:' + imgP.substring(5);
    }
    window.TH_TV_IMAGE_PROXY = imgP;
    window.TV_IMAGE_PROXY = imgP;
    window.TH_DEPARTURE = { id: <?php echo (int) $th_departure_id; ?>, name: <?php echo json_encode($th_departure_name, JSON_UNESCAPED_UNICODE); ?> };
    window.TH_CSRF = <?php echo json_encode($th_csrf_token, JSON_UNESCAPED_UNICODE); ?>;
})();
</script>
<?php
$_th_dp = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'th-date-presets.js';
$_th_dp_v = is_file($_th_dp) ? (string) filemtime($_th_dp) : '2';
?>
<script src="/frontend/js/th-date-presets.js?v=<?php echo htmlspecialchars($_th_dp_v, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/frontend/js/departure-preference.js" defer></script>
<?php
$_th_fp_hdr = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'tourvisor-flight-pick.js';
$_th_fp_hdr_v = is_file($_th_fp_hdr) ? (string) filemtime($_th_fp_hdr) : '1';
?>
<script src="/frontend/js/tourvisor-flight-pick.js?v=<?php echo htmlspecialchars($_th_fp_hdr_v, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php
$_th_tps_hdr = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'th-tour-price-sanity.js';
$_th_tps_hdr_v = is_file($_th_tps_hdr) ? (string) filemtime($_th_tps_hdr) : '1';
$_th_tc_hdr = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'th-tour-card.js';
$_th_tc_hdr_v = is_file($_th_tc_hdr) ? (string) filemtime($_th_tc_hdr) : '1';
$_th_tpf_hdr = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'th-tour-post-filters.js';
$_th_tpf_hdr_v = is_file($_th_tpf_hdr) ? (string) filemtime($_th_tpf_hdr) : '1';
$_th_tbm_hdr = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'th-tour-booking-modal.js';
$_th_tbm_hdr_v = is_file($_th_tbm_hdr) ? (string) filemtime($_th_tbm_hdr) : '1';
$_th_sm_hdr = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'session-manager.js';
$_th_sm_hdr_v = is_file($_th_sm_hdr) ? (string) filemtime($_th_sm_hdr) : '1';
?>
<script src="/frontend/js/th-tour-price-sanity.js?v=<?php echo htmlspecialchars($_th_tps_hdr_v, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/frontend/js/th-tour-card.js?v=<?php echo htmlspecialchars($_th_tc_hdr_v, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/frontend/js/session-manager.js?v=<?php echo htmlspecialchars($_th_sm_hdr_v, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/frontend/js/th-tour-post-filters.js?v=<?php echo htmlspecialchars($_th_tpf_hdr_v, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/frontend/js/th-tour-booking-modal.js?v=<?php echo htmlspecialchars($_th_tbm_hdr_v, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php
$_th_cc_hdr = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'cookie-consent.js';
$_th_cc_hdr_v = is_file($_th_cc_hdr) ? (string) filemtime($_th_cc_hdr) : '1';
if (!defined('TH_COOKIE_CONSENT_STYLES')) {
    define('TH_COOKIE_CONSENT_STYLES', true);
?>
<style id="th-cookie-consent-critical">
.cookie-consent-banner{position:fixed;bottom:0;left:0;right:0;z-index:10050;background:#f9fafb;border-top:1px solid rgba(15,23,42,.08);box-shadow:0 -4px 24px rgba(15,23,42,.12);padding:12px 16px;padding-bottom:max(12px,env(safe-area-inset-bottom,0px));font-size:.8125rem;line-height:1.45;color:#334155;transform:translateY(100%);opacity:0;transition:transform .28s ease,opacity .28s ease;pointer-events:none}
.cookie-consent-banner.is-visible{transform:translateY(0);opacity:1;pointer-events:auto}
.cookie-consent-banner__inner{max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:12px 20px}
.cookie-consent-banner__text{margin:0;flex:1 1 220px;max-width:52rem;text-align:left}
.cookie-consent-banner__link{color:#1A1A40;font-weight:600;text-decoration:underline}
.cookie-consent-banner__actions{display:flex;flex-wrap:wrap;gap:8px;flex-shrink:0}
.cookie-consent-banner__btn{border:none;border-radius:10px;padding:9px 20px;font-size:.875rem;font-weight:600;cursor:pointer}
.cookie-consent-banner__btn--accept{background:linear-gradient(135deg,#FF6B6B,#F65252);color:#fff}
.cookie-consent-banner__btn--decline{background:#e2e8f0;color:#475569}
@media (max-width:640px){.cookie-consent-banner{font-size:.75rem}.cookie-consent-banner__inner{flex-direction:column;align-items:stretch}.cookie-consent-banner__text{text-align:center}.cookie-consent-banner__actions{width:100%}.cookie-consent-banner__btn{flex:1 1 auto}}
</style>
<?php } ?>
<script src="/frontend/js/cookie-consent.js?v=<?php echo htmlspecialchars($_th_cc_hdr_v, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
