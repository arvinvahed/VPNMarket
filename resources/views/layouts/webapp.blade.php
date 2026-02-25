<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>پنل نمایندگی</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --tg-theme-bg-color: var(--tg-theme-bg-color, #f0f2f5);
            --tg-theme-text-color: var(--tg-theme-text-color, #000000);
            --tg-theme-hint-color: var(--tg-theme-hint-color, #999999);
            --tg-theme-link-color: var(--tg-theme-link-color, #2481cc);
            --tg-theme-button-color: var(--tg-theme-button-color, #3390ec);
            --tg-theme-button-text-color: var(--tg-theme-button-text-color, #ffffff);
            --tg-theme-secondary-bg-color: var(--tg-theme-secondary-bg-color, #ffffff);
        }

        body {
            background-color: var(--tg-theme-bg-color);
            color: var(--tg-theme-text-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 16px;
            padding-bottom: 80px; /* فضای خالی برای دکمه پایین */
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        .card {
            background-color: var(--tg-theme-secondary-bg-color);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        h1, h2, h3 { margin: 0 0 10px 0; font-size: 18px; }
        .subtitle { color: var(--tg-theme-hint-color); font-size: 13px; margin-bottom: 15px; display: block; }

        /* دکمه‌ها */
        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 10px;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }
        .btn-primary { background-color: var(--tg-theme-button-color); color: var(--tg-theme-button-text-color); }
        .btn-secondary { background-color: rgba(128, 128, 128, 0.1); color: var(--tg-theme-text-color); }

        /* استایل لیست‌ها */
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .list-item:last-child { border-bottom: none; }

        /* Input ها */
        .form-control {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--tg-theme-hint-color);
            background: var(--tg-theme-bg-color);
            color: var(--tg-theme-text-color);
            margin-bottom: 15px;
            box-sizing: border-box;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-success { background: #e6fffa; color: #00b894; }
        .status-pending { background: #fffce6; color: #fdcb6e; }
        .status-danger { background: #ffe6e6; color: #d63031; }

        /* لودر */
        .loader { display: none; text-align: center; margin-top: 10px; }
    </style>
</head>
<body>

@yield('content')

<script>
    const tg = window.Telegram.WebApp;
    tg.ready();
    tg.expand();

    // هندل کردن دکمه بازگشت برای صفحات غیر از داشبورد
    const currentPath = window.location.pathname;
    if (!currentPath.includes('dashboard')) {
        tg.BackButton.show();
        tg.BackButton.onClick(() => {
            window.history.back();
        });
    } else {
        tg.BackButton.hide();
    }

    // تابع کمکی برای ارسال درخواست‌ها
    async function sendRequest(url, method, data) {
        const formData = new FormData();
        for (const key in data) {
            formData.append(key, data[key]);
        }
        // اضافه کردن initData برای احراز هویت
        formData.append('_telegram_init_data', tg.initData);

        try {
            tg.MainButton.showProgress();
            const response = await fetch(url, {
                method: method,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Telegram-Init-Data': tg.initData
                },
                body: formData
            });

            const contentType = response.headers.get('content-type') || '';
            let result;
            if (contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const text = await response.text();
                result = {
                    success: false,
                    message: text || 'پاسخ نامعتبر از سرور دریافت شد.'
                };
            }

            tg.MainButton.hideProgress();
            return result;
        } catch (error) {
            tg.MainButton.hideProgress();
            tg.showAlert('خطای ارتباط با سرور');
            console.error(error);
            return { success: false };
        }
    }
</script>
@yield('scripts')
</body>
</html>
