<!-- resources/views/webapp/reseller.blade.php -->

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پنل نمایندگی VPN</title>

    <!-- Telegram WebApp -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--tg-theme-bg-color, #0f172a);
            color: var(--tg-theme-text-color, #f8fafc);
        }

        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .gradient-border {
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2px;
            border-radius: 1rem;
        }

        .gradient-border-inner {
            background: var(--tg-theme-secondary-bg-color, #1e293b);
            border-radius: 0.875rem;
        }

        .server-card {
            transition: all 0.3s ease;
        }

        .server-card:hover:not(.disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
        }

        .server-card.selected {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.1);
        }

        .server-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .plan-card {
            transition: all 0.2s ease;
        }

        .plan-card:hover {
            transform: scale(1.02);
        }

        .plan-card.selected {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }

        .loading-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen pb-24" x-data="resellerApp()" x-init="init()">

<!-- هدر -->
<header class="sticky top-0 z-50 glass-panel border-b border-gray-700/50">
    <div class="max-w-lg mx-auto px-4 py-3">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">
                    💎 پنل نمایندگی
                </h1>
                <p class="text-xs text-gray-400" x-text="user.name || 'در حال بارگذاری...'"></p>
            </div>
            <div class="text-left">
                <div class="text-xs text-gray-400">موجودی</div>
                <div class="font-bold text-emerald-400" x-text="formatPrice(user.balance)"></div>
            </div>
        </div>

        <!-- آمار سریع -->
        <div class="grid grid-cols-3 gap-2 mt-3">
            <div class="bg-gray-800/50 rounded-lg p-2 text-center">
                <div class="text-xs text-gray-400">سهمیه</div>
                <div class="font-bold text-indigo-400" x-text="user.quota"></div>
            </div>
            <div class="bg-gray-800/50 rounded-lg p-2 text-center">
                <div class="text-xs text-gray-400">تست</div>
                <div class="font-bold text-amber-400" x-text="user.trial_quota"></div>
            </div>
            <div class="bg-gray-800/50 rounded-lg p-2 text-center">
                <div class="text-xs text-gray-400">تخفیف</div>
                <div class="font-bold text-rose-400" x-text="user.discount_percent + '%'"></div>
            </div>
        </div>
    </div>
</header>

