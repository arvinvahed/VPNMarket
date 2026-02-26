<?php

// Load Laravel application
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\MatinBackup\Services\BackupService;
use Illuminate\Support\Facades\Log;

echo "Starting backup test...\n";

try {
    // Check if BackupService class exists
    if (!class_exists(BackupService::class)) {
        // Try to load it manually if autoloader fails
        $servicePath = __DIR__ . '/Modules/MatinBackup/Services/BackupService.php';
        if (file_exists($servicePath)) {
            require_once $servicePath;
            echo "Loaded BackupService manually.\n";
        } else {
            die("Error: BackupService file not found at $servicePath\n");
        }
    }

    $backupService = new BackupService();
    
    echo "Creating backup...\n";
    $filename = $backupService->createBackup();
    echo "Backup created: $filename\n";

    echo "Sending to Telegram...\n";
    $sent = $backupService->sendBackupToTelegram($filename);

    if ($sent) {
        echo "SUCCESS: Backup sent to Telegram successfully.\n";
    } else {
        echo "FAILURE: Failed to send backup to Telegram. Check logs for details.\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    Log::error('Backup Test Failed: ' . $e->getMessage());
}
