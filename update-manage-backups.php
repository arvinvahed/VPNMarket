<?php

$targetFile = __DIR__ . '/Modules/MatinBackup/Filament/Pages/ManageBackups.php';

// Check if file exists
if (!file_exists($targetFile)) {
    echo "Error: File not found at $targetFile\n";
    exit(1);
}

// Read current content
$content = file_get_contents($targetFile);

// The old code block to replace
$oldCode = <<<'EOT'
    public function createBackup()
    {
        try {
            $backupService = new BackupService();
            $filename = $backupService->createBackup();

            Notification::make()
                ->title('بکاپ با موفقیت ایجاد شد')
                ->success()
                ->send();
            
            $this->refreshBackups();

        } catch (\Exception $e) {
            Notification::make()
                ->title('خطا در ایجاد بکاپ')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
EOT;

// The new code block
$newCode = <<<'EOT'
    public function createBackup()
    {
        try {
            $backupService = new BackupService();
            $filename = $backupService->createBackup();

            // Send to Telegram
            try {
                $sent = $backupService->sendBackupToTelegram($filename);
                if ($sent) {
                    Notification::make()
                        ->title('بکاپ ایجاد و به تلگرام ارسال شد')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('بکاپ ایجاد شد اما ارسال به تلگرام ناموفق بود')
                        ->warning()
                        ->send();
                }
            } catch (\Exception $e) {
                // Ignore telegram errors, backup is created
                 Notification::make()
                    ->title('بکاپ ایجاد شد (خطا در تلگرام)')
                    ->body($e->getMessage())
                    ->warning()
                    ->send();
            }
            
            $this->refreshBackups();

        } catch (\Exception $e) {
            Notification::make()
                ->title('خطا در ایجاد بکاپ')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
EOT;

// Perform replacement
$newContent = str_replace($oldCode, $newCode, $content);

// Check if replacement happened
if ($content === $newContent) {
    // If exact string match failed (due to whitespace diffs), let's try a regex or just overwrite the whole file if we are confident.
    // Since indentation might vary, let's try to overwrite the whole file with what we have locally + the modification.
    // But we don't have the full file content from server.
    // Let's try to be smarter about replacement - normalize whitespace
    
    // Normalize newlines
    $normalizedContent = str_replace("\r\n", "\n", $content);
    $normalizedOldCode = str_replace("\r\n", "\n", $oldCode);
    
    if (strpos($normalizedContent, $normalizedOldCode) !== false) {
        $newContent = str_replace($normalizedOldCode, $newCode, $normalizedContent);
    } else {
        echo "Error: Could not find the code block to replace. The file on server might be different.\n";
        // Attempt to find the function signature and replace the body roughly
        $pattern = '/public function createBackup\(\)\s*\{(?:[^{}]|(?R))*\}/s';
        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, $newCode, $content);
            echo "Replaced using regex pattern matching.\n";
        } else {
            echo "Failed to replace using regex as well.\n";
            exit(1);
        }
    }
}

// Write back to file
if (file_put_contents($targetFile, $newContent) !== false) {
    echo "ManageBackups.php updated successfully!\n";
} else {
    echo "Failed to write to ManageBackups.php\n";
}
