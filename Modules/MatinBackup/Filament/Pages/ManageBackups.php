<?php

namespace Modules\MatinBackup\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use ZipArchive;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Filament\Forms\Components\FileUpload;
use Modules\MatinBackup\Services\BackupService;

class ManageBackups extends Page implements HasActions
{
    use InteractsWithActions;


    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static string $view = 'matinbackup::filament.pages.manage-backups';
    protected static ?string $navigationLabel = 'مدیریت بکاپ‌ها';
    protected static ?string $title = 'مدیریت بکاپ‌ها';
    protected static ?string $slug = 'matin-backups';
    protected static ?string $navigationGroup = 'سیستم';

    public function createBackupAction(): Action
    {
        return Action::make('createBackup')
            ->label('ایجاد بکاپ جدید')
            ->action(fn () => $this->createBackup())
            ->color('success');
    }

    public function uploadBackupAction(): Action
    {
        return Action::make('uploadBackup')
            ->label('آپلود فایل بکاپ')
            ->form([
                FileUpload::make('backup_file')
                    ->label('فایل بکاپ (.zip)')
                    ->disk('local')
                    ->directory('temp_uploads')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->maxSize(1024 * 1024) // 1GB
                    ->required(),
            ])
            ->action(fn (array $data) => $this->saveUploadedBackup($data['backup_file']))
            ->color('primary')
            ->modalHeading('آپلود فایل بکاپ')
            ->modalDescription('فایل بکاپ را انتخاب کنید. پس از آپلود، می‌توانید آن را از لیست بازگردانی کنید.')
            ->modalSubmitActionLabel('آپلود');
    }

    public function saveUploadedBackup($uploadedFile)
    {
        try {
            $sourcePath = Storage::disk('local')->path($uploadedFile);
            $filename = basename($uploadedFile);
            $destinationPath = storage_path('app/backups/' . $filename);
            
            if (!File::exists(storage_path('app/backups'))) {
                File::makeDirectory(storage_path('app/backups'), 0755, true);
            }

            if (File::exists($destinationPath)) {
                File::delete($destinationPath);
            }

            File::move($sourcePath, $destinationPath);

            Notification::make()
                ->title('فایل با موفقیت آپلود شد')
                ->success()
                ->send();

            $this->refreshBackups();

        } catch (\Exception $e) {
            Notification::make()
                ->title('خطا در آپلود فایل')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public $backups = [];


    public function mount(): void
    {
        $this->refreshBackups();
    }

    public function refreshBackups()
    {
        $this->backups = [];
        $backupPath = storage_path('app/backups');

        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $files = File::files($backupPath);
        foreach ($files as $file) {
            if ($file->getExtension() === 'zip') {
                $this->backups[] = [
                    'path' => $file->getPathname(),
                    'name' => $file->getFilename(),
                    'size' => $this->formatSize($file->getSize()),
                    'date' => date('Y-m-d H:i:s', $file->getMTime()),
                    'timestamp' => $file->getMTime(),
                ];
            }
        }
        
        // Sort by date desc
        usort($this->backups, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
    }

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


    
    public function restoreBackup($filename)
    {
        try {
            $path = storage_path('app/backups/' . $filename);
            $this->performRestore($path);

            Notification::make()
                ->title('بازگردانی با موفقیت انجام شد')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('خطا در بازگردانی')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function performRestore($zipPath)
    {
        $tempDir = storage_path('app/temp_restore_' . time());
        File::makeDirectory($tempDir, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) === TRUE) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            throw new \Exception("Failed to open zip file");
        }

        // 1. Restore Database
        $sqlFile = $tempDir . DIRECTORY_SEPARATOR . 'database.sql';
        if (File::exists($sqlFile)) {
            $dbConfig = config('database.connections.mysql');
            $passwordPart = !empty($dbConfig['password']) ? "-p\"{$dbConfig['password']}\"" : "";
            
            // Fix paths for Windows
            $sqlFile = str_replace('/', DIRECTORY_SEPARATOR, $sqlFile);

            $command = sprintf(
                'mysql --no-defaults --user="%s" %s --host="%s" --port="%s" "%s" < "%s" 2>&1',
                $dbConfig['username'],
                $passwordPart,
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['database'],
                $sqlFile
            );
            
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                $errorOutput = implode("\n", $output);
                throw new \Exception("Database restore failed. Exit code: $returnVar. Output: $errorOutput");
            }
        }

        // 2. Restore Public Storage
        $publicSource = $tempDir . DIRECTORY_SEPARATOR . 'public';
        if (File::exists($publicSource)) {
            $publicDest = storage_path('app/public');
            
            // Clean destination first to ensure exact replica
            // Note: Be careful with cleanDirectory, it deletes everything in it.
            if (File::exists($publicDest)) {
                 File::cleanDirectory($publicDest);
            } else {
                 File::makeDirectory($publicDest, 0755, true);
            }
            
            File::copyDirectory($publicSource, $publicDest);
        }

        // Cleanup
        File::deleteDirectory($tempDir);
    }

    public function downloadBackup($filename)
    {
        return response()->download(storage_path('app/backups/' . $filename));
    }

    public function deleteBackup($filename)
    {
        $path = storage_path('app/backups/' . $filename);
        if (File::exists($path)) {
            File::delete($path);
            Notification::make()->title('فایل حذف شد')->success()->send();
        } else {
            Notification::make()->title('فایل یافت نشد')->danger()->send();
        }
        $this->refreshBackups();
    }

    protected function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
