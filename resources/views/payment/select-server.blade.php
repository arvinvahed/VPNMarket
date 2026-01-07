<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200">
            🎯 انتخاب موقعیت مکانی سرور
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto px-4">

            <!-- Hero -->
            <div class="mb-10 rounded-3xl bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600
                        text-white p-8 shadow-2xl relative overflow-hidden">
                <div class="absolute inset-0 bg-white/10 backdrop-blur"></div>
                <div class="relative z-10 text-center">
                    <h1 class="text-3xl font-extrabold mb-3">🌍 انتخاب بهترین سرور</h1>
                    <p class="text-lg opacity-90">
                        پلن انتخابی شما:
                        <span class="px-3 py-1 bg-white/20 rounded-xl font-bold">
                            {{ $plan->name }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Error -->
            @if(session('error'))
                <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20
                            border border-red-300 text-red-700 dark:text-red-300">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Servers -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                @forelse($locations as $location)
                    @php
                        $bestServer = $location->servers
                            ->where('is_active', true)
                            ->where('current_users', '<', 'capacity')
                            ->sortBy('current_users')
                            ->first();

                        $totalCapacity = $location->servers->sum('capacity');
                        $totalUsed = $location->servers->sum('current_users');
                        $available = max(0, $totalCapacity - $totalUsed);
                        $isFull = $available <= 0;
                        $percentage = $totalCapacity ? round(($totalUsed / $totalCapacity) * 100) : 0;
                    @endphp

                    <div class="relative rounded-2xl border bg-white dark:bg-gray-900
                                border-gray-200 dark:border-gray-700 p-6
                                transition hover:shadow-2xl hover:-translate-y-1 touch-manipulation">

                        <!-- Status -->
                        <div class="absolute top-4 left-4">
                            @if($isFull)
                                <span class="px-3 py-1 text-xs rounded-full bg-red-500 text-white font-bold">
                                    ظرفیت پر
                                </span>
                            @else
                                <span class="px-3 py-1 text-xs rounded-full bg-green-500 text-white font-bold animate-pulse">
                                    فعال
                                </span>
                            @endif
                        </div>

                        <!-- Header -->
                        <div class="flex flex-col items-center text-center gap-2 mt-6 mb-5">
                            <div class="text-6xl">{{ $location->flag }}</div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                {{ $location->name }}
                            </h3>
                            <p class="text-sm text-gray-500">
                                {{ $location->servers->count() }} سرور
                            </p>
                        </div>

                        <!-- Capacity -->
                        <div class="mb-5">
                            <div class="flex justify-between text-xs mb-2 text-gray-500">
                                <span>مصرف</span>
                                <span class="{{ $isFull ? 'text-red-500' : 'text-green-600' }}">
                                    {{ $totalUsed }} / {{ $totalCapacity }}
                                </span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-2 rounded-full transition-all duration-700
                                    {{ $isFull ? 'bg-red-500' : ($percentage > 70 ? 'bg-yellow-400' : 'bg-green-500') }}"
                                     style="width: {{ $percentage }}%">
                                </div>
                            </div>
                        </div>

                        <!-- Best Server -->
                        @if($bestServer && !$isFull)
                            <div class="mb-4 p-3 rounded-lg bg-gray-100 dark:bg-gray-800 text-sm">
                                <div class="flex justify-between">
                                    <span>{{ $bestServer->name }}</span>
                                    <span class="text-green-600">
                                        {{ $bestServer->current_users }} کاربر
                                    </span>
                                </div>
                            </div>
                        @endif

                        <!-- Action -->
                        <form action="{{ route('order.store-with-server', $plan->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="server_id" value="{{ $bestServer?->id }}">
                            <button type="submit"
                                    @disabled($isFull)
                                    class="w-full py-3 rounded-xl font-bold text-white
                                           transition transform active:scale-95
                                           {{ $isFull
                                                ? 'bg-gray-400 cursor-not-allowed'
                                                : 'bg-gradient-to-r from-indigo-600 to-purple-600 hover:scale-105' }}">
                                {{ $isFull ? 'تکمیل ظرفیت' : 'اتصال به سرور' }}
                            </button>
                        </form>

                        @if(!$isFull)
                            <p class="mt-3 text-xs text-center text-gray-500">
                                ظرفیت باقی‌مانده:
                                <span class="font-bold text-green-600">
                                    {{ $bestServer->capacity - $bestServer->current_users }}
                                </span>
                            </p>
                        @endif
                    </div>
                @empty
                    <div class="col-span-full text-center p-12 border-2 border-dashed rounded-2xl">
                        هیچ سروری در دسترس نیست
                    </div>
                @endforelse
            </div>

            <!-- Bottom Nav -->
            <div class="mt-12 flex flex-col sm:flex-row justify-between items-center gap-4">
                <p class="text-sm text-gray-500">
                    سرور با کمترین کاربر به‌صورت خودکار انتخاب می‌شود
                </p>

                <!-- REAL BACK -->
                <a href="{{ route('dashboard') }}"
                   class="flex items-center gap-2 px-6 py-3 rounded-xl
          bg-gray-100 dark:bg-gray-700
          hover:scale-105 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    بازگشت به داشبورد
                </a>

            </div>

        </div>
    </div>
</x-app-layout>
