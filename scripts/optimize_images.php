<?php
/**
 * Скрипт для оптимизации изображений
 * Сжимает изображения и конвертирует в WebP
 * 
 * Использование: php scripts/optimize_images.php
 */

require_once __DIR__ . '/../backend/config/config.php';

class ImageOptimizer {
    private $maxWidth = 1920;
    private $maxHeight = 1080;
    private $quality = 85; // Для JPEG
    private $webpQuality = 80; // Для WebP
    
    /**
     * Оптимизировать одно изображение
     */
    public function optimize($filePath) {
        if (!file_exists($filePath)) {
            echo "Файл не найден: $filePath\n";
            return false;
        }
        
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = mime_content_type($filePath);
        
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            echo "Неподдерживаемый формат: $filePath\n";
            return false;
        }
        
        $originalSize = filesize($filePath);
        echo "Обработка: $filePath (размер: " . $this->formatBytes($originalSize) . ")\n";
        
        // Загружаем изображение
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($filePath);
                break;
            case 'png':
                $image = imagecreatefrompng($filePath);
                break;
            case 'gif':
                $image = imagecreatefromgif($filePath);
                break;
            case 'webp':
                $image = imagecreatefromwebp($filePath);
                break;
            default:
                return false;
        }
        
        if (!$image) {
            echo "Ошибка загрузки изображения\n";
            return false;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Изменяем размер, если нужно
        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
            
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Сохраняем прозрачность для PNG
            if ($ext === 'png' || $ext === 'webp') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
                imagefill($newImage, 0, 0, $transparent);
            }
            
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $newImage;
            $width = $newWidth;
            $height = $newHeight;
        }
        
        // Сохраняем оптимизированное изображение
        $saved = false;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $saved = imagejpeg($image, $filePath, $this->quality);
        } elseif ($ext === 'png') {
            $saved = imagepng($image, $filePath, 9);
        } elseif ($ext === 'webp') {
            $saved = imagewebp($image, $filePath, $this->webpQuality);
        }
        
        if ($saved) {
            $newSize = filesize($filePath);
            $savedBytes = $originalSize - $newSize;
            $percent = round(($savedBytes / $originalSize) * 100, 1);
            echo "  ✓ Оптимизировано: " . $this->formatBytes($newSize) . " (сэкономлено: " . $this->formatBytes($savedBytes) . ", $percent%)\n";
        }
        
        // Создаем WebP версию
        $webpPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $filePath);
        if ($webpPath !== $filePath && function_exists('imagewebp')) {
            imagewebp($image, $webpPath, $this->webpQuality);
            echo "  ✓ Создан WebP: $webpPath\n";
        }
        
        imagedestroy($image);
        return true;
    }
    
    /**
     * Форматирование размера файла
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Рекурсивная обработка директории
     */
    public function optimizeDirectory($dir, $recursive = true) {
        if (!is_dir($dir)) {
            echo "Директория не найдена: $dir\n";
            return;
        }
        
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        $total = count($files);
        $processed = 0;
        
        echo "Найдено изображений: $total\n\n";
        
        foreach ($files as $file) {
            if ($this->optimize($file)) {
                $processed++;
            }
            echo "\n";
        }
        
        echo "Обработано: $processed из $total\n";
        
        if ($recursive) {
            $subdirs = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            foreach ($subdirs as $subdir) {
                $this->optimizeDirectory($subdir, true);
            }
        }
    }
}

// Запуск оптимизации
if (php_sapi_name() === 'cli') {
    $optimizer = new ImageOptimizer();
    
    $directories = [
        __DIR__ . '/../frontend/window/img',
        __DIR__ . '/../img',
    ];
    
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            echo "=== Обработка директории: $dir ===\n\n";
            $optimizer->optimizeDirectory($dir);
            echo "\n";
        }
    }
    
    echo "Оптимизация завершена!\n";
} else {
    echo "Этот скрипт должен запускаться из командной строки\n";
}