<!-- محتوای اصلی -->
<main class="max-w-lg mx-auto px-4 py-4 space-y-4">

    <!-- انتخاب سرور -->
    <section class="fade-in">
        <h2 class="text-sm font-semibold text-gray-300 mb-3 flex items-center">
            <span class="w-2 h-2 bg-indigo-500 rounded-full ml-2"></span>
            انتخاب لوکیشن سرور
        </h2>

        <div class="grid grid-cols-2 gap-3">
            <template x-for="server in servers" :key="server.id">
                <div
                    @click="server.capacity_status === 'available' && (selectedServer = server.id)"
                    :class="{
                            'selected': selectedServer === server.id,
                            'disabled': server.capacity_status === 'full'
                        }"
                    class="server-card glass-panel rounded-xl p-3 cursor-pointer border-2 border-transparent"
                >
                    <div class="flex items-center space-x-3 space-x-reverse">
                        <span class="text-3xl" x-text="server.flag"></span>
                        <div class="flex-1">
                            <div class="font-bold text-sm" x-text="server.location_name"></div>
                            <div class="text-xs" :class="server.remaining > 5 ? 'text-emerald-400' : 'text-amber-400'">
                                <span x-text="server.remaining"></span> ظرفیت
                            </div>
                        </div>
                    </div>
                    <div x-show="server.capacity_status === 'full'" class="mt-2 text-xs text-red-400 text-center">
                        ⚠️ تکمیل
                    </div>
                </div>
            </template>
        </div>
    </section>

    <!-- تغییر وضعیت تست/اصلی -->
    <section class="glass-panel rounded-xl p-4 fade-in">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3 space-x-reverse">
                <div class="w-10 h-10 rounded-lg bg-amber-500/20 flex items-center justify-center">
                    <span class="text-xl">🧪</span>
                </div>
                <div>
                    <div class="font-semibold text-sm">اکانت تست رایگان</div>
                    <div class="text-xs text-gray-400">
                        <span x-text="user.trial_quota"></span> عدد باقی‌مانده
                    </div>
                </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" x-model="isTrial" class="sr-only peer" :disabled="user.trial_quota <= 0">
                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
            </label>
        </div>
    </section>

    <!-- انتخاب پلن (فقط اگر تست نباشد) -->
    <section x-show="!isTrial" x-transition class="fade-in">
        <h2 class="text-sm font-semibold text-gray-300 mb-3 flex items-center">
            <span class="w-2 h-2 bg-emerald-500 rounded-full ml-2"></span>
            انتخاب پلن
        </h2>

        <div class="space-y-3">
            <template x-for="plan in plans" :key="plan.id">
                <div
                    @click="selectedPlan = plan.id"
                    :class="{ 'selected': selectedPlan === plan.id }"
                    class="plan-card glass-panel rounded-xl p-4 cursor-pointer border-2 border-transparent"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="flex items-center space-x-2 space-x-reverse">
                                <span class="font-bold" x-text="plan.name"></span>
                                <span x-show="plan.is_popular" class="px-2 py-0.5 bg-rose-500/20 text-rose-400 text-xs rounded-full">محبوب</span>
                            </div>
                            <div class="text-sm text-gray-400 mt-1">
                                <span x-text="plan.volume_gb"></span> گیگابایت |
                                <span x-text="plan.duration_days"></span> روزه
                            </div>
                        </div>
                        <div class="text-left">
                            <div x-show="user.has_quota" class="text-emerald-400 font-bold text-sm">رایگان</div>
                            <div x-show="!user.has_quota">
                                <div class="text-xs text-gray-500 line-through" x-show="plan.original_price !== plan.price" x-text="formatPrice(plan.original_price)"></div>
                                <div class="text-emerald-400 font-bold" x-text="formatPrice(plan.price)"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>

    <!-- نام کاربری -->
    <section class="glass-panel rounded-xl p-4 fade-in">
        <label class="block text-sm font-medium text-gray-300 mb-2">
            👤 نام کاربری (انگلیسی)
        </label>
        <div class="flex space-x-2 space-x-reverse">
            <input
                type="text"
                x-model="username"
                @input="username = $event.target.value.toLowerCase().replace(/[^a-z0-9]/g, '')"
                class="flex-1 bg-gray-800/50 border border-gray-600 rounded-lg px-4 py-3 text-left font-mono text-sm focus:border-indigo-500 focus:outline-none transition"
                placeholder="مثال: user123"
                maxlength="20"
            >
            <button
                @click="generateRandomUsername()"
                class="px-4 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-lg transition"
                title="تولید تصادفی"
            >
                🎲
            </button>
        </div>
        <p class="text-xs text-gray-500 mt-2">فقط حروف کوچک انگلیسی و اعداد (بدون فاصله)</p>
    </section>

    <!-- دکمه ساخت -->
    <button
        @click="createAccount()"
        :disabled="!canSubmit || loading"
        :class="{
                'opacity-50 cursor-not-allowed': !canSubmit || loading,
                'hover:scale-[1.02] active:scale-[0.98]': canSubmit && !loading
            }"
        class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-indigo-500/30 transition transform flex items-center justify-center space-x-2 space-x-reverse"
    >
        <span x-show="loading" class="loading-spinner w-5 h-5 border-2 border-white border-t-transparent rounded-full"></span>
        <span x-text="loading ? 'در حال ساخت...' : (isTrial ? '🧪 ساخت اکانت تست' : '🚀 ساخت اکانت جدید')"></span>
    </button>

</main>

<!-- مودال نتیجه -->
<div
    x-show="showResult"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 scale-90"
    x-transition:enter-end="opacity-100 scale-100"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
>
    <div class="glass-panel rounded-2xl p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="text-center">
            <div class="w-16 h-16 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="text-3xl">✅</span>
            </div>
            <h3 class="text-xl font-bold text-emerald-400 mb-2">ساخت موفقیت‌آمیز!</h3>
            <p class="text-sm text-gray-400 mb-4" x-text="resultMessage"></p>
        </div>

        <!-- جزئیات -->
        <div class="space-y-3 mb-4">
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">نام کاربری:</span>
                <span class="font-mono text-left" x-text="resultData.username"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">انقضا:</span>
                <span x-text="resultData.expires_at"></span>
            </div>
            <div x-show="!isTrial && resultData.price_paid > 0" class="flex justify-between text-sm">
                <span class="text-gray-400">مبلغ پرداخت شده:</span>
                <span class="text-emerald-400" x-text="formatPrice(resultData.price_paid)"></span>
            </div>
        </div>

        <!-- لینک کانفیگ -->
        <div class="bg-gray-900/50 rounded-lg p-3 mb-4">
            <div class="text-xs text-gray-400 mb-2">لینک کانفیگ:</div>
            <code class="block text-xs text-gray-300 break-all font-mono leading-relaxed" x-text="resultData.config"></code>
        </div>

        <!-- دکمه‌ها -->
        <div class="space-y-2">
            <button
                @click="copyToClipboard(resultData.config)"
                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-lg font-semibold transition flex items-center justify-center space-x-2 space-x-reverse"
            >
                <span>📋 کپی لینک</span>
            </button>
            <button
                @click="resetForm()"
                class="w-full bg-gray-700 hover:bg-gray-600 text-white py-3 rounded-lg transition"
            >
                ساخت اکانت دیگر
            </button>
        </div>
    </div>
