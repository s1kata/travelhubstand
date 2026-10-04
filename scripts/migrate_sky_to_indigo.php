<?php
/**
 * UI-only: Tailwind sky-* → indigo (главная) в перечисленных PHP.
 */
$files = array_merge(
    glob(dirname(__DIR__) . '/frontend/window/countries/*.php') ?: [],
    [
        dirname(__DIR__) . '/frontend/window/countries-list.php',
    ]
);

$replacements = [
    'relative bg-gradient-to-br from-sky-100 via-white to-sky-50' => 'ds-country-hero relative bg-gradient-to-br from-indigo-50 via-white to-slate-50',
    'bg-gradient-to-br from-sky-100 via-white to-sky-50' => 'bg-gradient-to-br from-indigo-50 via-white to-slate-50',
    'bg-gradient-to-b from-sky-400 to-sky-600' => 'bg-gradient-to-b from-indigo-500 to-indigo-700',
    'from-sky-400 to-sky-600' => 'from-indigo-500 to-indigo-700',
    'bg-sky-100 text-sky-500' => 'bg-indigo-50 text-indigo-600',
    'hover:text-sky-400' => 'hover:text-indigo-300',
    'bg-sky-100/60' => 'bg-indigo-50/70',
    'hover:bg-sky-600' => 'hover:bg-indigo-600',
    'group-hover:text-sky-600' => 'group-hover:text-indigo-600',
    'border-sky-200' => 'border-indigo-100',
    'border-sky-100' => 'border-indigo-100',
    'hover:bg-sky-100' => 'hover:bg-indigo-50',
    'hover:text-sky-500' => 'hover:text-indigo-600',
    'text-sky-600' => 'text-indigo-600',
    'text-sky-500' => 'text-indigo-600',
    'heading-font text-lg font-semibold text-white mb-2 flex items-center gap-2' => 'heading-font text-lg font-semibold text-slate-900 mb-2 flex items-center gap-2',
];

foreach ($files as $f) {
    if (!is_file($f)) {
        continue;
    }
    $c = file_get_contents($f);
    $orig = $c;
    foreach ($replacements as $from => $to) {
        $c = str_replace($from, $to, $c);
    }
    if ($c !== $orig) {
        file_put_contents($f, $c);
        echo "updated: $f\n";
    } else {
        echo "unchanged: $f\n";
    }
}
echo "done.\n";
