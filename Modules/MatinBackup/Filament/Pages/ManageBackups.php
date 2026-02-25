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
            ->label('آپلود و ریستور')
            ->form([
                FileUpload::make('backup_file')
                    ->label('فایل بکاپ (.zip)')
                    ->disk('local')
                    ->directory('temp_uploads')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->maxSize(1024 * 1024) // 1GB
                    ->required(),
            ])
            ->action(fn (array $data) => $this->restoreFromUpload($data['backup_file']))
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('آپلود و بازگردانی بکاپ')
            ->modalDescription('آیا مطمئن هستید؟ این کار تمام اطلاعات فعلی را پاک کرده و با اطلاعات فایل بکاپ جایگزین می‌کند. این عملیات غیرقابل بازگشت است!')
            ->modalSubmitActionLabel('شروع عملیات ریستور');
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
            
            // Using process for better error handling could be better but exec is simple
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

    public function restoreFromUpload($uploadedFile)
    {
        try {
            $path = Storage::disk('local')->path($uploadedFile);
            $this->performRestore($path);
            
            // Delete uploaded file
            Storage::disk('local')->delete($uploadedFile);

            Notification::make()
                ->title('بازگردانی با موفقیت انجام شد')
                ->success()
                ->send();

            $this->refreshBackups();

        } catch (\Exception $e) {
            Notification::make()
                ->title('خطا در بازگردانی')
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
        Storage::delete('backups/' . $filename);
        $this->refreshBackups();
        Notification::make()->title('فایل حذف شد')->success()->send();
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
