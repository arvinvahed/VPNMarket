<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                {{ __('ایجاد کانفیگ جدید') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 md:py-12">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 lg:px-8">
            
            <x-reseller-back-button :fallbackRoute="route('reseller.configs.index')" />
            
            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative text-right" role="alert">
                    <strong class="font-bold">خطا!</strong>
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative text-right">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-3 md:p-6 text-right">
                <form action="{{ route('reseller.configs.store') }}" method="POST">
                    @csrf

                    <div class="mb-4 md:mb-6">
                        <label class="block text-xs md:text-sm font-medium mb-2 text-gray-900 dark:text-gray-100">انتخاب پنل</label>
                        <select name="panel_id" required class="w-full h-12 md:h-10 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm md:text-base">
                            <option value="">-- انتخاب کنید --</option>
                            @foreach ($panels as $panel)
                                <option value="{{ $panel->id }}">{{ $panel->name }} ({{ $panel->panel_type }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                        <div class="mb-4 md:mb-0">
                            <label class="block text-xs md:text-sm font-medium mb-2 text-gray-900 dark:text-gray-100">محدودیت ترافیک (GB)</label>
                            <input type="number" name="traffic_limit_gb" step="0.1" min="0.1" required 
                                class="w-full h-12 md:h-10 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm md:text-base"
                                placeholder="مثال: 10">
                        </div>

                        <div class="mb-4 md:mb-0">
                            <label class="block text-xs md:text-sm font-medium mb-2 text-gray-900 dark:text-gray-100">مدت اعتبار (روز)</label>
                            <input type="number" name="expires_days" min="1" required 
                                class="w-full h-12 md:h-10 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm md:text-base"
                                placeholder="مثال: 30">
                        </div>
                    </div>

                    <div class="mb-4 md:mb-6">
                        <label class="block text-xs md:text-sm font-medium mb-2 text-gray-900 dark:text-gray-100">توضیحات (اختیاری - حداکثر 200 کاراکتر)</label>
                        <input type="text" name="comment" maxlength="200" 
                            class="w-full h-12 md:h-10 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm md:text-base"
                            placeholder="توضیحات کوتاه درباره این کانفیگ">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">می‌توانید توضیحات کوتاهی برای شناسایی بهتر این کانفیگ وارد کنید</p>
                    </div>

                    @if (count($marzneshin_services) > 0)
                        <div class="mb-4 md:mb-6">
                            <label class="block text-xs md:text-sm font-medium mb-2 text-gray-900 dark:text-gray-100">سرویس‌های Marzneshin (اختیاری)</label>
                            <div class="space-y-3">
                                @foreach ($marzneshin_services as $serviceId)
                                    <label class="flex items-center text-sm md:text-base text-gray-900 dark:text-gray-100 min-h-[44px] sm:min-h-0">
                                        <input type="checkbox" name="service_ids[]" value="{{ $serviceId }}" 
                                            class="w-5 h-5 md:w-4 md:h-4 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 ml-2">
                                        <span>Service ID: {{ $serviceId }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-col sm:flex-row gap-3 md:gap-4 mt-6">
                        <button type="submit" class="w-full sm:w-auto px-4 py-3 md:py-2 h-12 md:h-10 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm md:text-base font-medium">
                            ایجاد کانفیگ
                        </button>
                        <a href="{{ route('reseller.configs.index') }}" class="w-full sm:w-auto px-4 py-3 md:py-2 h-12 md:h-10 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-center text-sm md:text-base font-medium flex items-center justify-center">
                            انصراف
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
