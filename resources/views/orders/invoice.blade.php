<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            صفحه پرداخت - سفارش #{{ $order->id }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="space-y-4 text-right">
                        <div>
                            <h3 class="text-lg font-bold mb-4">🛒 جزئیات سفارش</h3>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-gray-500">پلن:</span>
                                    <p class="font-bold">{{ $order->plan->name }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">حجم:</span>
                                    <p class="font-bold">{{ $order->plan->volume_gb }} GB</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">مدت زمان:</span>
                                    <p class="font-bold">{{ $order->plan->duration_label }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">نام کاربری:</span>
                                    <p class="font-bold">{{ $order->panel_username }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="border-t dark:border-gray-700 pt-4">
                            <div class="flex justify-between items-center mb-2">
                                <span>قیمت اصلی:</span>
                                <span>{{ number_format($order->plan->price) }} تومان</span>
                            </div>

                            @if($order->discount_amount > 0)
                                <div class="flex justify-between items-center mb-2 text-green-600">
                                    <span>تخفیف:</span>
                                    <span>-{{ number_format($order->discount_amount) }} تومان</span>
                                </div>
                            @endif

                            <div class="flex justify-between items-center font-bold text-lg border-t dark:border-gray-600 pt-2">
                                <span>مبلغ قابل پرداخت:</span>
                                <span>{{ number_format($order->amount) }} تومان</span>
                            </div>
                        </div>

                        <!-- کد تخفیف -->
                        <div class="border-t dark:border-gray-700 pt-4">
                            <form id="discount-form" class="flex gap-2">
                                @csrf
                                <input type="text" name="code" placeholder="کد تخفیف"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600">
                                    اعمال
                                </button>
                            </form>
                            <div id="discount-message" class="mt-2 text-sm"></div>
                        </div>

                        <!-- گزینه‌های پرداخت -->
                        <div class="border-t dark:border-gray-700 pt-4">
                            <h4 class="font-bold mb-3">💳 روش پرداخت</h4>

                            <div class="space-y-3">
                                <!-- پرداخت با کیف پول -->
                                @if($balance >= $order->amount)
                                    <form method="POST" action="{{ route('payment.wallet.process', $order) }}">
                                        @csrf
                                        <button type="submit" class="w-full p-3 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                            ✅ پرداخت با کیف پول (موجودی: {{ number_format($balance) }} تومان)
                                        </button>
                                    </form>
                                @else
                                    <div class="p-3 bg-gray-200 dark:bg-gray-700 rounded-lg opacity-50 cursor-not-allowed text-center">
                                        موجودی کیف پول کافی نیست (موجودی: {{ number_format($balance) }} تومان)
                                    </div>
                                @endif

                                <!-- پرداخت کارت به کارت -->
                                <form method="POST" action="{{ route('payment.card.process', $order) }}">
                                    @csrf
                                    <button type="submit" class="w-full p-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                        💳 پرداخت کارت به کارت
                                    </button>
                                </form>

                                <!-- پرداخت ارز دیجیتال -->
                                <form method="POST" action="{{ route('payment.crypto.process', $order) }}">
                                    @csrf
                                    <button type="submit" class="w-full p-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                        🪙 پرداخت ارز دیجیتال
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('discount-form').addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                const messageDiv = document.getElementById('discount-message');

                fetch('{{ route("order.applyDiscount", $order) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ code: formData.get('code') })
                })
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            messageDiv.innerHTML = `<div class="text-green-600">${data.message}</div>`;
                            location.reload();
                        } else {
                            messageDiv.innerHTML = `<div class="text-red-600">${data.error}</div>`;
                        }
                    })
                    .catch(error => {
                        messageDiv.innerHTML = '<div class="text-red-600">خطا در ارتباط</div>';
                    });
            });
        </script>
    @endpush
</x-app-layout>
