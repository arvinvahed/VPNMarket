<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('همه اعلان‌ها') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @if($notifications->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 text-center py-4">
                            هیچ اعلانی برای نمایش وجود ندارد.
                        </p>
                    @else
                        <div class="space-y-4">
                            @foreach ($notifications as $notification)
                                <div x-data="{ notificationId: {{ $notification->id }}, isRead: {{ $notification->is_read ? 'true' : 'false' }}, deleted: false }"
                                     x-show="!deleted"
                                     :class="{ 'bg-gray-50 dark:bg-gray-700': !isRead, 'bg-white dark:bg-gray-800 opacity-70': isRead }"
                                     class="relative p-5 rounded-xl shadow-md transition-all duration-300 transform hover:scale-[1.005] hover:shadow-lg">

                                    {{-- دکمه حذف --}}
                                    <button @click.prevent="
                                                if(confirm('آیا مطمئن هستید که می‌خواهید این اعلان را حذف کنید؟')) {
                                                    fetch('{{ route('notifications.destroy', $notification->id) }}', {
                                                        method: 'DELETE',
                                                        headers: {
                                                            'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content'),
                                                            'Content-Type': 'application/json'
                                                        }
                                                    })
                                                    .then(response => response.json())
                                                    .then(data => {
                                                        if (data.success) {
                                                            deleted = true; // مخفی کردن اعلان
                                                            // Optional: decrement unread count in header if needed via custom event or full reload
                                                            // window.location.reload(); // If you prefer a full reload
                                                        } else {
                                                            alert('خطا در حذف اعلان.');
                                                        }
                                                    })
                                                    .catch(error => {
                                                        console.error('Error deleting notification:', error);
                                                        alert('خطا در حذف اعلان.');
                                                    });
                                                }
                                            "
                                            class="absolute top-3 left-3 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-600 focus:outline-none"
                                            title="حذف اعلان">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm1 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    {{-- دکمه علامت‌گذاری به عنوان خوانده شده (فقط برای نخوانده‌ها) --}}
                                    <template x-if="!isRead">
                                        <button @click.prevent="
                                                    fetch('{{ route('notifications.mark-as-read', $notification->id) }}', {
                                                        method: 'POST',
                                                        headers: {
                                                            'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content'),
                                                            'Content-Type': 'application/json'
                                                        }
                                                    })
                                                    .then(response => response.json())
                                                    .then(data => {
                                                        if (data.success) {
                                                            isRead = true; // تغییر وضعیت در Alpine
                                                            // Optional: decrement unread count in header if needed
                                                        } else {
                                                            alert('خطا در علامت‌گذاری اعلان به عنوان خوانده شده.');
                                                        }
                                                    })
                                                    .catch(error => {
                                                        console.error('Error marking notification as read:', error);
                                                        alert('خطا در علامت‌گذاری اعلان به عنوان خوانده شده.');
                                                    });
                                                "
                                                class="absolute top-3 left-10 ml-2 text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-600 focus:outline-none"
                                                title="علامت‌گذاری به عنوان خوانده شده">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                    </template>


                                    {{-- محتوای اعلان --}}
                                    <a :href="{{ $notification->link ? '$notification->link' : "'#'" }}"
                                       @click="if(!isRead) { // فقط در صورتی که نخوانده باشد، علامت‌گذاری به عنوان خوانده شده
                                                    fetch('{{ route('notifications.mark-as-read', $notification->id) }}', {
                                                        method: 'POST',
                                                        headers: {
                                                            'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content'),
                                                            'Content-Type': 'application/json'
                                                        }
                                                    })
                                                    .then(response => response.json())
                                                    .then(data => {
                                                        if (data.success) {
                                                            isRead = true;
                                                        }
                                                    })
                                                    .catch(error => console.error('Error marking notification as read on click:', error));
                                                }"
                                       class="block pr-10"> {{-- pr-10 برای جبران فضای دکمه‌ها --}}
                                        <p class="font-bold text-lg" :class="{ 'text-indigo-600 dark:text-indigo-400': !isRead, 'text-gray-700 dark:text-gray-300': isRead }">{{ $notification->title }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ Str::limit($notification->message, 200) }}</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 block mt-2">{{ $notification->created_at->diffForHumans() }}</span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-8">
                        {{ $notifications->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
