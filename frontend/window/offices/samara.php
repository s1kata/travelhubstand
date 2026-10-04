<?php
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/components/employee_photo_url.php';
session_start();

// Функция для получения фотографий офиса из папки
function getOfficePhotosFromFolder($city, $limit = null) {
    $photos = [];
    $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/img/offices/' . $city . '/';
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (is_dir($baseDir)) {
        $files = scandir($baseDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                continue;
            }
            $filePath = $baseDir . $file;
            if (is_file($filePath)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExtensions)) {
                    $relativePath = '/img/offices/' . $city . '/' . $file;
                    $photos[] = [
                        'image_url' => $relativePath,
                        'filename' => $file,
                        'title' => pathinfo($file, PATHINFO_FILENAME),
                        'city' => $city
                    ];
                }
            }
        }
    }

    // Сортируем по имени файла
    usort($photos, function($a, $b) {
        return strcmp($a['filename'], $b['filename']);
    });

    // Ограничиваем количество, если указано
    if ($limit !== null && count($photos) > $limit) {
        $photos = array_slice($photos, 0, $limit);
    }

    return $photos;
}

// Получаем данные офиса из БД
$office = null;
$employees = [];
$certificates = [];
$officePhotos = [];

// Получаем ID офиса из параметра
$officeId = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($pdo) {
    try {
        // Получаем информацию об офисе по ID или первый офис в городе
        if ($officeId) {
            $stmt = $pdo->prepare("SELECT * FROM offices WHERE id = ? AND city = 'samara' LIMIT 1");
            $stmt->execute([$officeId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM offices WHERE city = 'samara' LIMIT 1");
            $stmt->execute();
        }
        $office = $stmt->fetch();
        
        // Получаем сотрудников
        if ($office && isset($office['id'])) {
            $stmt = $pdo->prepare("SELECT id, office_id, name, position, phone, email, photo, info FROM office_employees WHERE office_id = ? ORDER BY name");
            $stmt->execute([$office['id']]);
            $employees = $stmt->fetchAll();
            
            // Получаем сертификаты
            $stmt = $pdo->prepare("SELECT * FROM office_certificates WHERE office_id = ? ORDER BY created_at DESC");
            $stmt->execute([$office['id']]);
            $certificates = $stmt->fetchAll();

            // Получаем фотографии офиса из папки (максимум 4)
            $officePhotos = getOfficePhotosFromFolder('samara', 4);
        }
    } catch (PDOException $e) {
        error_log('[Office] Error loading data: ' . $e->getMessage());
    }
}

// Если данных нет, используем дефолтные значения
if (!$office) {
    $office = [
        'name' => 'Офис в Самаре',
        'address' => 'Москва, Первомайская ул., 42',
        'phone' => '+7 (846) 254-16-56',
        'phone_url' => '',
        'email' => 'hello@travelhub63.ru',
        'work_hours' => 'Пн-Пт: 9:00 - 20:00, Сб-Вс: 10:00 - 18:00',
        'map_url' => 'https://yandex.ru/map-widget/v1/?um=constructor%3A68baa4f498b6c1a43d0b2de2ac4ac2740d37afb0db1f2fd56edb4b54883b81cd&source=constructor&pt=50.1018,53.2001',
        'yandex_org_id' => '1234567890'
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Офис в Самаре - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/offices-samara.css?v=1">
    <?php include __DIR__ . '/../../../backend/components/design_system_head.php'; ?>
    </head>
<body class="text-slate-900">
    <?php 
    $current_page = 'offices';
    include __DIR__ . '/../../../backend/components/header.php'; 
    ?>

    <!-- Hero Section -->
    <section class="relative py-12 sm:py-16 md:py-20 lg:py-28 bg-gradient-to-br from-sky-50 via-white to-sky-50">
        <div class="th-container mx-auto px-4 sm:px-6">
            <div class="text-center max-w-4xl mx-auto">
                <span class="inline-flex items-center gap-2 px-3 sm:px-4 py-2 rounded-full bg-sky-50 border border-sky-200 text-xs uppercase tracking-[0.2em] sm:tracking-[0.28em] text-sky-500 mb-4 sm:mb-6">Наши офисы</span>
                <h1 class="heading-font text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold text-slate-900 mb-4 sm:mb-6"><?php echo htmlspecialchars($office['name'] ?? 'Офис в Самаре'); ?></h1>
                <p class="text-base sm:text-lg md:text-xl text-slate-700 mb-6 sm:mb-8 px-2">Добро пожаловать в наш офис в Самаре. Мы всегда рады помочь вам организовать незабываемое путешествие.</p>
            </div>
        </div>
    </section>

    <!-- Contact Info Section -->
    <section class="py-8 sm:py-12 md:py-16">
        <div class="th-container mx-auto px-4 sm:px-6">
            <div class="max-w-6xl mx-auto">
                <div class="mb-6">
                    <a href="/frontend/window/offices/samara-offices.php" class="inline-flex items-center gap-2 text-sky-600 hover:text-sky-700 font-medium">
                        <i class="fas fa-arrow-left"></i>
                        <span>Вернуться к списку офисов</span>
                    </a>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
                    <!-- Contact Details -->
                    <div class="surface-card p-6 sm:p-8">
                        <h2 class="heading-font text-2xl font-bold text-slate-900 mb-6">Контактная информация</h2>
                        <div class="space-y-4">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-map-marker-alt text-white"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-900 mb-1">Адрес</h3>
                                    <p class="text-slate-700"><?php echo htmlspecialchars($office['address'] ?? 'г. Самара, ул. Московское шоссе, 18, офис 301'); ?></p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-phone text-white"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-900 mb-1">Телефон</h3>
                                    <p class="text-slate-700">
                                        <?php if (!empty($office['phone_url']) && stripos($office['phone_url'], 'max.ru') === false): ?>
                                        <a href="<?php echo htmlspecialchars($office['phone_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="hover:text-sky-500"><?php echo htmlspecialchars($office['phone'] ?? ''); ?></a>
                                        <?php else: ?>
                                        <?php
                                        require_once __DIR__ . '/../../../backend/config/contacts.php';
                                        $_thc_fb = th_contacts();
                                        $_ph_disp = $office['phone'] ?? $_thc_fb['phone_display'];
                                        $_ph_tel = preg_replace('/\D+/', '', (string) $_ph_disp);
                                        if ($_ph_tel !== '' && $_ph_tel[0] !== '+') {
                                            $_ph_tel = '+' . $_ph_tel;
                                        }
                                        ?>
                                        <a href="tel:<?php echo htmlspecialchars($_ph_tel, ENT_QUOTES, 'UTF-8'); ?>" class="hover:text-sky-500"><?php echo htmlspecialchars((string) $_ph_disp, ENT_QUOTES, 'UTF-8'); ?></a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-envelope text-white"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-900 mb-1">Email</h3>
                                    <p class="text-slate-700"><a href="mailto:<?php echo htmlspecialchars($office['email'] ?? 'hello@travelhub63.ru'); ?>" class="hover:text-sky-500"><?php echo htmlspecialchars($office['email'] ?? 'hello@travelhub63.ru'); ?></a></p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-900 mb-1">График работы</h3>
                                    <p class="text-slate-700"><?php echo htmlspecialchars($office['work_hours'] ?? 'Пн-Пт: 9:00 - 20:00, Сб-Вс: 10:00 - 18:00'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Карта (Яндекс) -->
                    <div class="surface-card p-0 overflow-hidden">
                        <?php
                        $yandex_map_open_url = $office['map_url'] ?? 'https://yandex.ru/map-widget/v1/?um=constructor%3A68baa4f498b6c1a43d0b2de2ac4ac2740d37afb0db1f2fd56edb4b54883b81cd&source=constructor&pt=50.1018,53.2001';
                        include __DIR__ . '/../../../backend/components/yandex_map_open_link.php';
                        ?>
                    </div>
                </div>

                <!-- Office Gallery Section -->
                <div class="surface-card p-6 sm:p-8 mb-12">
                    <h2 class="heading-font text-2xl sm:text-3xl font-bold text-slate-900 mb-8 text-center">Фотографии офиса</h2>
                    <?php if (!empty($officePhotos)): ?>
                        <div class="photo-gallery">
                            <?php foreach ($officePhotos as $photo): ?>
                                <div class="photo-item" onclick="openPhotoModal('<?php echo htmlspecialchars($photo['image_url']); ?>', '<?php echo htmlspecialchars($photo['title'] ?? 'Фото офиса'); ?>')">
                                    <img src="<?php echo htmlspecialchars($photo['image_url']); ?>" alt="<?php echo htmlspecialchars($photo['title'] ?? 'Фото офиса'); ?>" loading="lazy">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center">
                                <i class="fas fa-images text-white text-2xl"></i>
                            </div>
                            <p class="text-slate-700">Фотографии офиса скоро будут добавлены</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Employees Section -->
                <div class="surface-card p-6 sm:p-8 mb-12">
                    <h2 class="heading-font text-2xl sm:text-3xl font-bold text-slate-900 mb-8 text-center">Наша команда</h2>
                    <?php if (!empty($employees)): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($employees as $employee): ?>
                                <div class="employee-card surface-card p-6 text-center">
                                    <div class="w-32 h-32 rounded-lg bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center mx-auto mb-4 overflow-hidden">
                                        <?php if (!empty($employee['photo'])): ?>
                                            <img src="<?php echo htmlspecialchars(th_employee_photo_public_href($employee['photo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($employee['name']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-user text-white text-4xl"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="heading-font text-xl font-bold text-slate-900 mb-2"><?php echo htmlspecialchars($employee['name']); ?></h3>
                                    <?php if (!empty($employee['position'])): ?>
                                        <p class="text-slate-700 mb-2"><?php echo htmlspecialchars($employee['position']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($employee['phone'])): ?>
                                        <p class="text-sm text-slate-700"><a href="tel:<?php echo htmlspecialchars(str_replace([' ', '(', ')', '-'], '', $employee['phone'])); ?>" class="hover:text-sky-500"><?php echo htmlspecialchars($employee['phone']); ?></a></p>
                                    <?php endif; ?>
                                    <?php if (!empty($employee['info'])): ?>
                                        <p class="text-sm text-slate-600 mt-2"><?php echo htmlspecialchars($employee['info']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-slate-700">Информация о сотрудниках будет добавлена в ближайшее время.</p>
                    <?php endif; ?>
                </div>

                <!-- Certificates Section -->
                <div class="surface-card p-6 sm:p-8 mb-12">
                    <h2 class="heading-font text-2xl sm:text-3xl font-bold text-slate-900 mb-8 text-center">Сертификаты и лицензии</h2>
                    <?php if (!empty($certificates)): ?>
                        <div class="certificate-grid">
                            <?php foreach ($certificates as $cert): ?>
                                <div class="certificate-item" onclick="openCertificateModal('<?php echo htmlspecialchars($cert['image_url']); ?>', '<?php echo htmlspecialchars($cert['title'] ?? 'Сертификат'); ?>')">
                                    <img src="<?php echo htmlspecialchars($cert['image_url']); ?>" alt="<?php echo htmlspecialchars($cert['title'] ?? 'Сертификат'); ?>" loading="lazy">
                                    <?php if (!empty($cert['title'])): ?>
                                        <p class="mt-2 text-sm font-semibold text-slate-900 text-center"><?php echo htmlspecialchars($cert['title']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-slate-700">Сертификаты будут добавлены в ближайшее время.</p>
                    <?php endif; ?>
                </div>

                <?php include __DIR__ . '/../../../backend/components/office_popular_destinations.php'; ?>
            </div>
        </div>
    </section>

    <!-- Yandex Reviews Widget Section -->
    <section class="py-8 sm:py-12 md:py-16 bg-white office-reviews-section">
        <div class="th-container mx-auto px-4 sm:px-6">
            <div class="max-w-6xl mx-auto">
                <div class="surface-card p-6 sm:p-8">
                    <h2 class="heading-font text-2xl sm:text-3xl font-bold text-slate-900 mb-6 text-center">Отзывы наших клиентов</h2>
                    <p class="text-center text-slate-700 mb-8">Читайте отзывы о нашей работе на Яндекс.Картах</p>
                    <?php
                    $reviews_yandex_query = isset($office['name']) ? (string) $office['name'] : 'Travel Hub Самара';
                    include __DIR__ . '/../../../backend/components/reviews_yandex_link.php';
                    ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Certificate Modal -->
    <div id="certificateModal" class="hidden fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4" onclick="closeCertificateModal()">
        <div class="bg-white rounded-xl max-w-4xl max-h-[90vh] overflow-auto" onclick="event.stopPropagation()">
            <div class="p-4 flex justify-end items-center">
                <button onclick="closeCertificateModal()" class="text-slate-700 hover:text-slate-800">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div class="p-4">
                <img id="modalImage" src="" alt="Сертификат Travel Hub (Самара)" class="w-full h-auto rounded-lg">
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../backend/components/footer.php'; ?>


    <script>
        function openCertificateModal(imageUrl, title) {
            document.getElementById('modalImage').src = imageUrl;
            document.getElementById('certificateModal').classList.remove('hidden');
            if (window.THMobile && window.THMobile.lockScroll) window.THMobile.lockScroll(true);
        }
        
        function openPhotoModal(imageUrl, title) {
            document.getElementById('modalImage').src = imageUrl;
            document.getElementById('certificateModal').classList.remove('hidden');
            if (window.THMobile && window.THMobile.lockScroll) window.THMobile.lockScroll(true);
        }

        function closeCertificateModal() {
            document.getElementById('certificateModal').classList.add('hidden');
            if (window.THMobile && window.THMobile.lockScroll) window.THMobile.lockScroll(false);
        }

        // Закрытие по Escape

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCertificateModal();
            }
        });
    </script>

</body>
</html>