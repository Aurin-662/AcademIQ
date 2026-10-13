<?php

foreach (glob('storage/framework/views/*.php') as $f) {
    $c = file_get_contents($f);
    if (preg_match('/PATH (.*?) ENDPATH/', $c, $m)) {
        if (str_contains($m[1], 'resources')) {
            echo $m[1] . " => " . $f . "\n";
        }
    }
}
