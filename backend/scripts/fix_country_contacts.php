<?php
/**
 * One-shot: replace dead WA + contact blocks on country pages.
 * Run: php backend/scripts/fix_country_contacts.php
 */
declare(strict_types=1);

$dir = realpath(__DIR__ . '/../../frontend/window/countries');
if ($dir === false) {
    fwrite(STDERR, "countries dir not found\n");
    exit(1);
}

$files = glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
$replacedSections = 0;
$touched = 0;

foreach ($files as $path) {
    $c = file_get_contents($path);
    if ($c === false) {
        continue;
    }
    $orig = $c;
    $slug = pathinfo($path, PATHINFO_FILENAME);

    $c = str_replace(
        [
            'https://wa.me/70000000000',
            'https://wa.me/74956603666',
            'tel:+74956603666',
            '+7 (495) 660-36-66',
        ],
        [
            'https://wa.me/78462541656',
            'https://wa.me/78462541656',
            'tel:+78462541656',
            '+7 (846) 254-16-56',
        ],
        $c
    );

    $pattern = '/\s*<!-- Contact Info with Map -->\s*<section id="contact".*?<\/section>\s*(?=<\?php\s+include\s+.*?footer\.php)/s';
    $replacement = "\n    <?php \$th_country_cta_source = 'country_{$slug}'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>\n\n    ";
    $new = preg_replace($pattern, $replacement, $c, 1, $count);
    if ($new !== null && $count > 0) {
        $c = $new;
        $replacedSections++;
    }

    if ($c !== $orig) {
        file_put_contents($path, $c);
        $touched++;
        echo "updated: {$slug}.php\n";
    }
}

echo "sections replaced: {$replacedSections}\n";
echo "files touched: {$touched}\n";
