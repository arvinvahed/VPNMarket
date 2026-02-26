<?php

namespace Modules\MatinBackup\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use ZipArchive;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Log;

class BackupService
{
    public function createBackup(): string
    {
        $filename = 'backup-' . date('Y-m-d-H-i-s') . '.zip';
        $tempDir = storage_path('app/temp_backup_' . time());
        $backupPath = storage_path('app/backups/' . $filename);
        
        if (!File::exists(storage_path('app/backups'))) {
            File::makeDirectory(storage_path('app/backups'), 0755, true);
        }
        File::makeDirectory($tempDir, 0755, true);

        // 1. Dump Database
        $dbConfig = config('database.connections.mysql');
        $dumpFile = $tempDir . DIRECTORY_SEPARATOR . 'database.sql';
        
        // Metadata
        $metadata = [
            'created_at' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
        ];
        file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));

        // Password might be empty or special chars, handle carefully
        $passwordPart = !empty($dbConfig['password']) ? "-p\"{$dbConfig['password']}\"" : "";
        
        // Fix paths for Windows
        $dumpFile = str_replace('/', DIRECTORY_SEPARATOR, $dumpFile);
        
        // Add --column-statistics=0 for MySQL 8 compatibility and --no-defaults to avoid auth issues
        $command = sprintf(
            'mysqldump --no-defaults --column-statistics=0 --user="%s" %s --host="%s" --port="%s" "%s" > "%s"',
            $dbConfig['username'],
            $passwordPart,
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['database'],
            $dumpFile
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new \Exception("Database dump failed. Command: $command");
        }

        // 2. Zip Files
        $zip = new ZipArchive();
        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new \Exception("Cannot create zip file at $backupPath");
        }

        // Add Database
        if (File::exists($dumpFile)) {
            $zip->addFile($dumpFile, 'database.sql');
        } else {
            throw new \Exception("Database dump file not found at: $dumpFile");
        }

        if (File::exists($tempDir . DIRECTORY_SEPARATOR . 'metadata.json')) {
            $zip->addFile($tempDir . DIRECTORY_SEPARATOR . 'metadata.json', 'metadata.json');
        }

        // Add Public Storage
        $publicPath = storage_path('app/public');
        if (File::exists($publicPath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($publicPath),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = 'public/' . substr($filePath, strlen($publicPath) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        $zip->close();

        // Cleanup
        File::deleteDirectory($tempDir);

        return $filename;
    }

    public function sendBackupToTelegram($filename)
    {
        try {
            $settings = Setting::all()->pluck('value', 'key');
            $botToken = $settings->get('telegram_bot_token');
            $chatId = $settings->get('telegram_admin_chat_id');

            if (!$botToken || !$chatId) {
                Log::warning('Telegram bot token or Admin Chat ID not set for backup sending.');
                return false;
            }

            Telegram::setAccessToken($botToken);

            $filePath = storage_path('app/backups/' . $filename);

            if (!File::exists($filePath)) {
                Log::error("Backup file not found for sending: $filePath");
                return false;
            }

            Telegram::sendDocument([
                'chat_id' => $chatId,
                'document' => \Telegram\Bot\FileUpload\InputFile::create($filePath, $filename),
                'caption' => "📦 *بکاپ جدید سایت*\n\n📅 تاریخ: " . now()->format('Y-m-d H:i:s'),
                'parse_mode' => 'Markdown',
            ]);

            Log::info("Backup sent to Telegram successfully: $filename");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send backup to Telegram: " . $e->getMessage());
            return false;
        }
    }
}
