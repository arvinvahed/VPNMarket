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

                        {{-- نمایش تخفیف اعمال‌شده --}}
                        @php
                            $originalAmount = $order->plan->price ?? $order->amount;
                            $discountAmount = session('discount_amount', 0);
                            $finalAmount = $originalAmount - $discountAmount;
                        @endphp

                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">مبلغ اصلی:</span>
                            <span id="original-amount" class="font-semibold text-gray-800 dark:text-gray-200">
                                {{ number_format($originalAmount) }} تومان
                            </span>
                        </div>

                        @if($discountAmount > 0)
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">تخفیف:</span>
                                <span class="font-semibold text-red-500">
                                - {{ number_format($discountAmount) }} تومان
                            </span>
                            </div>
                            <div class="flex justify-between items-center border-t dark:border-gray-700 pt-3">
                                <span class="text-gray-500 dark:text-gray-400">مبلغ نهایی:</span>
                                <span id="final-amount" class="font-bold text-lg text-green-600">
                                {{ number_format($finalAmount) }} تومان
                            </span>
                            </div>
                        @endif

                        @if(!$discountAmount)
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 dark:text-gray-400">مبلغ قابل پرداخت:</span>
                                <span class="font-bold text-lg text-green-500">
                                {{ number_format($originalAmount) }} تومان
                            </span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- فرم کد تخفیف --}}
                @if(!session('discount_code'))
                    <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 text-right mb-3">
                            کد تخفیف دارید؟
                        </h4>
                        <form id="discount-form" action="{{ route('order.applyDiscount', $order->id) }}" method="POST">
                            @csrf
                            <div class="flex gap-3">
                                <input type="text" name="code" id="discount-code"
                                       class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                       placeholder="مثلاً: YALDA1404">
                                <button type="submit"
                                        class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition">
                                    اعمال کد
                                </button>
                            </div>
                        </form>
                        <div id="discount-message" class="mt-2 text-sm text-right"></div>
                    </div>
                @else
                    <div class="bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-700 rounded-lg p-3 text-center">
                        <p class="text-green-700 dark:text-green-300">
                            ✅ کد تخفیف <strong>{{ session('discount_code') }}</strong> اعمال شده است.
                        </p>
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
                                <button type="submit"
                                        class="w-full text-center p-6 border-2 rounded-lg transition dark:border-gray-600
                                           @if($finalAmount > auth()->user()->balance)
                                               border-red-400 cursor-not-allowed bg-red-50 dark:bg-red-900/20
                                           @else
                                               hover:border-purple-500 dark:hover:border-purple-500
                                           @endif"
                                        @if($finalAmount > auth()->user()->balance) disabled @endif>

                                    <h4 class="font-bold text-gray-900 dark:text-gray-100">پرداخت از کیف پول (آنی)</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                        موجودی شما: {{ number_format(auth()->user()->balance) }} تومان
                                    </p>
                                    @if ($finalAmount > auth()->user()->balance)
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

    <script>
        document.getElementById('discount-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = e.target;
            const messageDiv = document.getElementById('discount-message');
            const submitBtn = form.querySelector('button[type="submit"]');

            submitBtn.disabled = true;
            submitBtn.innerHTML = 'در حال بررسی...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: new FormData(form)
                });

                const data = await response.json();

                if (data.success) {
                    messageDiv.innerHTML = `<div class="text-green-600 dark:text-green-400">${data.message}</div>`;
                    setTimeout(() => location.reload(), 1200);
                } else {
                    messageDiv.innerHTML = `<div class="text-red-600 dark:text-red-400">${data.error}</div>`;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'اعمال کد';
                }
            } catch (error) {
                messageDiv.innerHTML = `<div class="text-red-600 dark:text-red-500">خطا در ارتباط با سرور</div>`;
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'اعمال کد';
            }
        });
    </script>
</x-app-layout>
