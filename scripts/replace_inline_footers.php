<?php
/**
 * Заменяет inline <footer>...</footer> на include общего footer.php
 */
$root = dirname(__DIR__);
$replacements = [
    // frontend/window/countries/*.php
    [
        'glob' => $root . '/frontend/window/countries/*.php',
        'include' => "<?php include __DIR__ . '/../../../backend/components/footer.php'; ?>\n",
    ],
    // frontend/window/*.php (корень window)
    [
        'glob' => $root . '/frontend/window/*.php',
        'include' => "<?php include __DIR__ . '/../../backend/components/footer.php'; ?>\n",
    ],
    // frontend/window/offices/*.php
    [
        'glob' => $root . '/frontend/window/offices/*.php',
        'include' => "<?php include __DIR__ . '/../../../backend/components/footer.php'; ?>\n",
    ],
];

foreach ($replacements as $cfg) {
    foreach (glob($cfg['glob']) ?: [] as $file) {
        if (basename($file) === 'country.php') {
            // шаблон может отличаться — пропускаем, правим вручную при необходимости
        }
        $c = file_get_contents($file);
        if (strpos($c, 'backend/components/footer.php') !== false) {
            echo "skip (already include): $file\n";
            continue;
        }
        $new = preg_replace('/<!--\s*Footer\s*-->[\s\S]*?<\/footer>/i', $cfg['include'], $c, 1, $count);
        if ($count === 0) {
            echo "skip (no footer block): $file\n";
            continue;
        }
        if ($new !== $c) {
            file_put_contents($file, $new);
            echo "ok: $file\n";
        }
    }
}
echo "done.\n";
