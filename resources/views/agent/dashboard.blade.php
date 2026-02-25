@extends('layouts.webapp')

@section('content')

    <!-- هدر پروفایل -->
    <div class="card" style="text-align: center; background: linear-gradient(to bottom, var(--tg-theme-secondary-bg-color), var(--tg-theme-bg-color));">
        <div style="width: 70px; height: 70px; margin: 0 auto 10px; background: var(--tg-theme-button-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold;">
            {{ mb_substr($user->name, 0, 1) }}
        </div>
        <h2 style="margin: 5px 0;">{{ $user->name }}</h2>
        <div class="status-badge status-success" style="display: inline-block;">نماینده رسمی</div>
    </div>

    <!-- کارت موجودی -->
    <div class="card">
        <div class="list-item" style="border: none; padding: 0;">
            <div>
                <span class="subtitle" style="margin: 0;">موجودی کیف پول</span>
                <h1 style="color: var(--tg-theme-button-color); margin-top: 5px;">
                    {{ number_format($agent->agent_balance) }} <small style="font-size: 14px; color: var(--tg-theme-hint-color);">تومان</small>
                </h1>
            </div>
            <a href="{{ route('webapp.agent.deposit', ['user_id' => request('user_id')]) }}" class="btn-primary" style="width: auto; padding: 10px 20px; font-size: 14px; margin: 0; display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-plus"></i> افزایش
            </a>
        </div>
    </div>

    <!-- دکمه‌های میانبر -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
        <a href="{{ route('webapp.agent.buy-server', ['user_id' => request('user_id')]) }}" class="card" style="text-align: center; text-decoration: none; color: inherit; margin: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100px;">
            <div style="width: 45px; height: 45px; background: rgba(52, 152, 219, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                <i class="fa-solid fa-server" style="font-size: 22px; color: var(--tg-theme-button-color);"></i>
            </div>
            <div style="font-size: 14px; font-weight: bold;">خرید سرور جدید</div>
        </a>

        <a href="{{ route('webapp.agent.accounts', ['user_id' => request('user_id')]) }}" class="card" style="text-align: center; text-decoration: none; color: inherit; margin: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100px;">
            <div style="width: 45px; height: 45px; background: rgba(128, 128, 128, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                <i class="fa-solid fa-users" style="font-size: 22px; color: var(--tg-theme-text-color);"></i>
            </div>
            <div style="font-size: 14px; font-weight: bold;">مدیریت کاربران</div>
        </a>
    </div>

    <!-- لیست سرورهای اختصاصی نماینده -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h3>سرورهای اختصاصی</h3>
        <span style="font-size: 12px; background: var(--tg-theme-hint-color); color: white; padding: 2px 8px; border-radius: 10px;">{{ $servers->count() }} عدد</span>
    </div>

    @if($servers->count() > 0)
        @foreach($servers as $server)
            <div class="card" style="position: relative; overflow: hidden;">
                <!-- نوار رنگی وضعیت -->
                <div style="position: absolute; top: 0; right: 0; bottom: 0; width: 4px; background: {{ $server->is_active ? ($server->host === 'pending' ? '#f1c40f' : '#00b894') : '#d63031' }};"></div>

                <div style="padding-right: 10px;"> <!-- فاصله برای نوار رنگی -->
                    <div class="list-item" style="border-bottom: none; padding-top: 0;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <!-- آیکون هوشمند پنل -->
                            @if($server->host === 'pending')
                                <div style="width: 45px; height: 45px; background: #fff3cd; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-hourglass-half" style="color: #f39c12; font-size: 20px;"></i>
                                </div>
                            @elseif($server->isMarzban())
                                <div style="width: 45px; height: 45px; background: #2d3436; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <!-- لوگوی مرزبان (اگر عکس لود نشد آیکون نشون بده) -->
                                    <img src="https://raw.githubusercontent.com/Gozargah/Marzban/master/assets/marzban-logo.png"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
                                         style="width: 28px; height: 28px; object-fit: contain;">
                                    <i class="fa-solid fa-shield-halved" style="color: white; font-size: 20px; display: none;"></i>
                                </div>
                            @elseif($server->isXui())
                                <div style="width: 45px; height: 45px; background: #0984e3; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-rocket" style="color: white; font-size: 22px;"></i>
                                </div>
                            @else
                                <div style="width: 45px; height: 45px; background: #636e72; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-server" style="color: white; font-size: 20px;"></i>
                                </div>
                            @endif

                            <div>
                                <div style="font-weight: bold; font-size: 16px;">{{ $server->name }}</div>
                                <div style="font-size: 12px; color: var(--tg-theme-hint-color); margin-top: 2px;">
                                    @if($server->host === 'pending')
                                        <span style="color: #e67e22;">⏳ در حال آماده‌سازی توسط ادمین...</span>
                                    @else
                                        {{ $server->isMarzban() ? 'پنل مرزبان' : 'پنل سنایی (X-UI)' }} | {{ parse_url($server->full_url, PHP_URL_HOST) ?? $server->host }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($server->is_active && $server->host !== 'pending')
                            <div style="text-align: left;">
                                <button class="status-badge" style="border: none; background: rgba(0, 184, 148, 0.1); color: #00b894; cursor: pointer;" onclick="checkPing(this, '{{ $server->full_url }}')">
                                    <i class="fa-solid fa-wifi"></i> تست
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- نوار ظرفیت -->
                    @if($server->is_active && $server->host !== 'pending')
                        <div style="margin-top: 10px;">
                            <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; color: var(--tg-theme-hint-color);">
                                <span>👥 {{ $server->current_users }} کاربر</span>
                                <span>ظرفیت: {{ $server->capacity }}</span>
                            </div>
                            <div style="background: rgba(128,128,128,0.2); height: 6px; border-radius: 3px; overflow: hidden;">
                                <div style="background: var(--tg-theme-button-color); height: 100%; width: {{ $server->usage_percent }}%;"></div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <a href="#" class="btn-primary" style="flex: 1; font-size: 13px; padding: 10px; margin: 0; text-decoration: none;">
                                <i class="fa-solid fa-user-plus"></i> ساخت اکانت
                            </a>
                            <a href="{{ $server->full_url }}" target="_blank" class="btn-secondary" style="flex: 1; font-size: 13px; padding: 10px; margin: 0; text-decoration: none;">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> ورود به پنل
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @else
        <div class="card" style="text-align: center; padding: 40px 20px;">
            <i class="fa-solid fa-box-open" style="font-size: 50px; color: var(--tg-theme-hint-color); margin-bottom: 15px; display: block;"></i>
            <p style="margin: 0; font-weight: bold;">هنوز سروری ندارید</p>
            <p class="subtitle" style="margin-top: 5px;">برای شروع کسب درآمد، اولین سرور خود را بخرید.</p>
            <a href="{{ route('webapp.agent.buy-server', ['user_id' => request('user_id')]) }}" class="btn-primary" style="margin-top: 15px; width: auto; display: inline-block; padding: 10px 30px;">
                خرید سرور
            </a>
        </div>
    @endif

    <!-- سرورهای VPN و محصولات تعریف‌شده در پنل اصلی -->
    @if(isset($vpnServers) && $vpnServers->count() > 0)
        <h3 style="margin-top: 25px;">سرورهای VPN (تعریف شده در پنل)</h3>

        @foreach($vpnServers as $vpnServer)
            <div class="card" style="margin-bottom: 12px;">
                <div class="list-item" style="border-bottom: none;">
                    <div>
                        <div style="font-weight: bold; font-size: 15px;">
                            {{ $vpnServer->name }}
                            <span style="font-size: 11px; color: var(--tg-theme-hint-color);">
                                ({{ $vpnServer->type === 'sanaei' ? 'سنایی' : ($vpnServer->type === 'marzban' ? 'مرزبان' : $vpnServer->type) }})
                            </span>
                        </div>
                        <div style="font-size: 11px; color: var(--tg-theme-hint-color); margin-top: 3px;">
                            آدرس: {{ ($vpnServer->is_https ? 'https://' : 'http://') . $vpnServer->ip_address }}:{{ $vpnServer->port }}
                        </div>
                        <div style="font-size: 11px; color: var(--tg-theme-hint-color); margin-top: 3px;">
                            ظرفیت: {{ $vpnServer->capacity ?: 'نامحدود' }} | محصولات: {{ $vpnServer->products->count() }}
                        </div>
                    </div>
                </div>

                @if($vpnServer->products->count() > 0)
                    <div style="padding: 0 12px 10px;">
                        @foreach($vpnServer->products as $product)
                            <div class="list-item" style="border-bottom: 1px solid rgba(0,0,0,0.05); padding: 8px 0;">
                                <div>
                                    <div style="font-size: 14px; font-weight: 500;">
                                        {{ $product->name }}
                                    </div>
                                    <div style="font-size: 11px; color: var(--tg-theme-hint-color); margin-top: 2px;">
                                        پروتکل: {{ strtoupper($product->protocol) }} |
                                        مدت: {{ $product->period_days }} روز |
                                        ترافیک:
                                        @if($product->traffic_limit === 0)
                                            نامحدود
                                        @else
                                            {{ number_format($product->traffic_limit) }} GB
                                        @endif
                                    </div>
                                </div>
                                <div style="text-align: left; font-size: 12px; font-weight: bold;">
                                    {{ number_format((int) $product->base_price) }}<br>
                                    <span style="font-size: 10px; color: var(--tg-theme-hint-color);">تومان (قیمت پایه)</span>
                                    <div style="margin-top: 6px;">
                                        <input type="text"
                                               class="form-control"
                                               placeholder="نام کاربری دلخواه (اختیاری)"
                                               style="margin-bottom: 4px; font-size: 11px; padding: 4px 8px;"
                                               data-username-input="product-{{ $product->id }}">
                                        <button type="button"
                                                style="padding: 4px 10px; font-size: 11px; border-radius: 999px; border: none; background: var(--tg-theme-button-color); color: var(--tg-theme-button-text-color); cursor: pointer;"
                                                onclick="buyVpnProduct({{ $product->id }}, '{{ addslashes($product->name) }}')">
                                            خرید
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    <!-- تراکنش‌ها -->
    @if($recentTransactions->count() > 0)
        <h3 style="margin-top: 25px;">تراکنش‌های اخیر</h3>
        <div class="card">
            @foreach($recentTransactions as $tx)
                <div class="list-item">
                    <div>
                        <div style="font-size: 14px;">{{ $tx->description }}</div>
                        <div style="font-size: 11px; color: var(--tg-theme-hint-color);">{{ $tx->created_at->diffForHumans() }}</div>
                    </div>
                    <div style="font-weight: bold; direction: ltr; color: {{ $tx->amount > 0 ? '#00b894' : '#d63031' }};">
                        {{ number_format($tx->amount) }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

@endsection

@section('scripts')
    <script>
        function checkPing(btn, url) {
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            btn.disabled = true;

            setTimeout(() => {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> آنلاین';
                btn.style.color = '#00b894';
                btn.style.background = 'rgba(0, 184, 148, 0.2)';
            }, 1500);
        }

        async function buyVpnProduct(productId, productName) {
            try {
                const usernameInput = document.querySelector('[data-username-input=\"product-' + productId + '\"]');
                const username = usernameInput ? usernameInput.value.trim() : '';

                const response = await sendRequest("{{ route('webapp.agent.buy-product', [], false) }}", "POST", {
                    product_id: productId,
                    username: username,
                });

                if (response.success) {
                    const info = response.data || {};
                    const link = info.config_link || info.subscription_url || '';
                    const linkLabel = info.config_link ? 'لینک کانفیگ:' : 'لینک اشتراک:';
                    const messageLines = [
                        'اکانت با موفقیت ساخته شد.',
                        '',
                        'سرور: ' + (info.server_name || ''),
                        'محصول: ' + (info.product_name || productName),
                        'نام کاربری: ' + (info.username || ''),
                        '',
                        linkLabel,
                        link
                    ];

                    if (typeof tg !== 'undefined' && tg && tg.showPopup) {
                        tg.showPopup({
                            title: 'موفق',
                            message: messageLines.join('\n'),
                            buttons: [{ type: 'ok' }]
                        });
                    } else {
                        alert(messageLines.join('\n'));
                    }
                } else {
                    const msg = response.message || 'خطا در ساخت اکانت VPN';
                    if (typeof tg !== 'undefined' && tg && tg.showAlert) {
                        tg.showAlert(msg);
                    } else {
                        alert(msg);
                    }
                }
            } catch (e) {
                if (typeof tg !== 'undefined' && tg && tg.showAlert) {
                    tg.showAlert('خطای غیرمنتظره در ساخت اکانت');
                } else {
                    alert('خطای غیرمنتظره در ساخت اکانت');
                }
            }
        }
    </script>
@endsection
