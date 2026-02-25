<x-filament-panels::page>
    <div class="flex justify-end mb-4">
        {{ $this->createBackupAction }}
        {{ $this->uploadBackupAction }}
    </div>

    <div class="overflow-x-auto bg-white rounded-lg shadow dark:bg-gray-800">
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-6 py-3">نام فایل</th>
                    <th scope="col" class="px-6 py-3">تاریخ</th>
                    <th scope="col" class="px-6 py-3">حجم</th>
                    <th scope="col" class="px-6 py-3 text-center">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($backups as $backup)
                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            {{ $backup['name'] }}
                        </td>
                        <td class="px-6 py-4 ltr">
                            {{ $backup['date'] }}
                        </td>
                        <td class="px-6 py-4 ltr">
                            {{ $backup['size'] }}
                        </td>
                        <td class="px-6 py-4 text-center space-x-2">
                            <x-filament::button
                                color="success"
                                size="sm"
                                wire:click="downloadBackup('{{ $backup['name'] }}')"
                            >
                                دانلود
                            </x-filament::button>

                            <x-filament::button
                                color="warning"
                                size="sm"
                                wire:click="restoreBackup('{{ $backup['name'] }}')"
                                wire:confirm="آیا از بازگردانی این بکاپ اطمینان دارید؟ اطلاعات فعلی حذف خواهند شد!"
                            >
                                ریستور
                            </x-filament::button>

                            <x-filament::button
                                color="danger"
                                size="sm"
                                wire:click="deleteBackup('{{ $backup['name'] }}')"
                                wire:confirm="آیا از حذف این فایل اطمینان دارید؟"
                            >
                                حذف
                            </x-filament::button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center">هیچ فایل بکاپی یافت نشد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
