<?php
require_once __DIR__ . '/../../backend/components/page_cache_early.php';
if (PageCache::get()) exit;
require_once __DIR__ . '/../../backend/config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
PageCache::start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>О нас - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/frontend/css/pages/about.css?v=2">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="text-slate-900">
    <?php 
    $current_page = 'about';
    include __DIR__ . '/../../backend/components/header.php'; 
    ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <img src="/frontend/window/img/турция/turkey-stambul-22181.jpg" 
             alt="Турция" 
             class="hero-background"
             loading="eager">
        <div class="hero-overlay"></div>
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-5xl mx-auto text-center hero-content">
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/20 backdrop-blur-md border border-white/30 text-white text-xs uppercase tracking-[0.3em] mb-6">
                    <i class="fas fa-star"></i>
                    Премиум путешествия с 2010 года
                </span>
                <h1 class="heading-font text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold text-white mb-6 leading-tight">
                    Travel Hub
                </h1>
                <p class="text-xl sm:text-2xl md:text-3xl text-white/95 mb-8 max-w-3xl mx-auto leading-relaxed">
                    Ваш персональный консьерж в мире незабываемых путешествий
                </p>
                <p class="text-lg sm:text-xl text-white/90 max-w-2xl mx-auto mb-10">
                    Мы создаём уникальные маршруты, организуем премиум-отдых и обеспечиваем сервис, который остаётся в памяти на всю жизнь
                </p>
            </div>
        </div>
    </section>

    <!-- Story Section -->
    <section class="py-16 sm:py-20 md:py-28 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center mb-20">
                    <div class="image-section h-96 lg:h-[500px]">
                        <img src="/frontend/window/img/турция/bodrum-1.jpg" 
                             alt="Турция" 
                             loading="lazy">
                    </div>
                    <div>
                        <span class="inline-block px-4 py-2 rounded-full bg-sky-50 border border-sky-200 text-sky-600 text-sm font-semibold mb-6">
                            Наша история
                        </span>
                        <h2 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 mb-6">
                            Более 14 лет создаём <span class="gradient-text">идеальные путешествия</span>
                        </h2>
                        <div class="space-y-4 text-lg text-slate-700 leading-relaxed">
                            <p>
                                Travel Hub была основана в 2010 году командой профессионалов, которые верили, что каждое путешествие должно быть уникальным, безопасным и незабываемым.
                            </p>
                            <p>
                                За годы работы мы выросли из небольшого агентства в одну из ведущих туристических компаний России, специализирующихся на премиальном сервисе и индивидуальном подходе к каждому клиенту.
                            </p>
                            <p class="text-slate-700 font-medium">
                                Сегодня мы — это команда экспертов, которая организует путешествия для более чем 50 000 довольных клиентов по всему миру.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Mission Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                    <div class="order-2 lg:order-1">
                        <span class="inline-block px-4 py-2 rounded-full bg-sky-50 border border-sky-200 text-sky-600 text-sm font-semibold mb-6">
                            Наша миссия
                        </span>
                        <h2 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 mb-6">
                            Превращаем мечты в <span class="gradient-text">реальность</span>
                        </h2>
                        <div class="space-y-4 text-lg text-slate-700 leading-relaxed">
                            <p>
                                Мы стремимся сделать путешествия доступными для каждого, предоставляя высококачественный сервис, персональный подход и гарантию безопасности на каждом этапе вашего путешествия.
                            </p>
                            <p>
                                Наша цель — создать незабываемые воспоминания, которые останутся с вами на всю жизнь. Мы не просто продаём туры, мы создаём истории.
                            </p>
                            <p class="text-slate-700 font-medium">
                                Каждый клиент для нас — это возможность создать что-то особенное, уникальное и неповторимое.
                            </p>
                        </div>
                    </div>
                    <div class="image-section h-96 lg:h-[500px] order-1 lg:order-2">
                        <img src="/frontend/window/img/мальдивы/1a8d863f3a6095f994c7d10d6f82960c.jpg" 
                             alt="Мальдивы" 
                             loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-16 sm:py-20 md:py-28 relative overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="/frontend/window/img/мальдивы/3fadde43a70f778ca631c0a28ed34a40.jpg" 
                 alt="Мальдивы" 
                 class="w-full h-full object-cover opacity-20">
            <div class="absolute inset-0 bg-gradient-to-br from-sky-50 via-blue-50 to-white"></div>
        </div>
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 mb-4">
                        Travel Hub в <span class="gradient-text">цифрах</span>
                    </h2>
                    <p class="text-xl text-slate-700 max-w-2xl mx-auto">
                        Результаты нашей работы говорят сами за себя
                    </p>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8">
                    <div class="stat-card rounded-2xl p-8 text-center">
                        <div class="text-5xl sm:text-6xl font-bold gradient-text mb-3">50K+</div>
                        <div class="text-slate-700 font-medium text-lg">Довольных клиентов</div>
                    </div>
                    <div class="stat-card rounded-2xl p-8 text-center">
                        <div class="text-5xl sm:text-6xl font-bold gradient-text mb-3">14+</div>
                        <div class="text-slate-700 font-medium text-lg">Лет опыта</div>
                    </div>
                    <div class="stat-card rounded-2xl p-8 text-center">
                        <div class="text-5xl sm:text-6xl font-bold gradient-text mb-3">100+</div>
                        <div class="text-slate-700 font-medium text-lg">Направлений</div>
                    </div>
                    <div class="stat-card rounded-2xl p-8 text-center">
                        <div class="text-5xl sm:text-6xl font-bold gradient-text mb-3">24/7</div>
                        <div class="text-slate-700 font-medium text-lg">Поддержка</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Photo Gallery Section -->
    <section id="team" class="py-16 sm:py-20 md:py-28 bg-gradient-to-br from-slate-50 to-sky-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <span class="inline-block px-4 py-2 rounded-full bg-sky-50 border border-sky-200 text-sky-600 text-sm font-semibold mb-6">
                        Наша команда в работе
                    </span>
                    <h2 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 mb-4">
                        Мы создаём <span class="gradient-text">незабываемые моменты</span>
                    </h2>
                    <p class="text-xl text-slate-700 max-w-2xl mx-auto">
                        Посмотрите, как мы работаем и какие путешествия организуем
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                    <div class="image-section h-64 lg:h-80 rounded-2xl overflow-hidden shadow-xl">
                        <img src="/frontend/window/img/таиланд/870112554ed554357b844f61493ce547.jpg" 
                             alt="Таиланд" 
                             loading="lazy">
                    </div>
                    <div class="image-section h-64 lg:h-80 rounded-2xl overflow-hidden shadow-xl">
                        <img src="/frontend/window/img/ОАЭ/d7d6b9977c657fc97e65d3d22219d8dc.jpg" 
                             alt="ОАЭ" 
                             loading="lazy">
                    </div>
                    <div class="image-section h-64 lg:h-80 rounded-2xl overflow-hidden shadow-xl">
                        <img src="/frontend/window/img/египет/photo_2025-11-27_17-02-33.jpg" 
                             alt="Египет" 
                             loading="lazy">
                    </div>
                    <div class="image-section h-64 lg:h-80 rounded-2xl overflow-hidden shadow-xl">
                        <img src="/frontend/window/img/сейшелы/a605d9c888f456b0bd001f4b3ef79d68.jpg" 
                             alt="Сейшелы" 
                             loading="lazy">
                    </div>
                    <div class="image-section h-64 lg:h-80 rounded-2xl overflow-hidden shadow-xl">
                        <img src="/frontend/window/img/шриланка/8bd273484e927f25219ac7a3e80fe003.jpg" 
                             alt="Шри-Ланка" 
                             loading="lazy">
                    </div>
                    <div class="image-section h-64 lg:h-80 rounded-2xl overflow-hidden shadow-xl">
                        <img src="/frontend/window/img/маврикий/946ccd7a7b82f2c25b164ada53e46ea5.jpg"
                             alt="Маврикий" 
                             loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Partners Section -->
    <section id="partners" class="py-16 sm:py-20 md:py-24 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-10">
                    <span class="inline-block px-4 py-2 rounded-full bg-sky-50 border border-sky-200 text-sky-600 text-sm font-semibold mb-4">Партнёры</span>
                    <h2 class="heading-font text-3xl sm:text-4xl font-bold text-slate-900 mb-3">Работаем с ведущими туроператорами</h2>
                    <p class="text-slate-700">Нажмите на логотип, чтобы перейти на официальный сайт партнёра</p>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-5">
                    <?php
                    $thTourOperatorsPartners = is_file(__DIR__ . '/../../backend/config/tour_operators_partners.php')
                        ? require __DIR__ . '/../../backend/config/tour_operators_partners.php'
                        : [];
                    foreach ($thTourOperatorsPartners as $thPartner):
                    ?>
                    <a class="partner-logo-card" href="<?php echo htmlspecialchars($thPartner['href'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" aria-label="<?php echo htmlspecialchars($thPartner['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars($thPartner['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($thPartner['name'], ENT_QUOTES, 'UTF-8'); ?>" width="160" height="48" loading="lazy" decoding="async">
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section class="py-16 sm:py-20 md:py-28 relative overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="/frontend/window/img/таиланд/91ab2281edaad46dd43d245fccc3d9a0.jpg" 
                 alt="Таиланд" 
                 class="w-full h-full object-cover opacity-15">
            <div class="absolute inset-0 bg-white"></div>
        </div>
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <span class="inline-block px-4 py-2 rounded-full bg-sky-50 border border-sky-200 text-sky-600 text-sm font-semibold mb-6">
                        Наши принципы
                    </span>
                    <h2 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 mb-4">
                        Что делает нас <span class="gradient-text">особенными</span>
                    </h2>
                    <p class="text-xl text-slate-700 max-w-2xl mx-auto">
                        Три столпа, на которых строится наш сервис
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-12">
                    <div class="text-center">
                        <div class="feature-icon mb-6">
                            <i class="fas fa-shield-alt text-4xl text-sky-600"></i>
                        </div>
                        <h3 class="heading-font text-2xl font-bold text-slate-900 mb-4">Безопасность</h3>
                        <p class="text-slate-700 text-lg leading-relaxed">
                            Все туры застрахованы, отели тщательно проверены, вы защищены на каждом этапе путешествия. Мы гарантируем полную безопасность и надёжность.
                        </p>
                    </div>
                    <div class="text-center">
                        <div class="feature-icon mb-6">
                            <i class="fas fa-heart text-4xl text-sky-600"></i>
                        </div>
                        <h3 class="heading-font text-2xl font-bold text-slate-900 mb-4">Забота</h3>
                        <p class="text-slate-700 text-lg leading-relaxed">
                            Персональный менеджер сопровождает вас от момента бронирования до возвращения домой. Мы всегда на связи и готовы помочь в любой ситуации.
                        </p>
                    </div>
                    <div class="text-center">
                        <div class="feature-icon mb-6">
                            <i class="fas fa-star text-4xl text-sky-600"></i>
                        </div>
                        <h3 class="heading-font text-2xl font-bold text-slate-900 mb-4">Качество</h3>
                        <p class="text-slate-700 text-lg leading-relaxed">
                            Работаем только с проверенными партнёрами и гарантируем высокий уровень сервиса. Каждая деталь вашего путешествия продумана до мелочей.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="py-16 sm:py-20 md:py-28 relative overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="/frontend/window/img/ОАЭ/734f8edf8d0c999bb97522274e25b32c.jpg" 
                 alt="ОАЭ" 
                 class="w-full h-full object-cover opacity-20">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-50 to-sky-50"></div>
        </div>
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                    <div class="image-section h-96 lg:h-[500px] rounded-2xl overflow-hidden shadow-2xl">
                        <img src="/frontend/window/img/маврикий/f37149293ca7fd31b458b42c085465e8.jpg" 
                             alt="Маврикий" 
                             loading="lazy">
                    </div>
                    <div>
                        <span class="inline-block px-4 py-2 rounded-full bg-sky-50 border border-sky-200 text-sky-600 text-sm font-semibold mb-6">
                            Почему выбирают нас
                        </span>
                        <h2 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 mb-8">
                            Премиум-сервис <span class="gradient-text">без компромиссов</span>
                        </h2>
                        <div class="space-y-6">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-sky-300 to-blue-400 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div>
                                    <h4 class="heading-font text-xl font-bold text-slate-900 mb-2">Персональный подход</h4>
                                    <p class="text-slate-700">Каждое путешествие разрабатывается индивидуально с учётом ваших пожеланий и предпочтений</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-sky-300 to-blue-400 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div>
                                    <h4 class="heading-font text-xl font-bold text-slate-900 mb-2">Эксклюзивные направления</h4>
                                    <p class="text-slate-700">Доступ к закрытым виллам, приватным пляжам и уникальным локациям по всему миру</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-sky-300 to-blue-400 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div>
                                    <h4 class="heading-font text-xl font-bold text-slate-900 mb-2">Круглосуточная поддержка</h4>
                                    <p class="text-slate-700">Наша команда доступна 24/7 во время вашего путешествия для решения любых вопросов</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-sky-300 to-blue-400 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div>
                                    <h4 class="heading-font text-xl font-bold text-slate-900 mb-2">Гарантия качества</h4>
                                    <p class="text-slate-700">Мы лично проверяем каждый отель и маршрут, чтобы гарантировать высочайший уровень сервиса</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Information Section -->
    <section id="contact" class="py-16 sm:py-20 md:py-28 relative overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="/frontend/window/img/сейшелы/c3ef99b44739059a79cbe4c652b198df.jpg" 
                 alt="Сейшелы" 
                 class="w-full h-full object-cover opacity-15">
            <div class="absolute inset-0 bg-white"></div>
        </div>
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-16">
                    <span class="inline-block px-4 py-2 rounded-full bg-sky-50 border border-sky-200 text-sky-600 text-sm font-semibold mb-6">
                        Свяжитесь с нами
                    </span>
                    <h2 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 mb-4">
                        Готовы начать <span class="gradient-text">ваше путешествие?</span>
                    </h2>
                    <p class="text-xl text-slate-700 max-w-2xl mx-auto">
                        Свяжитесь с нами любым удобным способом. Мы ответим в течение 15 минут
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                    <div class="contact-card rounded-2xl p-8 text-center">
                        <div class="w-20 h-20 rounded-2xl bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center mx-auto mb-6 shadow-lg">
                            <i class="fas fa-phone text-white text-3xl"></i>
                        </div>
                        <h3 class="heading-font text-xl font-bold text-slate-900 mb-3">Телефон</h3>
                        <p class="text-slate-700 mb-2"><a href="tel:+78462541656" class="hover:text-sky-500 font-semibold text-lg">+7 (846) 254-16-56</a></p>
                        <p class="text-sm text-slate-600">Пн-Пт: 9:00 - 21:00</p>
                        <p class="text-sm text-slate-600">Сб-Вс: 10:00 - 18:00</p>
                    </div>

                    <div class="contact-card rounded-2xl p-8 text-center">
                        <div class="w-20 h-20 rounded-2xl bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center mx-auto mb-6 shadow-lg">
                            <i class="fas fa-envelope text-white text-3xl"></i>
                        </div>
                        <h3 class="heading-font text-xl font-bold text-slate-900 mb-3">Email</h3>
                        <p class="text-slate-700"><a href="mailto:hello@travelhub63.ru" class="hover:text-sky-500">hello@travelhub63.ru</a></p>
                    </div>

                    <div class="contact-card rounded-2xl p-8 text-center">
                        <div class="w-20 h-20 rounded-2xl bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center mx-auto mb-6 shadow-lg">
                            <i class="fas fa-share-alt text-white text-3xl"></i>
                        </div>
                        <h3 class="heading-font text-xl font-bold text-slate-900 mb-4">Социальные сети</h3>
                        <div class="flex justify-center gap-4">
                            <a href="https://max.ru/u/f9LHodD0cOJpBbwh-zr3lqTmDxZiZMLDP-FuyTUa8fyzWO3S2tgc4_Mirnk" class="w-16 h-16 rounded-2xl bg-slate-900 text-white flex items-center justify-center hover:bg-[#5B7CFF] transition shadow-md text-sm font-extrabold" aria-label="MAX" target="_blank" rel="noopener noreferrer">MAX</a>
                            <a href="https://t.me/TravelHub63" class="w-16 h-16 rounded-2xl bg-sky-100 text-sky-600 flex items-center justify-center hover:bg-sky-200 transition shadow-md" aria-label="Telegram" target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-telegram text-2xl"></i>
                            </a>
                            <a href="https://vk.ru/hubtravel" class="w-16 h-16 rounded-2xl bg-sky-100 text-[#0077FF] flex items-center justify-center hover:bg-sky-200 transition shadow-md" aria-label="VK" target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-vk text-2xl"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Form Section -->
    <section id="contact-form" class="py-16 sm:py-20 md:py-28 bg-gradient-to-br from-sky-50 via-blue-50 to-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-12">
                    <span class="inline-block px-4 py-2 rounded-full bg-sky-50 border border-sky-200 text-sky-600 text-sm font-semibold mb-6">
                        Оставьте заявку
                    </span>
                    <h2 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 mb-4">
                        Свяжитесь с нами
                    </h2>
                    <p class="text-xl text-slate-700 max-w-2xl mx-auto">
                        Оставьте контакты, и мы свяжемся с вами в течение 15 минут. Подготовим 2-3 концепции путешествия с расчётом бюджета.
                    </p>
                </div>
                <div class="surface-card p-6 md:p-10">
                    <?php
                    $th_lead_source = 'about_page';
                    $th_lead_id = 'about-lead-form';
                    include __DIR__ . '/../../backend/components/lead_form.php';
                    ?>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-16 sm:py-20 md:py-28 bg-gradient-to-r from-sky-500 via-blue-500 to-sky-600">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-white mb-6">
                    Начните планировать ваше идеальное путешествие сегодня
                </h2>
                <p class="text-xl text-white/90 mb-10 max-w-2xl mx-auto">
                    Оставьте заявку, и наш консьерж свяжется с вами в течение 15 минут. Мы подготовим несколько уникальных вариантов путешествия специально для вас.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="tel:+78462541656" class="inline-flex items-center justify-center gap-3 bg-white text-sky-600 px-8 py-4 rounded-xl font-semibold text-lg hover:shadow-2xl transition transform hover:-translate-y-1">
                        <i class="fas fa-phone"></i>
                        +7 (846) 254-16-56
                    </a>
                    <a href="#contact-form" class="inline-flex items-center justify-center gap-3 bg-white/10 backdrop-blur-md text-white border-2 border-white/30 px-8 py-4 rounded-xl font-semibold text-lg hover:bg-white/20 transition">
                        <i class="fas fa-envelope"></i>
                        Оставить заявку
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>

    <script>
        // Плавный переход к блоку контактов при открытии по якорю #contact
        (function () {
            if (window.location.hash !== '#contact') return;
            var target = document.getElementById('contact');
            if (!target) return;
            window.addEventListener('load', function () {
                setTimeout(function () {
                    var start = window.pageYOffset;
                    var end = target.getBoundingClientRect().top + start;
                    var distance = end - start;
                    var duration = 1400;
                    var startTime = null;
                    function easeInOutCubic(t) {
                        return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
                    }
                    function step(timestamp) {
                        if (!startTime) startTime = timestamp;
                        var elapsed = timestamp - startTime;
                        var progress = Math.min(elapsed / duration, 1);
                        var eased = easeInOutCubic(progress);
                        window.scrollTo(0, start + distance * eased);
                        if (progress < 1) requestAnimationFrame(step);
                    }
                    requestAnimationFrame(step);
                }, 200);
            });
        })();
    </script>
<?php PageCache::end(); ?>
</body>
</html>