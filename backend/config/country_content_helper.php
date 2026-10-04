<?php
/**
 * Общая функция для загрузки контента стран из БД
 * Используется на всех страницах стран
 */

/**
 * Убирает битые байты и символ замены U+FFFD (часто после неверной кодировки в админке).
 */
function country_content_clean_utf8(string $text): string
{
    if ($text === '') {
        return '';
    }
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
    $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
    if ($converted !== false) {
        $text = $converted;
    }
    return preg_replace('/\x{FFFD}/u', '', $text);
}

/**
 * Био из БД не использовать, если строка не UTF-8 или уже содержит символ замены.
 */
function country_content_db_bio_is_unreliable(string $bio): bool
{
    if ($bio === '') {
        return true;
    }
    if (!mb_check_encoding($bio, 'UTF-8')) {
        return true;
    }
    return (bool) preg_match('/\x{FFFD}/u', $bio);
}

function loadCountryContentFromDB($pdo, $slug) {
    if (!$pdo) return null;
    
    try {
        // Проверяем существование таблицы
        $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='country_content'");
        if (!$tableCheck->fetchColumn()) {
            return null;
        }
        
        $stmt = $pdo->prepare('SELECT * FROM country_content WHERE country_slug = ?');
        $stmt->execute([$slug]);
        $content = $stmt->fetch();
        
        if ($content) {
            return [
                'bio' => $content['bio'],
                'highlights' => $content['highlights'] ? json_decode($content['highlights'], true) : null,
                'useful_info' => $content['useful_info'] ? json_decode($content['useful_info'], true) : null,
                'detailed_info' => $content['detailed_info'] ? json_decode($content['detailed_info'], true) : null,
            ];
        }
    } catch (PDOException $e) {
        error_log('[country_content_helper] Error loading content from DB: ' . $e->getMessage());
    }
    
    return null;
}

/**
 * Применяет контент из БД к массиву данных страны
 */
function applyCountryContentFromDB($pdo, $slug, &$countryData) {
    $dbContent = loadCountryContentFromDB($pdo, $slug);
    
    if ($dbContent) {
        // Обновляем данные из БД
        if (!empty($dbContent['bio']) && is_string($dbContent['bio'])) {
            if (!country_content_db_bio_is_unreliable($dbContent['bio'])) {
                $countryData['bio'] = country_content_clean_utf8($dbContent['bio']);
            }
            // иначе оставляем bio из PHP-файла (статический текст без битой кодировки)
        }
        if (!empty($dbContent['highlights']) && is_array($dbContent['highlights'])) {
            $countryData['highlights'] = $dbContent['highlights'];
        }
        if (!empty($dbContent['useful_info'])) {
            if (!empty($dbContent['useful_info']['bestTime'])) {
                $countryData['bestTime'] = $dbContent['useful_info']['bestTime'];
            }
            if (!empty($dbContent['useful_info']['currency'])) {
                $countryData['currency'] = $dbContent['useful_info']['currency'];
            }
            if (!empty($dbContent['useful_info']['language'])) {
                $countryData['language'] = $dbContent['useful_info']['language'];
            }
            if (!empty($dbContent['useful_info']['visa'])) {
                $countryData['visa'] = $dbContent['useful_info']['visa'];
            }
        }
        if (!empty($dbContent['detailed_info'])) {
            $countryData['detailedInfo'] = $dbContent['detailed_info'];
        }
    }
}

























