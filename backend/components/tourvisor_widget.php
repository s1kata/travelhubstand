<?php $widget_lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ru'; ?>
<section class="py-16 bg-white">
    <div class="th-container mx-auto px-6">
        <div class="text-center mb-8 space-y-3">
            <h2 class="heading-font text-3xl md:text-4xl font-bold text-slate-900">
                <?php echo $widget_lang === 'ru' ? 'Подберём тур под ваш отпуск' : 'Find the perfect trip for your getaway'; ?>
            </h2>
            <p class="text-slate-600 max-w-2xl mx-auto">
                <?php echo $widget_lang === 'ru'
                    ? 'Заполните форму — и мы предложим варианты по вашим датам и бюджету'
                    : 'Fill out the form and we will suggest options for your dates and budget'; ?>
            </p>
        </div>
        <div class="max-w-7xl mx-auto bg-white rounded-3xl shadow-2xl p-8 md:p-10 border border-sky-100">
            <?php
            $tourvisor_widget_module = 'search';
            $tourvisor_widget_container_id = 'tourvisor-widget-block';
            include __DIR__ . '/tourvisor_widget_embed.php';
            ?>
        </div>
    </div>
</section>
