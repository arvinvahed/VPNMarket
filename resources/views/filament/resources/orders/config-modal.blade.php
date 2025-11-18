<div class="p-4 space-y-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            لینک کانفیگ:
        </label>
        <div class="flex gap-2">
            <input type="text" value="{{ $config }}" readonly
                   class="flex-1 p-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm font-mono"
                   id="config-link">
            <button onclick="copyToClipboard()"
                    class="px-4 py-2 bg-primary-500 text-white rounded-lg text-sm hover:bg-primary-600 transition-colors">
                کپی
            </button>
        </div>
    </div>

    @if(Str::startsWith($config, ['vless://', 'vmess://', 'trojan://', 'ss://']))
        <div class="text-center p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                QR Code (اسکن کنید):
            </label>
            <div class="flex justify-center">
                {!! QrCode::size(250)->margin(2)->generate($config) !!}
            </div>
        </div>
    @else
        <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg text-sm text-yellow-800 dark:text-yellow-200">
            ⚠️ این لینک از نوع اشتراک است و برای استفاده باید کپی شود.
        </div>
    @endif

    <script>
        function copyToClipboard() {
            const copyText = document.getElementById("config-link");
            copyText.select();
            copyText.setSelectionRange(0, 99999); // برای موبایل

            navigator.clipboard.writeText(copyText.value).then(() => {
                // نمایش پیام موفقیت
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = '✅ کپی شد!';
                button.classList.add('bg-green-500');
                button.classList.remove('bg-primary-500');

                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('bg-green-500');
                    button.classList.add('bg-primary-500');
                }, 2000);
            });
        }
    </script>
</div>
