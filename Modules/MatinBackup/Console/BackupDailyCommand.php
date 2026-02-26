<?php

namespace Modules\MatinBackup\Console;

use Illuminate\Console\Command;
use Modules\MatinBackup\Services\BackupService;
use Illuminate\Support\Facades\Log;

class BackupDailyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:daily-telegram';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup and send it to Telegram';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting backup process...');

        try {
            $backupService = new BackupService();
            
            $this->info('Creating backup...');
            $filename = $backupService->createBackup();
            $this->info("Backup created: $filename");

            $this->info('Sending to Telegram...');
            $sent = $backupService->sendBackupToTelegram($filename);

            if ($sent) {
                $this->info('Backup sent to Telegram successfully.');
            } else {
                $this->error('Failed to send backup to Telegram. Check logs for details.');
            }

        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Log::error('Backup Command Failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
