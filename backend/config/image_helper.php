<?php
/**
 * Image optimization helper
 * Provides functions for optimized image output with WebP support and lazy loading
 */

/**
 * Get WebP version of an image path
 * 
 * @param string $originalPath Original image path
 * @return string|null WebP path if exists, null otherwise
 */
function getWebPPath(string $originalPath): ?string {
    // Check if original path is external URL
    if (strpos($originalPath, 'http://') === 0 || strpos($originalPath, 'https://') === 0) {
        return null;
    }
    
    // Remove leading slash if present
    $path = ltrim($originalPath, '/');
    
    // Get file info
    $pathInfo = pathinfo($path);
    $webPPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
    
    // Check if WebP version exists
    $fullWebPPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $webPPath;
    if (file_exists($fullWebPPath)) {
        return '/' . $webPPath;
    }
    
    return null;
}

/**
 * Output optimized image tag with WebP support and lazy loading
 * 
 * @param string $src Original image source
 * @param string $alt Alt text
 * @param array $attributes Additional HTML attributes
 * @param bool $lazy Enable lazy loading (default: true for non-critical images)
 * @param bool $critical Is this a critical image (above the fold)? (default: false)
 * @return string HTML img tag
 */
function optimizedImage(
    string $src,
    string $alt = '',
    array $attributes = [],
    bool $lazy = true,
    bool $critical = false
): string {
    $webPPath = getWebPPath($src);
    
    // Build attributes
    $attrs = $attributes;
    
    // Set loading attribute
    if ($critical) {
        $attrs['loading'] = 'eager';
        $attrs['fetchpriority'] = 'high';
    } else {
        $attrs['loading'] = $lazy ? 'lazy' : 'eager';
    }
    
    // Set decoding
    if (!isset($attrs['decoding'])) {
        $attrs['decoding'] = 'async';
    }
    
    // Build attributes string
    $attrString = '';
    foreach ($attrs as $key => $value) {
        if ($value === true) {
            $attrString .= ' ' . htmlspecialchars($key);
        } elseif ($value !== false && $value !== null) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }
    }
    
    // Build HTML
    // If WebP is available, use picture element, otherwise use regular img
    if ($webPPath) {
        $html = '<picture>';
        $html .= '<source srcset="' . htmlspecialchars($webPPath) . '" type="image/webp">';
        $html .= '<img src="' . htmlspecialchars($src) . '"';
        if ($alt !== '') {
            $html .= ' alt="' . htmlspecialchars($alt) . '"';
        }
        $html .= $attrString;
        $html .= '>';
        $html .= '</picture>';
    } else {
        // No WebP, use regular img tag
        $html = '<img src="' . htmlspecialchars($src) . '"';
        if ($alt !== '') {
            $html .= ' alt="' . htmlspecialchars($alt) . '"';
        }
        $html .= $attrString;
        $html .= '>';
    }
    
    return $html;
}

/**
 * Check if browser supports WebP
 * 
 * @return bool
 */
function supportsWebP(): bool {
    if (!isset($_SERVER['HTTP_ACCEPT'])) {
        return false;
    }
    
    return strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
}

/**
 * Convert image to WebP format
 * Requires GD library with WebP support or ImageMagick
 * 
 * @param string $sourcePath Source image path
 * @param int $quality WebP quality (0-100, default: 85)
 * @return bool|string WebP path on success, false on failure
 */
function convertToWebP(string $sourcePath, int $quality = 85): bool|string {
    // Check if source exists
    if (!file_exists($sourcePath)) {
        return false;
    }
    
    // Get image info
    $info = getimagesize($sourcePath);
    if ($info === false) {
        return false;
    }
    
    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];
    
    // Create output path
    $pathInfo = pathinfo($sourcePath);
    $webPPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
    
    // Skip if WebP already exists
    if (file_exists($webPPath)) {
        return $webPPath;
    }
    
    // Load image based on type
    $image = null;
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            // Preserve transparency for PNG
            imagealphablending($image, false);
            imagesavealpha($image, true);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    if ($image === false) {
        return false;
    }
    
    // Convert to WebP
    $result = imagewebp($image, $webPPath, $quality);
    imagedestroy($image);
    
    if ($result) {
        return $webPPath;
    }
    
    return false;
}

