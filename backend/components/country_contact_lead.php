<?php
/**
 * Единый контактный блок + Яндекс.Карта (как в v1) + lead CTA для страниц стран.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/contacts.php';
require_once dirname(__DIR__) . '/config/maps.php';
$thc = th_contacts();
$thm = th_maps();
$th_country_cta_source = isset($th_country_cta_source) ? (string) $th_country_cta_source : 'country_page';
// Как в website-main: constructor-виджет; если нужен HQ Самары — задайте $yandex_map_open_url до include.
$yandex_map_open_url = isset($yandex_map_open_url) && is_string($yandex_map_open_url) && trim($yandex_map_open_url) !== ''
    ? trim($yandex_map_open_url)
    : $thm['widget_default'];
?>
<section id="contact" class="relative py-16 sm:py-20" style="background:linear-gradient(180deg,#eef6f5 0%,#f8fafc 100%);">
    <div class="container mx-auto px-4 sm:px-6 max-w-6xl">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start mb-8">
            <div class="space-y-4">
                <h2 class="heading-font text-2xl sm:text-3xl font-bold text-slate-900">Свяжитесь с Travel Hub</h2>
                <p class="text-slate-600 leading-relaxed">Подберём тур по этой стране за 15 минут — в офисе или по телефону.</p>
                <div class="rounded-2xl bg-white border border-slate-200 p-5 sm:p-6 space-y-3 shadow-sm">
                    <p class="text-slate-800"><strong>Телефон:</strong>
                        <a class="text-[#5DA9A4] font-semibold hover:underline" href="tel:<?php echo htmlspecialchars($thc['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($thc['phone_display'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </p>
                    <p class="text-slate-800"><strong>Email:</strong>
                        <a class="hover:underline" href="mailto:<?php echo htmlspecialchars($thc['email'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($thc['email'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </p>
                    <p class="text-slate-800"><strong>Адрес:</strong> <?php echo htmlspecialchars($thc['address_primary'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="flex flex-wrap gap-3 pt-2">
                        <a href="<?php echo htmlspecialchars($thc['max_url'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="inline-flex items-center justify-center gap-2 min-h-[48px] px-4 rounded-xl bg-[#0F1C3F] text-white font-bold"
                           target="_blank" rel="noopener noreferrer">MAX</a>
                        <a href="<?php echo htmlspecialchars($thc['tg_url'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="inline-flex items-center justify-center gap-2 min-h-[48px] px-4 rounded-xl bg-[#229ED9] text-white font-bold"
                           target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-telegram" aria-hidden="true"></i> Telegram
                        </a>
                        <a href="<?php echo htmlspecialchars($thc['vk_url'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="inline-flex items-center justify-center gap-2 min-h-[48px] px-4 rounded-xl bg-[#0077FF] text-white font-bold"
                           target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-vk" aria-hidden="true"></i> VK
                        </a>
                        <a href="/frontend/window/offices.php"
                           class="inline-flex items-center justify-center gap-2 min-h-[48px] px-4 rounded-xl border-2 border-slate-200 text-slate-800 font-bold">
                            Офисы
                        </a>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-2 rounded-2xl overflow-hidden border border-slate-200 bg-white shadow-sm">
                <?php include __DIR__ . '/yandex_map_open_link.php'; ?>
            </div>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-5 sm:p-6 shadow-sm max-w-xl mx-auto">
            <?php
            $th_lead_id = 'country-lead-' . preg_replace('/[^a-z0-9\-]/i', '', $th_country_cta_source);
            $th_lead_source = $th_country_cta_source;
            $th_lead_title = 'Заявка по этой стране';
            $th_lead_sub = 'Перезвоним за 15 минут. Без спама.';
            $th_lead_submit = 'Жду звонка';
            $th_lead_show_msg = false;
            include __DIR__ . '/lead_capture.php';
            ?>
        </div>
    </div>
</section>
