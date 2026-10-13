<?php

$transcriptPath = 'C:/Users/Aurin/.gemini/antigravity-ide/brain/82fa5a4a-002f-4ecd-b8d9-d7315d33dd2e/.system_generated/logs/transcript.jsonl';

if (!file_exists($transcriptPath)) {
    die("Transcript not found\n");
}

$fileContents = [];

$handle = fopen($transcriptPath, 'r');
if (!$handle) {
    die("Could not open transcript\n");
}

$count = 0;
while (($line = fgets($handle)) !== false) {
    $data = json_decode($line, true);
    if (!$data) continue;

    if (isset($data['tool_calls']) && is_array($data['tool_calls'])) {
        foreach ($data['tool_calls'] as $tc) {
            $name = $tc['name'] ?? ($tc['function']['name'] ?? '');
            $args = $tc['args'] ?? ($tc['function']['arguments'] ?? null);
            if (is_string($args)) {
                $args = json_decode($args, true);
            }
            if ($name === 'write_to_file' && is_array($args)) {
                $target = $args['TargetFile'] ?? null;
                $content = $args['CodeContent'] ?? null;
                if ($target && $content !== null && strlen($content) > 0) {
                    $normTarget = str_replace('/', DIRECTORY_SEPARATOR, $target);
                    $fileContents[$normTarget] = $content;
                    $count++;
                }
            }
        }
    }
}
fclose($handle);

echo "Found " . count($fileContents) . " unique files with written contents across {$count} write calls.\n";

$restored = 0;
foreach ($fileContents as $file => $content) {
    if (file_exists($file)) {
        if (filesize($file) === 0) {
            file_put_contents($file, $content);
            echo "Restored empty file: {$file} (" . strlen($content) . " bytes)\n";
            $restored++;
        }
    } else {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($file, $content);
        echo "Created missing file: {$file} (" . strlen($content) . " bytes)\n";
        $restored++;
    }
}

echo "Total restored: {$restored}\n";
