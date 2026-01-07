<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            انتخاب روش پرداخت
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    شما در حال خرید پلن "{{ $plan->name }}" به مبلغ {{ $plan->price }} {{ $plan->currency }} هستید.
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    لطفاً یکی از روش‌های پرداخت زیر را انتخاب کنید:
                </p>

                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- گزینه کارت به کارت -->
                    <form method="POST" action="{{ route('payment.card.process', $order->id) }}">
                        @csrf
                        <button type="submit" class="w-full text-center p-6 border-2 rounded-lg hover:border-blue-500 transition">
                            <h4 class="font-bold text-gray-900 dark:text-gray-100">پرداخت با کارت به کارت</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                ساده و سریع، مناسب برای پرداخت ریالی.
                            </p>
                        </button>
                    </form>


                    <!-- گزینه ارز دیجیتال -->
                    <form method="POST" action="{{ route('payment.crypto.process', $order->id) }}">
                        @csrf
                        <button type="submit" class="w-full text-center p-6 border-2 rounded-lg hover:border-green-500 transition">
                            <h4 class="font-bold text-gray-900 dark:text-gray-100">پرداخت با ارز دیجیتال (USDT)</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                پرداخت آنی و خودکار.
                            </p>
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