</div>

<!-- مودال خطا -->
<div
    x-show="showError"
    x-transition
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
>
    <div class="glass-panel rounded-2xl p-6 w-full max-w-sm text-center">
        <div class="w-16 h-16 bg-rose-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <span class="text-3xl">❌</span>
        </div>
        <h3 class="text-lg font-bold text-rose-400 mb-2">خطا</h3>
        <p class="text-sm text-gray-300 mb-4" x-text="errorMessage"></p>
        <button
            @click="showError = false"
            class="w-full bg-gray-700 hover:bg-gray-600 text-white py-3 rounded-lg transition"
        >
            بستن
        </button>
    </div>
</div>

<script>
    function resellerApp() {
        return {
            // داده‌ها
            tg: null,
            user: {
                name: '',
                balance: 0,
                quota: 0,
                trial_quota: 0,
                discount_percent: 0,
                has_quota: false
            },
            servers: [],
            plans: [],

            // وضعیت فرم
            selectedServer: null,
            selectedPlan: null,
            username: '',
            isTrial: false,
            loading: false,

            // نتایج
            showResult: false,
            showError: false,
            resultData: {},
            resultMessage: '',
            errorMessage: '',

            // computed
            get canSubmit() {
                if (!this.selectedServer) return false;
                if (!this.isTrial && !this.selectedPlan) return false;
                if (this.username.length < 3) return false;
                return true;
            },

            init() {
                // راه‌اندازی Telegram WebApp
                this.tg = window.Telegram.WebApp;
                this.tg.expand();
                this.tg.ready();

                // دریافت اطلاعات کاربر
                this.fetchData();

                // تنظیم رنگ تم
                if (this.tg.colorScheme === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            },

            async fetchData() {
                try {
                    const tgUser = this.tg.initDataUnsafe?.user;
                    if (!tgUser) {
                        throw new Error('لطفاً از طریق تلگرام وارد شوید');
                    }

                    const response = await fetch(`/api/webapp/info?telegram_id=${tgUser.id}`);
                    const data = await response.json();

                    if (data.error) {
                        throw new Error(data.error);
                    }

                    this.user = data.user;
                    this.servers = data.servers;
                    this.plans = data.plans;

                } catch (error) {
                    this.showErrorMessage(error.message);
                }
            },

            generateRandomUsername() {
                const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
                let result = 'u';
                for (let i = 0; i < 5; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                this.username = result;
            },

            async createAccount() {
                if (!this.canSubmit || this.loading) return;

                this.loading = true;

                try {
                    const tgUser = this.tg.initDataUnsafe?.user;

                    const response = await fetch('/api/webapp/create-user', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            telegram_id: tgUser.id,
                            server_id: this.selectedServer,
                            plan_id: this.isTrial ? null : this.selectedPlan,
                            username: this.username,
                            is_trial: this.isTrial
                        })
                    });

                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.message || 'خطای ناشناخته');
                    }

                    this.resultData = data;
                    this.resultMessage = this.isTrial
                        ? 'اکانت تست شما با موفقیت ساخته شد!'
                        : 'اکانت جدید با موفقیت ساخته شد!';

                    this.showResult = true;

                    // بازخورد لمسی
                    this.tg.HapticFeedback.notificationOccurred('success');

                } catch (error) {
                    this.showErrorMessage(error.message);
                    this.tg.HapticFeedback.notificationOccurred('error');
                } finally {
                    this.loading = false;
                }
            },

            copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(() => {
                    this.tg.showAlert('✅ لینک کپی شد!');
                    this.tg.HapticFeedback.notificationOccurred('success');
                });
            },

            resetForm() {
                this.showResult = false;
                this.selectedServer = null;
                this.selectedPlan = null;
                this.username = '';
                this.isTrial = false;
                this.resultData = {};

                // به‌روزرسانی مجدد اطلاعات کاربر
                this.fetchData();
            },

            showErrorMessage(message) {
                this.errorMessage = message;
                this.showError = true;
            },

            formatPrice(price) {
                return new Intl.NumberFormat('fa-IR').format(price) + ' تومان';
            }
        }
    }
</script>
</body>
</html>
