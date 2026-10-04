<?php
$dir = dirname(__DIR__) . '/frontend/window/countries';
foreach (glob($dir . '/*.php') as $f) {
    $c = file_get_contents($f);
    $start = '    <style>';
    $end = '    </style>';
    $p1 = strpos($c, $start);
    if ($p1 === false) {
        echo "skip no style: $f\n";
        continue;
    }
    $p2 = strpos($c, $end, $p1);
    if ($p2 === false) {
        echo "skip no end: $f\n";
        continue;
    }
    $p2 += strlen($end);
    $ins = "    <?php include __DIR__ . '/../../../backend/components/design_system_head.php'; ?>\n";
    $c = substr($c, 0, $p1) . $ins . substr($c, $p2);
    $c = preg_replace('/<body class="text-slate-900">/', '<body class="ds-page text-slate-900 antialiased">', $c, 1);
    file_put_contents($f, $c);
    echo "ok $f\n";
}
echo "done.\n";
