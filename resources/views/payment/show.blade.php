<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            پرداخت سفارش #{{ $order->id }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-8">


                @if (session('status'))
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-xl">
                        <p>{{ session('status') }}</p>
                    </div>
                @endif
                @if (session('error'))
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-xl">
                        <p>{{ session('error') }}</p>
                    </div>
                @endif
                @if (session('success'))
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-xl">
                        <p>{{ session('success') }}</p>
                    </div>
                @endif


                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 text-right border-b dark:border-gray-700 pb-3 mb-4">
                        جزئیات فاکتور
                    </h3>
                    <div class="space-y-3 text-right">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">موضوع:</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                @if ($order->plan)
                                    {{ $order->renews_order_id ? 'تمدید سرویس' : 'خرید سرویس' }} ({{ $order->plan->name }})
                                @else
                                    شارژ کیف پول
                                @endif
                            </span>
                        </div>
                        
                        @if ($order->promo_code_id && $order->promoCode)
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">مبلغ اولیه:</span>
                                <span class="text-gray-600 dark:text-gray-400 line-through">
                                    {{ number_format($order->original_amount) }} تومان
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">کد تخفیف ({{ $order->promoCode->code }}):</span>
                                <span class="text-red-500 font-semibold">
                                    - {{ number_format($order->discount_amount) }} تومان
                                </span>
                            </div>
                        @endif
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">مبلغ قابل پرداخت:</span>
                            <span class="font-bold text-lg text-green-500">
                                {{ number_format($order->amount ?? $order->plan->price) }} تومان
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Coupon Code Section --}}
                @if ($order->plan)
                <div class="bg-gradient-to-r from-purple-50 to-blue-50 dark:from-purple-900/20 dark:to-blue-900/20 p-6 rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 text-right mb-4">
                        کد تخفیف دارید؟
                    </h3>
                    
                    @if ($order->promo_code_id && $order->promoCode)
                        <div class="flex items-center justify-between bg-white dark:bg-gray-800 p-4 rounded-lg">
                            <div class="text-right">
                                <p class="text-sm text-gray-600 dark:text-gray-400">کد تخفیف فعال:</p>
                                <p class="font-bold text-purple-600 dark:text-purple-400 text-lg">{{ $order->promoCode->code }}</p>
                            </div>
                            <form method="POST" action="{{ route('order.remove-coupon', $order->id) }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-400 rounded-lg transition">
                                    حذف کد تخفیف
                                </button>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('order.apply-coupon', $order->id) }}" class="flex gap-3">
                            @csrf
                            <input type="text" 
                                   name="coupon_code" 
                                   placeholder="کد تخفیف خود را وارد کنید"
                                   class="flex-1 px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-gray-100 text-right"
                                   required>
                            <button type="submit" 
                                    class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition font-semibold">
                                اعمال
                            </button>
                        </form>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 text-right">
                            کد تخفیف خود را وارد کرده و روی دکمه اعمال کلیک کنید
                        </p>
                    @endif
                </div>
                @endif


                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 text-right">
                        انتخاب روش پرداخت
                    </h3>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-6">


                        @if ($order->plan)
                        <form method="POST" action="{{ route('payment.wallet.process', $order->id) }}">
                            @csrf
                            @php
                                $finalPrice = $order->amount ?? $order->plan->price;
                            @endphp
                            <button type="submit"
                                    class="w-full text-center p-6 border-2 rounded-lg transition dark:border-gray-600
                                               @if($finalPrice > auth()->user()->balance)
                                                   border-red-400 cursor-not-allowed bg-red-50 dark:bg-red-900/20
                                               @else
                                                   hover:border-purple-500 dark:hover:border-purple-500
                                               @endif"
                                    @if($finalPrice > auth()->user()->balance) disabled @endif>

                                <h4 class="font-bold text-gray-900 dark:text-gray-100">پرداخت از کیف پول (آنی)</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                    موجودی شما: {{ number_format(auth()->user()->balance) }} تومان
                                </p>
                                @if ($finalPrice > auth()->user()->balance)
                                    <p class="text-xs font-semibold text-red-500 mt-2">موجودی کافی نیست</p>
                                @endif
                            </button>
                        </form>
                        @endif



                        <form method="POST" action="{{ route('payment.card.process', $order->id) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full text-center p-6 border-2 rounded-lg hover:border-blue-500 transition dark:border-gray-600 dark:hover:border-blue-500">
                                <h4 class="font-bold text-gray-900 dark:text-gray-100">پرداخت با کارت به کارت</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                    ارسال رسید و انتظار برای تایید
                                </p>
                            </button>
                        </form>

                        {{-- گزینه ارز دیجیتال (غیرفعال) --}}
                        <div class="w-full text-center p-6 border-2 rounded-lg transition dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 cursor-not-allowed opacity-60">
                            <h4 class="font-bold text-gray-500 dark:text-gray-400">پرداخت با ارز دیجیتال</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-500 mt-2">
                                (به زودی)
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
