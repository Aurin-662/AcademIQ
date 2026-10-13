<?php

$transcriptPath = 'C:/Users/Aurin/.gemini/antigravity-ide/brain/82fa5a4a-002f-4ecd-b8d9-d7315d33dd2e/.system_generated/logs/transcript.jsonl';

$handle = fopen($transcriptPath, 'r');
while (($line = fgets($handle)) !== false) {
    if (str_contains($line, 'write_to_file')) {
        $data = json_decode($line, true);
        echo "Line keys: " . implode(', ', array_keys($data)) . "\n";
        echo "Type: " . ($data['type'] ?? '') . "\n";
        echo "Sample data: " . substr(json_encode($data), 0, 500) . "\n";
        break;
    }
}
fclose($handle);
