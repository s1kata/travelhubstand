<?php
/**
 * Скрипт для создания бэкапа базы данных SQLite
 * 
 * Создает резервную копию базы данных с датой и временем в имени файла
 */

require_once __DIR__ . '/../config/config.php';

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Бэкап базы данных</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .backup-db-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3ba3ff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 0 0;
        }
        .btn:hover { background: #2a8fef; }
    </style>
</head>
<body>
    <div class="backup-db-card">
        <h1>💾 Бэкап базы данных</h1>

        <?php
        $projectRoot = dirname(__DIR__, 2);
        $dataDir = $projectRoot . DIRECTORY_SEPARATOR . 'data';
        $dbPath = $dataDir . DIRECTORY_SEPARATOR . 'user_management.db';
        $backupDir = $dataDir . DIRECTORY_SEPARATOR . 'backups';
        
        // Создаем директорию для бэкапов
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        // Проверяем существование базы данных
        if (!file_exists($dbPath)) {
            echo '<div class="status error">❌ База данных не найдена: <code>' . htmlspecialchars($dbPath) . '</code></div>';
            echo '<div class="status info">База данных будет создана автоматически при первой регистрации</div>';
        } else {
            $dbSize = filesize($dbPath);
            echo '<div class="status success">✅ База данных найдена</div>';
            echo '<div class="status info">Размер: ' . number_format($dbSize) . ' байт (' . number_format($dbSize / 1024, 2) . ' KB)</div>';
            
            // Проверяем пользователей
            try {
                $pdo = new PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                echo '<div class="status info">👥 Пользователей в базе: <strong>' . $userCount . '</strong></div>';
            } catch (PDOException $e) {
                echo '<div class="status error">Ошибка проверки базы: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            
            // Создаем бэкап
            if (isset($_GET['create_backup'])) {
                $timestamp = date('Y-m-d_His');
                $backupFileName = 'user_management_backup_' . $timestamp . '.db';
                $backupPath = $backupDir . DIRECTORY_SEPARATOR . $backupFileName;
                
                if (copy($dbPath, $backupPath)) {
                    $backupSize = filesize($backupPath);
                    echo '<div class="status success">✅ Бэкап успешно создан!</div>';
                    echo '<div class="status info">';
                    echo 'Файл: <code>' . htmlspecialchars($backupFileName) . '</code><br>';
                    echo 'Размер: ' . number_format($backupSize) . ' байт<br>';
                    echo 'Путь: <code>' . htmlspecialchars($backupPath) . '</code>';
                    echo '</div>';
                } else {
                    echo '<div class="status error">❌ Не удалось создать бэкап</div>';
                }
            }
            
            // Показываем существующие бэкапы
            $backups = glob($backupDir . DIRECTORY_SEPARATOR . 'user_management_backup_*.db');
            if (!empty($backups)) {
                rsort($backups); // Сортируем по дате (новые первыми)
                echo '<h2>Существующие бэкапы</h2>';
                echo '<div class="status info">';
                echo '<table style="width: 100%; border-collapse: collapse;">';
                echo '<tr><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Файл</th><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Размер</th><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Дата</th></tr>';
                foreach ($backups as $backup) {
                    $fileName = basename($backup);
                    $fileSize = filesize($backup);
                    $fileDate = date('Y-m-d H:i:s', filemtime($backup));
                    echo '<tr>';
                    echo '<td style="padding: 8px; border-bottom: 1px solid #eee;"><code>' . htmlspecialchars($fileName) . '</code></td>';
                    echo '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . number_format($fileSize / 1024, 2) . ' KB</td>';
                    echo '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($fileDate) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                echo '</div>';
            } else {
                echo '<div class="status info">Бэкапов пока нет</div>';
            }
            
            // Кнопка создания бэкапа
            echo '<div style="margin-top: 20px;">';
            echo '<a href="?create_backup=1" class="btn">📦 Создать бэкап сейчас</a>';
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">
            <h3>💡 Рекомендации</h3>
            <ul>
                <li>Делайте бэкапы регулярно (например, раз в день)</li>
                <li>Храните бэкапы в безопасном месте</li>
                <li>Перед обновлением сайта обязательно создайте бэкап</li>
                <li>Бэкапы хранятся в <code>data/backups/</code></li>
            </ul>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="admin.php" class="btn">← Назад в админ панель</a>
            <a href="migrate_database.php" class="btn">Миграция базы</a>
        </div>
    </div>
</body>
</html>












