<?php

// List contents of Modules/MatinBackup
$modulePath = __DIR__ . '/Modules/MatinBackup';
echo "Checking contents of: " . $modulePath . "\n";

if (is_dir($modulePath)) {
    $files = scandir($modulePath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        echo "- " . $file . "\n";
        
        $subPath = $modulePath . '/' . $file;
        if (is_dir($subPath)) {
            $subFiles = scandir($subPath);
            foreach ($subFiles as $subFile) {
                if ($subFile === '.' || $subFile === '..') continue;
                echo "  - " . $subFile . "\n";
            }
        }
    }
} else {
    echo "Directory does not exist!\n";
}
