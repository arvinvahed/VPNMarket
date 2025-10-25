<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center w-full"> {{-- Flex-col برای موبایل، flex-row برای دسکتاپ --}}
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4 sm:mb-0"> {{-- Margin bottom در موبایل، صفر در دسکتاپ --}}
                {{ __('داشبورد کاربری') }}
            </h2>

            {{-- Notification Bell Icon --}}
            <div x-data="{ open: false }" class="relative ml-auto sm:ml-3"> {{-- ml-auto برای اینکه در موبایل راست‌چین شود --}}
                <button @click="open = !open" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition duration-150 ease-in-out" aria-label="Notifications">
                    <svg class="h-6 w-6 text-gray-500 dark:text-gray-400" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                        <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    @if(auth()->user()->unreadNotifications->count() > 0)
                        <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full transform translate-x-1/2 -translate-y-1/2">
                        {{ auth()->user()->unreadNotifications->count() }}
                    </span>
                    @endif
                </button>

                {{-- Notifications Dropdown --}}
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 sm:right-auto sm:left-0 mt-2 w-64 sm:w-80 rounded-md shadow-lg py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-50 text-right" {{-- right-0 برای نمایش در سمت راست موبایل، sm:left-0 برای دسکتاپ --}}
                     @click.away="open = false">
                    <div class="flex justify-between items-center px-4 py-2 text-xs text-gray-400">
                        اعلانات
                        @if(auth()->user()->unreadNotifications->count() > 0)
                            <button @click.prevent="markAllAsRead('{{ route('notifications.mark-all-as-read') }}')"
                                    class="text-indigo-500 hover:text-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300 font-semibold focus:outline-none flex items-center"> {{-- flex items-center برای آیکون و متن --}}
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                همه را خواندم
                            </button>
                        @endif
                    </div>
                    @forelse(auth()->user()->unreadNotifications->take(5) as $notification)
                        <a href="{{ $notification->link ?? '#' }}"
                           @click.prevent="markAsReadAndRedirect('{{ route('notifications.mark-as-read', $notification->id) }}', '{{ $notification->link ?? '#' }}')"
                           class="block px-4 py-2 text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150 ease-in-out">
                            <p class="font-bold">{{ $notification->title }}</p>
                            <p class="text-xs text-gray-500">{{ Str::limit($notification->message, 50) }}</p>
                            <span class="text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </a>
                    @empty
                        <div class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            اعلانی برای نمایش وجود ندارد.
                        </div>
                    @endforelse
                    @if(auth()->user()->notifications->count() > 0)
                        <div class="border-t border-gray-200 dark:border-gray-700 mt-1"></div>
                        <a href="{{ route('notifications.index') }}" class="block px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150 ease-in-out">
                            مشاهده همه اعلان‌ها
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">


            <div class="p-6 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg text-right">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center"> {{-- Flex-col برای موبایل --}}
                    <div class="mb-4 sm:mb-0"> {{-- Margin bottom در موبایل --}}
                        <span class="text-gray-500">موجودی کیف پول شما:</span>
                        <span class="font-bold text-lg text-green-500 block sm:inline-block mt-1 sm:mt-0">{{ number_format(auth()->user()->balance) }} تومان</span> {{-- block در موبایل برای نمایش بهتر --}}
                    </div>
                    <a href="{{ route('wallet.charge.form') }}" class="w-full sm:w-auto px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-center"> {{-- w-full در موبایل --}}
                        شارژ کیف پول
                    </a>
                </div>
            </div>


            @if (session('renewal_success'))
                <div class="mb-4 bg-blue-100 border-t-4 border-blue-500 rounded-b text-blue-900 px-4 py-3 shadow-md text-right" role="alert">
                    <div class="flex flex-col sm:flex-row-reverse items-start sm:items-center"> {{-- Flex-col در موبایل --}}
                        <div class="py-1 sm:ml-4 mb-2 sm:mb-0"><svg class="fill-current h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/></svg></div>
                        <div>
                            <p class="font-bold">اطلاعیه تمدید</p>
                            <p class="text-sm">{{ session('renewal_success') }}</p>
                        </div>
                    </div>
                </div>
            @endif
            @if (session('status'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative text-right" role="alert">
                    <strong class="font-bold block mb-1">موفقیت!</strong> {{-- block در موبایل --}}
                    <span class="block sm:inline">{{ session('status') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative text-right" role="alert">
                    <strong class="font-bold block mb-1">خطا!</strong> {{-- block در موبایل --}}
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div x-data="{ tab: 'my_services' }" class="bg-white/70 dark:bg-gray-900/70 rounded-2xl shadow-lg backdrop-blur-md p-4 sm:p-6">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex flex-nowrap overflow-x-auto custom-scrollbar sm:space-x-4 sm:space-x-reverse px-4 sm:px-8" aria-label="Tabs"> {{-- flex-nowrap و overflow-x-auto برای اسکرول در موبایل --}}
                        <button @click="tab = 'my_services'" :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': tab === 'my_services', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200': tab !== 'my_services'}" class="whitespace-nowrap py-4 px-3 sm:px-1 border-b-2 font-medium text-sm transition"> {{-- px-3 برای موبایل --}}
                            سرویس‌های من
                        </button>
                        <button @click="tab = 'order_history'" :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': tab === 'order_history', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200': tab !== 'order_history'}" class="whitespace-nowrap py-4 px-3 sm:px-1 border-b-2 font-medium text-sm transition">
                            تاریخچه سفارشات
                        </button>
                        <button @click="tab = 'new_service'" :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': tab === 'new_service', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200': tab !== 'new_service'}" class="whitespace-nowrap py-4 px-3 sm:px-1 border-b-2 font-medium text-sm transition">
                            خرید سرویس جدید
                        </button>
                        <button @click="tab = 'referral'" :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': tab === 'referral', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200': tab !== 'referral'}" class="whitespace-nowrap py-4 px-3 sm:px-1 border-b-2 font-medium text-sm transition">
                            دعوت از دوستان
                        </button>
                        <button @click="tab = 'tutorials'" :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': tab === 'tutorials', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200': tab !== 'tutorials'}" class="whitespace-nowrap py-4 px-3 sm:px-1 border-b-2 font-medium text-sm transition">
                            راهنمای اتصال
                        </button>
                        @if (Module::isEnabled('Ticketing'))
                            <button @click="tab = 'support'" :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': tab === 'support', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200': tab !== 'support'}" class="whitespace-nowrap py-4 px-3 sm:px-1 border-b-2 font-medium text-sm transition">
                                پشتیبانی
                            </button>
                        @endif
                    </nav>
                </div>


                <div class="p-2 sm:p-4">

                    <div x-show="tab === 'my_services'" x-transition.opacity>
                        @if($orders->isNotEmpty())
                            <div class="space-y-4">
                                @foreach ($orders->filter(fn($order) => !empty($order->config_details)) as $order)
                                    <div class="p-5 rounded-xl bg-gray-50 dark:bg-gray-800/50 shadow-md transition-shadow hover:shadow-lg" x-data="{ open: false }">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 items-center text-right"> {{-- grid-cols-1 در موبایل --}}
                                            <div>
                                                <span class="text-xs text-gray-500">پلن</span>
                                                <p class="font-bold text-gray-900 dark:text-white">{{ $order->plan->name }}</p>
                                            </div>
                                            <div>
                                                <span class="text-xs text-gray-500">حجم</span>
                                                <p class="font-bold text-gray-900 dark:text-white">{{ $order->plan->volume_gb }} GB</p>
                                            </div>
                                            <div>
                                                <span class="text-xs text-gray-500">وضعیت</span>
                                                <p class="font-semibold text-green-500">فعال</p>
                                            </div>
                                            <div>
                                                <span class="text-xs text-gray-500">تاریخ انقضا</span>
                                                <p class="font-mono text-gray-900 dark:text-white" dir="ltr">{{ $order->expires_at ? \Carbon\Carbon::parse($order->expires_at)->format('Y-m-d') : '-' }}</p>
                                            </div>
                                            <div class="text-left sm:text-right md:text-left mt-4 sm:mt-0"> {{-- تنظیم چینش دکمه‌ها در موبایل --}}
                                                <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-2 sm:space-x-reverse"> {{-- دکمه‌ها زیر هم در موبایل --}}
                                                    <form method="POST" action="{{ route('order.renew', $order->id) }}">
                                                        @csrf
                                                        <button type="submit" class="w-full sm:w-auto px-3 py-2 bg-yellow-500 text-white text-xs rounded-lg hover:bg-yellow-600 focus:outline-none" title="تمدید سرویس">
                                                            تمدید
                                                        </button>
                                                    </form>
                                                    <button @click="open = !open" class="w-full sm:w-auto px-3 py-2 bg-gray-700 text-white text-xs rounded-lg hover:bg-gray-600 focus:outline-none">
                                                        <span x-show="!open">کانفیگ</span>
                                                        <span x-show="open">بستن</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div x-show="open" x-transition x-cloak class="mt-4 pt-4 border-t dark:border-gray-700">
                                            <h4 class="font-bold mb-2 text-gray-900 dark:text-white text-right">اطلاعات سرویس:</h4>
                                            <div class="p-3 bg-gray-100 dark:bg-gray-900 rounded-lg relative" x-data="{copied: false, copyToClipboard(text) { navigator.clipboard.writeText(text); this.copied = true; setTimeout(() => { this.copied = false }, 2000); }}">
                                                <pre class="text-left text-sm text-gray-800 dark:text-gray-300 whitespace-pre-wrap overflow-x-auto" dir="ltr">{{ $order->config_details }}</pre> {{-- overflow-x-auto برای اسکرول افقی کانفیگ --}}
                                                <button @click="copyToClipboard(`{{ $order->config_details }}`)" class="absolute top-2 right-2 px-2 py-1 text-xs bg-gray-300 dark:bg-gray-700 rounded hover:bg-gray-400"><span x-show="!copied">کپی</span><span x-show="copied" class="text-green-500 font-bold">کپی شد!</span></button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 dark:text-gray-400 text-center py-10">🚀 شما هنوز هیچ سرویس فعالی خریداری نکرده‌اید.</p>
                        @endif
                    </div>


                    <div x-show="tab === 'order_history'" x-transition.opacity x-cloak>
                        <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white text-right">تاریخچه سفارشات و تراکنش‌ها</h2>
                        <div class="space-y-3">
                            @forelse ($transactions as $transaction)
                                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-center text-right"> {{-- grid-cols-1 در موبایل --}}
                                        <div>
                                            <span class="text-xs text-gray-500">نوع تراکنش</span>
                                            <p class="font-bold text-gray-900 dark:text-white">
                                                @if ($transaction->plan)
                                                    {{ $transaction->renews_order_id ? 'تمدید سرویس' : 'خرید سرویس' }}
                                                @else
                                                    شارژ کیف پول
                                                @endif
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">مبلغ</span>
                                            <p class="font-bold text-gray-900 dark:text-white">
                                                {{ number_format($transaction->plan->price ?? $transaction->amount) }} تومان
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">تاریخ</span>
                                            <p class="font-mono text-gray-900 dark:text-white" dir="ltr">
                                                {{ $transaction->created_at->format('Y-m-d') }}
                                            </p>
                                        </div>
                                        <div class="text-left sm:text-right md:text-left mt-4 sm:mt-0"> {{-- چینش وضعیت در موبایل --}}
                                            @if ($transaction->status == 'paid')
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    موفق
                                                </span>
                                            @elseif ($transaction->status == 'pending')
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    در انتظار تایید
                                                </span>
                                            @else
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    ناموفق/منقضی
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500 dark:text-gray-400 text-center py-10">هیچ تراکنشی یافت نشد.</p>
                            @endforelse
                        </div>
                    </div>


                    <div x-show="tab === 'new_service'" x-transition.opacity x-cloak>
                        <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white text-right">خرید سرویس جدید</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"> {{-- grid-cols-1 در موبایل --}}
                            @foreach ($plans as $plan)
                                <div class="p-6 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-blue-500/20 hover:-translate-y-1 transition-all text-right">
                                    <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ $plan->name }}</h3>
                                    <p class="text-3xl font-bold my-3 text-gray-900 dark:text-white">{{ $plan->price }} <span class="text-base font-normal text-gray-500 dark:text-gray-400">{{ $plan->currency }}</span></p>
                                    <ul class="text-sm space-y-2 text-gray-600 dark:text-gray-300 my-4">
                                        @foreach(explode("\n", $plan->features) as $feature)
                                            <li class="flex items-start"><svg class="w-4 h-4 text-green-500 ml-2 shrink-0 mt-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg><span>{{ trim($feature) }}</span></li>
                                        @endforeach
                                    </ul>
                                    <form method="POST" action="{{ route('order.store', $plan->id) }}" class="mt-6">
                                        @csrf
                                        <button type="submit" class="w-full px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition">خرید این پلن</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>


                    <div x-show="tab === 'tutorials'" x-transition.opacity x-cloak class="text-right">
                        <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">راهنمای استفاده از سرویس‌ها</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 mb-6">برای استفاده از کانفیگ‌ها، ابتدا باید نرم‌افزار V2Ray-Client مناسب دستگاه خود را نصب کنید.</p>

                        <div class="space-y-6" x-data="{ app: 'android' }">
                            <div class="flex flex-col sm:flex-row justify-center p-1 bg-gray-200 dark:bg-gray-800 rounded-xl space-y-2 sm:space-y-0 sm:space-x-2 sm:space-x-reverse"> {{-- دکمه‌ها زیر هم در موبایل --}}
                                <button @click="app = 'android'" :class="app === 'android' ? 'bg-white dark:bg-gray-700 shadow' : ''" class="w-full text-center py-2 px-4 rounded-lg transition">اندروید</button>
                                <button @click="app = 'ios'" :class="app === 'ios' ? 'bg-white dark:bg-gray-700 shadow' : ''" class="w-full text-center py-2 px-4 rounded-lg transition">آیفون (iOS)</button>
                                <button @click="app = 'windows'" :class="app === 'windows' ? 'bg-white dark:bg-gray-700 shadow' : ''" class="w-full text-center py-2 px-4 rounded-lg transition">ویندوز</button>
                            </div>

                            <div x-show="app === 'android'" class="p-6 bg-gray-50 dark:bg-gray-800/50 rounded-xl animate-fadeIn">
                                <h3 class="font-bold text-lg mb-3">راهنمای اندروید (V2RayNG)</h3>
                                <ol class="list-decimal list-inside space-y-2 text-gray-700 dark:text-gray-300">
                                    <li>ابتدا نرم‌افزار <a href="https://github.com/2dust/v2rayNG/releases" target="_blank" class="text-blue-500 hover:underline">V2RayNG</a> را از این لینک دانلود و نصب کنید.</li>
                                    <li>در تب "سرویس‌های من"، روی دکمه "مشاهده کانفیگ" کلیک کرده و سپس دکمه "کپی" را بزنید.</li>
                                    <li>وارد برنامه V2RayNG شوید و روی علامت بعلاوه (+) در بالای صفحه ضربه بزنید.</li>
                                    <li>گزینه `Import config from Clipboard` را انتخاب کنید.</li>
                                    <li>برای اتصال، روی دایره خاکستری در پایین صفحه ضربه بزنید تا سبز شود.</li>
                                </ol>
                            </div>

                            <div x-show="app === 'ios'" x-cloak class="p-6 bg-gray-50 dark:bg-gray-800/50 rounded-xl animate-fadeIn">
                                <h3 class="font-bold text-lg mb-3">راهنمای آیفون (Streisand / V2Box)</h3>
                                <p class="mb-2 text-sm">برای iOS می‌توانید از چندین برنامه استفاده کنید. ما V2Box را پیشنهاد می‌کنیم.</p>
                                <ol class="list-decimal list-inside space-y-2 text-gray-700 dark:text-gray-300">
                                    <li>ابتدا یکی از نرم‌افزارهای <a href="https://apps.apple.com/us/app/v2box-v2ray-client/id6446814690" target="_blank" class="text-blue-500 hover:underline">V2Box</a> یا <a href="https://apps.apple.com/us/app/streisand/id6450534064" target="_blank" class="text-blue-500 hover:underline">Streisand</a> را از اپ استور نصب کنید.</li>
                                    <li>در تب "سرویس‌های من"، روی دکمه "مشاهده کانفیگ" کلیک کرده و سپس دکمه "کپی" را بزنید.</li>
                                    <li>وارد برنامه شده، به بخش کانفیگ‌ها (Configs) بروید.</li>
                                    <li>روی علامت بعلاوه (+) بزنید و گزینه `Import from Clipboard` را انتخاب کنید.</li>
                                    <li>برای اتصال، سرویس اضافه شده را انتخاب و آن را فعال کنید.</li>
                                </ol>
                            </div>

                            <div x-show="app === 'windows'" x-cloak class="p-6 bg-gray-50 dark:bg-gray-800/50 rounded-xl animate-fadeIn">
                                <h3 class="font-bold text-lg mb-3">راهنمای ویندوز (V2RayN)</h3>
                                <ol class="list-decimal list-inside space-y-2 text-gray-700 dark:text-gray-300">
                                    <li>ابتدا نرم‌افزار <a href="https://github.com/2dust/v2rayN/releases" target="_blank" class="text-blue-500 hover:underline">V2RayN</a> را از این لینک دانلود و از حالت فشرده خارج کنید.</li>
                                    <li>در تب "سرویس‌های من"، روی دکمه "مشاهده کانفیگ" کلیک کرده و سپس دکمه "کپی" را بزنید.</li>
                                    <li>در برنامه V2RayN، کلیدهای `Ctrl+V` را فشار دهید تا کانفیگ به صورت خودکار اضافه شود.</li>
                                    <li>روی آیکون برنامه در تسک‌بار راست کلیک کرده، از منوی `System proxy` گزینه `Set system proxy` را انتخاب کنید.</li>
                                    <li>برای اتصال، سرور اضافه شده را انتخاب کرده و کلید `Enter` را بزنید.</li>
                                </ol>
                            </div>
                        </div>
                    </div>


                    <div x-show="tab === 'referral'" x-transition.opacity x-cloak>
                        <h2 class="text-xl font-bold mb-6 text-gray-900 dark:text-white text-right">کسب درآمد با دعوت از دوستان</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-right">


                            <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-800/50 space-y-4 shadow-lg">
                                <p class="text-gray-600 dark:text-gray-300">با اشتراک‌گذاری لینک زیر، دوستان خود را به ما معرفی کنید. پس از اولین خرید موفق آن‌ها، <span class="font-bold text-green-500">{{ number_format((int)\App\Models\Setting::where('key', 'referral_referrer_reward')->first()?->value ?? 0) }} تومان</span> به کیف پول شما اضافه خواهد شد!</p>

                                <div x-data="{ copied: false }">
                                    <label for="referral-link-mobile" class="block text-sm font-medium text-gray-500">لینک دعوت اختصاصی شما:</label>
                                    <div class="mt-1 flex flex-col sm:flex-row rounded-md shadow-sm">
                                        <input type="text" readonly id="referral-link-mobile" value="{{ route('register') }}?ref={{ auth()->user()->referral_code }}" class="flex-1 block w-full rounded-t-md sm:rounded-r-md sm:rounded-t-none sm:text-sm border-gray-300 dark:bg-gray-900 dark:border-gray-600 text-left" dir="ltr"> {{-- rounded-t-md در موبایل --}}
                                        <button @click="navigator.clipboard.writeText(document.getElementById('referral-link-mobile').value); copied = true; setTimeout(() => copied = false, 2000)" type="button" class="relative sm:-ml-px inline-flex items-center justify-center space-x-2 px-4 py-2 border border-gray-300 text-sm font-medium rounded-b-md sm:rounded-l-md sm:rounded-b-none text-gray-700 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600 w-full sm:w-auto mt-2 sm:mt-0"> {{-- rounded-b-md در موبایل، w-full در موبایل --}}
                                            <span x-show="!copied">کپی</span>
                                            <span x-show="copied" x-cloak class="text-green-500 font-bold">کپی شد!</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- کارت آمار --}}
                            <div class="p-6 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex flex-col justify-center items-center shadow-lg">
                                <p class="opacity-80">تعداد دعوت‌های موفق شما</p>
                                <p class="font-bold text-6xl mt-2">{{ auth()->user()->referrals()->count() }}</p>
                                <p class="text-sm opacity-80 mt-1">نفر</p>
                            </div>

                        </div>
                    </div>




                    @if (Module::isEnabled('Ticketing'))
                        <div x-show="tab === 'support'" x-transition.opacity x-cloak>
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white text-right mb-2 sm:mb-0">تیکت‌های پشتیبانی</h2>
                                <a href="{{ route('tickets.create') }}" class="w-full sm:w-auto px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 text-center">ارسال تیکت جدید</a> {{-- w-full در موبایل --}}
                            </div>

                            <div class="space-y-4">
                                @forelse ($tickets as $ticket)
                                    <a href="{{ route('tickets.show', $ticket->id) }}" class="block p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
                                            <p class="font-semibold text-gray-800 dark:text-gray-200 mb-2 sm:mb-0">{{ $ticket->subject }}</p>
                                            <span class="text-xs font-mono text-gray-500">{{ $ticket->created_at->format('Y-m-d') }}</span>
                                        </div>
                                        <div class="mt-2 flex flex-col sm:flex-row justify-between items-start sm:items-center">
                                            <span class="text-sm text-gray-600 dark:text-gray-400 mb-2 sm:mb-0">آخرین بروزرسانی: {{ $ticket->updated_at->diffForHumans() }}</span>
                                            <span class="text-xs px-2 py-1 rounded-full
                                                @switch($ticket->status)
                                                    @case('open') bg-blue-100 text-blue-800 @break
                                                    @case('answered') bg-green-100 text-green-800 @break
                                                    @case('closed') bg-gray-200 text-gray-700 @break
                                                @endswitch">
                                                {{ $ticket->status == 'open' ? 'باز' : ($ticket->status == 'answered' ? 'پاسخ داده شده' : 'بسته شده') }}
                                            </span>
                                        </div>
                                    </a>
                                @empty
                                    <p class="text-gray-500 dark:text-gray-400 text-center py-10">هیچ تیکتی یافت نشد.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function markAsReadAndRedirect(readUrl, redirectLink) {
                fetch(readUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = redirectLink;
                        }
                    })
                    .catch(error => console.error('Error marking notification as read:', error));
            }

            function markAllAsRead(readAllUrl) {
                fetch(readAllUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        }
                    })
                    .catch(error => console.error('Error marking all notifications as read:', error));
            }
        </script>
    @endpush
</x-app-layout>

<style>
    /* Custom Scrollbar for tabs on mobile */
    .custom-scrollbar::-webkit-scrollbar {
        height: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f1f1; /* Light gray track */
        border-radius: 5px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #888; /* Darker gray thumb */
        border-radius: 5px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #555; /* Even darker gray on hover */
    }
    /* For Firefox */
    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #888 #f1f1f1;
    }
</style>
