@extends('layouts.webapp')

@section('content')
    <div class="card">
        <h2>شارژ کیف پول</h2>
        <p class="subtitle">لطفاً مبلغ را کارت به کارت کرده و رسید را آپلود کنید.</p>

        <div style="background: rgba(52, 152, 219, 0.1); padding: 10px; border-radius: 8px; margin-bottom: 15px;">
            <div style="font-size: 13px;">شماره کارت جهت واریز:</div>
            <div style="font-weight: bold; letter-spacing: 2px; text-align: center; margin: 5px 0; font-size: 18px;">
                {{ $cardNumber }}
            </div>
            <div style="font-size: 12px; text-align: center;">{{ $cardName }}</div>
        </div>
    </div>

    <form id="depositForm">
        <div class="card">
            <label style="font-size: 14px; font-weight: bold;">مبلغ (تومان) *</label>
            <input type="number" name="amount" class="form-control" placeholder="مثلا 500000" style="margin-top: 5px;">

            <label style="font-size: 14px; font-weight: bold;">تصویر رسید *</label>
            <div onclick="document.getElementById('receipt').click()"
                 style="border: 2px dashed var(--tg-theme-hint-color); border-radius: 8px; padding: 20px; text-align: center; margin-top: 10px; cursor: pointer;">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 24px; color: var(--tg-theme-button-color);"></i>
                <div style="margin-top: 5px; font-size: 12px;">انتخاب تصویر رسید</div>
                <img id="preview" src="" style="width: 100%; margin-top: 10px; display: none; border-radius: 8px;">
            </div>
            <input type="file" name="receipt" id="receipt" accept="image/*" style="display: none;" onchange="showPreview(this)">
        </div>
    </form>
@endsection

@section('scripts')
    <script>
        if (typeof tg !== 'undefined' && tg && tg.MainButton) {
            tg.MainButton.setText("ثبت درخواست شارژ");
            tg.MainButton.show();
        }

        function showPreview(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                    document.getElementById('preview').style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        if (typeof tg !== 'undefined' && tg && tg.MainButton) {
        tg.MainButton.onClick(async () => {
            const amount = document.querySelector('input[name="amount"]').value;
            const fileInput = document.getElementById('receipt');

            if (!amount || amount < 50000) {
                tg.showAlert('حداقل مبلغ شارژ ۵۰,۰۰۰ تومان است');
                return;
            }
            if (fileInput.files.length === 0) {
                tg.showAlert('لطفاً تصویر رسید را انتخاب کنید');
                return;
            }

            tg.MainButton.showProgress();

            // چون فایل داریم باید دستی FormData بسازیم و تابع sendRequest ساده اینجا جواب نمیده
            // اما برای سادگی اینجا کپی می‌کنیم
            const formData = new FormData();
            formData.append('amount', amount);
            formData.append('receipt', fileInput.files[0]);
            formData.append('_telegram_init_data', tg.initData || '');

            try {
                // ساخت URL بر اساس آدرس فعلی (مشکل http/https نداشته باشد)
                const currentUrl = window.location.href;
                const postUrl = currentUrl.split('?')[0];

                const response = await fetch(postUrl, {
                    method: "POST",
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                let result;
                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    result = await response.json();
                } else {
                    const text = await response.text();
                    console.error('Non-JSON response from deposit:', text.substring(0, 500));
                    throw new Error('پاسخ سرور نامعتبر است');
                }
                tg.MainButton.hideProgress();

                if (result.success) {
                    const successMessage = result.message || 'درخواست شارژ با موفقیت ثبت شد.';
                    const popupSupported = tg && typeof tg.showPopup === 'function';
                    if (popupSupported) {
                        try {
                            tg.showPopup(
                                {
                                    title: '✅ ثبت شد',
                                    message: successMessage,
                                    buttons: [{type: 'ok'}],
                                },
                                () => {
                                    window.location.href = '/agent/dashboard';
                                }
                            );
                        } catch (e) {
                            console.error('Telegram showPopup failed on deposit', e);
                            alert(successMessage);
                            window.location.href = '/agent/dashboard';
                        }
                    } else {
                        alert(successMessage);
                        window.location.href = '/agent/dashboard';
                    }
                } else {
                    tg.showAlert(result.message);
                }

            } catch (e) {
                tg.MainButton.hideProgress();
                console.error('Deposit request error:', e);
                const msg = 'خطا در ارسال اطلاعات: ' + (e.message || '');
                if (tg && typeof tg.showAlert === 'function') {
                    try {
                        tg.showAlert(msg);
                    } catch (err) {
                        console.error('Telegram showAlert failed on deposit', err);
                        alert(msg);
                    }
                } else {
                    alert(msg);
                }
            }
        });
        }
    </script>
@endsection
