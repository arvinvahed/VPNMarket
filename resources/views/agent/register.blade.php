<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پنل نمایندگی</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --tg-color: var(--tg-theme-button-color, #3498db);
            --tg-bg: var(--tg-theme-secondary-bg-color, #f0f2f5);
            --tg-text: var(--tg-theme-text-color, #2c3e50);
            --tg-hint: var(--tg-theme-hint-color, #7f8c8d);
            --card-bg: var(--tg-theme-bg-color, #ffffff);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--tg-bg);
            color: var(--tg-text);
            margin: 0;
            padding: 16px;
            padding-bottom: 100px;
            overflow-x: hidden;
        }

        .card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.03);
        }

        h1 { font-size: 22px; margin-bottom: 10px; text-align: center; }
        .subtitle { text-align: center; color: var(--tg-hint); font-size: 13px; margin-bottom: 20px; }

        .features { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
        .feature-item {
            background: rgba(52, 152, 219, 0.1);
            padding: 12px;
            border-radius: 12px;
            text-align: center;
            font-size: 12px;
            border: 1px solid rgba(52, 152, 219, 0.2);
        }
        .feature-item i { display: block; font-size: 18px; color: var(--tg-color); margin-bottom: 5px; }

        .pricing-banner {
            background: linear-gradient(135deg, var(--tg-color), #2980b9);
            color: white;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .pricing-banner .amount { font-size: 24px; font-weight: bold; display: block; }

        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 600; }

        input, textarea {
            width: 100%;
            background: var(--tg-bg);
            border: 1.5px solid transparent;
            color: var(--tg-text);
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.2s;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--tg-color);
            background: var(--card-bg);
        }

        .main-button {
            position: fixed;
            bottom: 10px;
            left: 16px;
            right: 16px;
            padding: 16px;
            background: var(--tg-color);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            cursor: pointer;
            z-index: 1000;
        }

        .main-button:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }

        .file-upload-wrapper {
            position: relative;
            border: 2px dashed var(--tg-hint);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
        }
        .file-upload-wrapper input {
            position: absolute;
            opacity: 0;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        #imagePreview {
            width: 100%;
            max-height: 150px;
            object-fit: contain;
            display: none;
            margin-top: 10px;
            border-radius: 8px;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: none;
            font-size: 13px;
        }

        .debug-info {
            background: #f0f0f0;
            padding: 10px;
            font-size: 10px;
            margin-top: 20px;
            border-radius: 5px;
            word-break: break-all;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>🏢 پنل نمایندگی</h1>
    <p class="subtitle">به جمع نمایندگان برتر ما بپیوندید</p>

    <div class="features">
        <div class="feature-item"><i class="fa-solid fa-bolt"></i>سرعت بالا</div>
        <div class="feature-item"><i class="fa-solid fa-shield-halved"></i>امنیت کامل</div>
        <div class="feature-item"><i class="fa-solid fa-id-card"></i>پنل اختصاصی</div>
        <div class="feature-item"><i class="fa-solid fa-headset"></i>پشتیبانی</div>
    </div>
</div>

<div class="pricing-banner">
    <span>هزینه شروع نمایندگی از</span>
    <span class="amount">{{ number_format($registrationFee) }} تومان</span>
    <small>با انتخاب پلن مورد نظر خود</small>
</div>

<div class="error-message" id="errorMessage"></div>

<form id="agentForm">
    @csrf
    <!-- ✅ hidden inputs -->
    <input type="hidden" id="user_id" name="user_id" value="{{ $user->telegram_chat_id ?? '' }}">
    <input type="hidden" id="_telegram_init_data" name="_telegram_init_data" value="">

    <div class="card">
        <div class="form-group">
            <label><i class="fa-solid fa-phone"></i> شماره تماس *</label>
            <input type="tel" name="phone" id="phone" placeholder="09123456789" required>
        </div>

        <div class="form-group">
            <label><i class="fa-solid fa-at"></i> آیدی تلگرام</label>
            <input type="text" name="telegram_id" placeholder="@username">
        </div>
    </div>

    <div class="card">
        <label><i class="fa-solid fa-star"></i> انتخاب پلن نمایندگی *</label>
        <div class="plan-list">
            @foreach($plans as $index => $plan)
                <label class="plan-item">
                    <input type="radio" name="plan_id" value="{{ $plan->id }}" {{ $index === 0 ? 'checked' : '' }}>
                    <div class="plan-content">
                        <div class="plan-header">
                            <span class="plan-name">{{ $plan->name }}</span>
                            <span class="plan-type">
                                @if($plan->type === 'quota')
                                    پلن محدود (تا {{ number_format($plan->account_limit) }} اکانت)
                                @else
                                    پلن نامحدود (پرداخت به ازای هر اکانت)
                                @endif
                            </span>
                        </div>
                        <div class="plan-pricing">
                            <span>هزینه اشتراک: {{ number_format($plan->price) }} تومان</span>
                            @if($plan->type === 'pay_as_you_go')
                                <span>هزینه هر اکانت: {{ number_format($plan->price_per_account) }} تومان</span>
                            @endif
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
    </div>

        <div class="card">
        <label><i class="fa-solid fa-receipt"></i> آپلود رسید واریز *</label>
        <div class="file-upload-wrapper" onclick="document.getElementById('payment_receipt').click()">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 30px; color: var(--tg-color)"></i>
            <p style="font-size: 12px; margin-top: 5px;">برای انتخاب عکس کلیک کنید</p>
            <input type="file" id="payment_receipt" name="payment_receipt" accept="image/*" required>
            <img id="imagePreview" src="" alt="Preview" style="display: none; max-width: 100%; margin-top: 10px;">
        </div>
        <div class="form-group" style="margin-top: 15px;">
            <label>مبلغ واریزی (تومان) *</label>
            <input type="number" name="payment_amount" id="payment_amount" value="30000" min="30000" required>
        </div>
    </div>

    <button type="submit" id="submitBtn" class="main-button">ثبت و ارسال درخواست</button>
</form>

    <script>
    const tg = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
    if (tg) {
        tg.ready();
        tg.expand();
    }

    const isPendingAgent = @json(isset($agent) && $agent->status === 'pending');

    const initData = tg ? (tg.initData || '') : '';
    document.getElementById('_telegram_init_data').value = initData;

    if (isPendingAgent) {
        const btn = document.getElementById('submitBtn');
        const errorDiv = document.getElementById('errorMessage');
        if (btn) {
            btn.disabled = true;
            btn.innerText = 'درخواست شما در حال بررسی است';
        }
        if (errorDiv) {
            errorDiv.textContent = 'درخواست نمایندگی شما قبلاً ثبت شده و در انتظار تایید ادمین است.';
            errorDiv.style.display = 'block';
        }
    }

    const paymentInput = document.getElementById('payment_receipt');
    const previewImage = document.getElementById('imagePreview');
    if (paymentInput && previewImage) {
        paymentInput.addEventListener('change', function () {
            console.log('payment_receipt change event fired', this.files);
            const file = this.files && this.files[0];
            if (!file) {
                console.warn('No file selected for payment_receipt');
                const errorDiv = document.getElementById('errorMessage');
                if (errorDiv) {
                    errorDiv.textContent = 'فایل رسید انتخاب نشد. لطفاً دوباره امتحان کنید.';
                    errorDiv.style.display = 'block';
                }
                previewImage.style.display = 'none';
                return;
            }

            try {
                const url = URL.createObjectURL(file);
                console.log('Preview URL created:', url);
                previewImage.src = url;
                previewImage.style.display = 'block';
            } catch (e) {
                console.error('Error while creating preview URL:', e);
                const errorDiv = document.getElementById('errorMessage');
                if (errorDiv) {
                    errorDiv.textContent = 'خطا در نمایش پیش‌نمایش تصویر.';
                    errorDiv.style.display = 'block';
                }
            }
        });
    }

    const formElement = document.getElementById('agentForm');
    if (formElement && !isPendingAgent) {
        console.log('bind agentForm submit');
        formElement.onsubmit = async (e) => {
        e.preventDefault();

        const btn = document.getElementById('submitBtn');
        const errorDiv = document.getElementById('errorMessage');

        // مخفی کردن خطای قبلی
        errorDiv.style.display = 'none';

        // ولیدیشن ساده
        const phone = document.getElementById('phone').value.trim();
        if (!phone || phone.length < 10) {
            errorDiv.textContent = 'لطفاً شماره تماس معتبر وارد کنید';
            errorDiv.style.display = 'block';
            return;
        }

        const fileInput = document.getElementById('payment_receipt');
        if (!fileInput.files || fileInput.files.length === 0) {
            errorDiv.textContent = 'لطفاً عکس رسید را انتخاب کنید';
            errorDiv.style.display = 'block';
            return;
        }

        const selectedPlan = document.querySelector('input[name="plan_id"]:checked');
        if (!selectedPlan) {
            errorDiv.textContent = 'لطفاً یک پلن نمایندگی انتخاب کنید';
            errorDiv.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> در حال ارسال...';

        const formData = new FormData(e.target);

        // ✅ ساخت URL صحیح
        const currentUrl = window.location.href;
        const baseUrl = currentUrl.split('?')[0]; // حذف query params
        const postUrl = baseUrl; // POST به همین URL

        console.log('Submitting to:', postUrl);
        console.log('Form data:', {
            phone: formData.get('phone'),
            user_id: formData.get('user_id'),
            has_file: !!formData.get('payment_receipt')
        });

        try {
            const response = await fetch(postUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Telegram-Init-Data': initData,
                    'Accept': 'application/json'
                }
            });

            console.log('Response status:', response.status);

            // چک کردن content-type
            const contentType = response.headers.get('content-type');
            console.log('Content-Type:', contentType);

            let result;
            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const text = await response.text();
                console.error('Non-JSON response:', text.substring(0, 500));
                throw new Error('پاسخ سرور نامعتبر است');
            }

            if (response.ok && result.success) {
                const successMessage = result.message || 'درخواست شما با موفقیت ثبت شد.';
                const popupSupported = tg && typeof tg.showPopup === 'function';

                if (popupSupported) {
                    try {
                        tg.showPopup(
                            {
                                title: '✅ موفقیت‌آمیز',
                                message: successMessage,
                                buttons: [{ type: 'ok' }],
                            },
                            () => {
                                try {
                                    tg.close();
                                } catch (e) {
                                    console.error('Telegram close failed', e);
                                }
                            }
                        );
                    } catch (e) {
                        console.error('Telegram showPopup failed', e);
                        alert(successMessage);
                    }
                } else {
                    alert(successMessage);
                }
            } else {
                throw new Error(result.message || 'خطا در ثبت درخواست');
            }
        } catch (err) {
            console.error('Error:', err);
            errorDiv.textContent = '❌ ' + err.message;
            errorDiv.style.display = 'block';

            const alertSupported = tg && typeof tg.showAlert === 'function';
            const errorText = 'خطا: ' + err.message;

            if (alertSupported) {
                try {
                    tg.showAlert(errorText);
                } catch (e) {
                    console.error('Telegram showAlert failed', e);
                    alert(errorText);
                }
            } else {
                alert(errorText);
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'ثبت و ارسال درخواست';
        }
        };
    } else {
        console.log('agentForm not found or isPendingAgent == true, submit handler not bound', {
            hasForm: !!formElement,
            isPendingAgent: isPendingAgent,
        });
    }
</script>
</body>
</html>
