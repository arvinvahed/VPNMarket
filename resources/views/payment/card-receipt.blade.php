<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            ارسال رسید پرداخت برای سفارش #{{ $order->id }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm overflow-hidden shadow-2xl sm:rounded-2xl p-6 md:p-8 text-right space-y-8">

                {{-- بخش کارت بانکی --}}
                <div x-data="{ copied: false }" class="relative p-6 rounded-2xl bg-gradient-to-br from-gray-700 via-gray-800 to-black text-white shadow-lg">
                    <div class="absolute top-4 right-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 opacity-50" viewBox="0 0 384 512"><path fill="currentColor" d="M336 0H48C21.5 0 0 21.5 0 48v416c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zM192 416c-22.1 0-40-17.9-40-40s17.9-40 40-40 40 17.9 40 40-17.9 40-40 40zm128-204H64v-48h256v48zm0-108H64V64h256v40z"/></svg>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <p class="text-sm text-gray-400">مبلغ قابل پرداخت</p>
                            <p class="font-bold text-3xl text-green-400">
                                {{ number_format($finalAmount) }}
                                <span class="text-lg font-normal text-gray-300">تومان</span>
                            </p>
                            @if(session('discount_amount', 0) > 0)
                                <p class="text-xs text-gray-400 mt-2">
                                    <del>{{ number_format($order->plan->price ?? $order->amount) }}</del> تومان
                                    با تخفیف {{ number_format(session('discount_amount')) }} تومان
                                </p>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">شماره کارت</p>
                            <div class="flex items-center justify-between">
                                <p id="card-number" class="font-mono font-semibold text-xl tracking-wider" dir="ltr">
                                    {{ $settings->get('payment_card_number', '---- ---- ---- ----') }}
                                </p>
                                <button @click="navigator.clipboard.writeText(document.getElementById('card-number').innerText); copied = true; setTimeout(() => copied = false, 2000)" class="p-2 rounded-md bg-white/10 hover:bg-white/20 transition">
                                    <svg x-show="!copied" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                    <svg x-show="copied" x-cloak class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">به نام</p>
                            <p class="font-semibold text-lg">
                                {{ $settings->get('payment_card_holder_name', 'ثبت نشده') }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- بخش آپلود رسید --}}
                <div x-data="{ fileName: '' }">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                        مرحله ۲: ارسال تصویر رسید
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        پس از واریز، لطفاً از رسید خود اسکرین‌شات گرفته و آن را در فرم زیر بارگذاری کنید.
                    </p>

                    @if ($errors->any())
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <p>{{ $errors->first() }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('payment.card.submit', $order->id) }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf {{-- اضافه کردن CSRF Token --}}
                        <div>
                            <label for="receipt" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 dark:hover:bg-bray-800 dark:bg-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:hover:border-gray-500 dark:hover:bg-gray-600 transition">

                                <div x-show="fileName === ''" class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <svg class="w-8 h-8 mb-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                    </svg>
                                    <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">برای انتخاب فایل کلیک کنید</span></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG (حداکثر ۲ مگابایت)</p>
                                </div>
                                <div x-show="fileName !== ''" x-cloak class="flex flex-col items-center justify-center">
                                    <svg class="w-8 h-8 mb-3 text-green-500 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    <p class="text-sm text-gray-700 dark:text-gray-200">
                                        فایل <span class="font-semibold" x-text="fileName"></span> انتخاب شد.
                                    </p>
                                </div>
                                <input @change="fileName = $event.target.files.length > 0 ? $event.target.files[0].name : ''" id="receipt" name="receipt" type="file" class="hidden" required>
                            </label>
                        </div>

                        <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold rounded-lg shadow-lg hover:scale-105 transform transition-transform duration-300">
                            ثبت و ارسال رسید
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
