@extends('layouts.webapp')

@section('content')
    <h2 style="text-align: center;">خرید اکانت VPN برای نماینده</h2>

    @if(isset($vpnServers) && $vpnServers->count())
        <p class="subtitle" style="text-align: center; margin-bottom: 10px;">ابتدا سرور مورد نظر را انتخاب کنید</p>

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
                                                onclick="buyVpnProduct(this, {{ $product->id }}, '{{ addslashes($product->name) }}')">
                                            خرید
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="padding: 0 12px 10px; font-size: 12px; color: var(--tg-theme-hint-color);">
                        برای این سرور هنوز محصول فعالی تعریف نشده است.
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <p class="subtitle" style="text-align: center; margin-top: 20px;">
            هنوز هیچ سرور VPN فعالی برای نمایندگان تعریف نشده است.
        </p>
    @endif

@endsection

@section('scripts')
    <script>
        async function buyVpnProduct(btn, productId, productName) {
            const originalText = btn.innerText;
            btn.innerText = 'لطفا صبر کنید...';
            btn.disabled = true;
            btn.style.opacity = '0.7';

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
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        }
    </script>
@endsection
