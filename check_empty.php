<?php
$empty = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__)) as $file) {
    $p = $file->getPathname();
    if (str_contains($p, '.git') || str_contains($p, 'node_modules') || str_contains($p, '.venv') || str_contains($p, 'vendor')) {
        continue;
    }
    if ($file->isFile() && $file->getSize() === 0) {
        $empty[] = $p;
    }
}
echo json_encode($empty, JSON_PRETTY_PRINT);
