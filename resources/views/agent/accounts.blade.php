@extends('layouts.webapp')

@section('content')
    <style>
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: var(--tg-theme-secondary-bg-color, #fff);
            padding: 12px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: var(--tg-theme-text-color, #000);
        }
        .stat-label {
            font-size: 11px;
            color: var(--tg-theme-hint-color, #999);
            margin-top: 4px;
        }

        /* Search & Filters */
        .search-box {
            position: relative;
            margin-bottom: 15px;
        }
        .search-input {
            width: 100%;
            padding: 12px 40px 12px 12px;
            border-radius: 10px;
            border: 1px solid var(--tg-theme-hint-color, #ccc);
            background: var(--tg-theme-bg-color, #f5f5f5);
            color: var(--tg-theme-text-color, #000);
            box-sizing: border-box;
            font-size: 14px;
        }
        .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--tg-theme-hint-color, #999);
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 10px;
            margin-bottom: 10px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
        }
        .filter-tabs::-webkit-scrollbar {
            display: none; /* Chrome/Safari */
        }
        .filter-tab {
            padding: 6px 12px;
            border-radius: 20px;
            background: var(--tg-theme-secondary-bg-color, #fff);
            color: var(--tg-theme-hint-color, #999);
            font-size: 12px;
            white-space: nowrap;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .filter-tab.active {
            background: var(--tg-theme-button-color, #3390ec);
            color: var(--tg-theme-button-text-color, #fff);
        }

        /* Account Card */
        .account-card {
            background: var(--tg-theme-secondary-bg-color, #fff);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        .account-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .account-info h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--tg-theme-text-color, #000);
        }
        .account-info span {
            font-size: 11px;
            color: var(--tg-theme-hint-color, #999);
            display: block;
            margin-top: 2px;
        }
        .status-badge {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 6px;
        }
        .status-active { background: #e6fffa; color: #00b894; }
        .status-expired { background: #ffe6e6; color: #d63031; }
        .status-pending { background: #fffce6; color: #fdcb6e; }

        .account-details {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: var(--tg-theme-hint-color, #888);
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-action {
            flex: 1;
            padding: 8px;
            border-radius: 8px;
            border: none;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .btn-copy {
            background: var(--tg-theme-button-color, #3390ec);
            color: var(--tg-theme-button-text-color, #fff);
        }
        .btn-renew {
            background: #f0f2f5;
            color: #2ecc71;
        }
        .btn-delete {
            background: #fff0f0;
            color: #e74c3c;
        }

        /* Modals */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-content {
            background: var(--tg-theme-secondary-bg-color, #fff);
            padding: 20px;
            border-radius: 16px;
            width: 100%;
            max-width: 320px;
            text-align: center;
            position: relative;
        }
        .modal-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .modal-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-modal {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-confirm { background: var(--tg-theme-button-color, #3390ec); color: #fff; }
        .btn-cancel { background: #f0f2f5; color: #555; }
        
        /* Pagination */
        .pagination-wrapper {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
        }
        .pagination-wrapper nav {
            display: flex;
            justify-content: center;
        }
    </style>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-value">{{ number_format($stats['total'] ?? 0) }}</span>
            <span class="stat-label">کل کاربران</span>
        </div>
        <div class="stat-card">
            <span class="stat-value" style="color: #00b894;">{{ number_format($stats['active'] ?? 0) }}</span>
            <span class="stat-label">فعال</span>
        </div>
        <div class="stat-card">
            <span class="stat-value" style="color: #d63031;">{{ number_format($stats['expired'] ?? 0) }}</span>
            <span class="stat-label">منقضی</span>
        </div>
        <div class="stat-card">
            <span class="stat-value" style="color: #fdcb6e;">{{ number_format($stats['soon'] ?? 0) }}</span>
            <span class="stat-label">انقضا نزدیک</span>
        </div>
    </div>

    <!-- Search & Filter -->
    <form action="" method="GET" id="searchForm">
        <input type="hidden" name="user_id" value="{{ $user->telegram_chat_id }}">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="search" class="search-input" placeholder="جستجو (نام، یوزرنیم، UUID)..." value="{{ request('search') }}">
        </div>
        
        <div class="filter-tabs">
            <a href="{{ route('webapp.agent.accounts', ['user_id' => $user->telegram_chat_id]) }}" class="filter-tab {{ !request('status') ? 'active' : '' }}">همه</a>
            <a href="{{ route('webapp.agent.accounts', ['status' => 'active', 'user_id' => $user->telegram_chat_id]) }}" class="filter-tab {{ request('status') == 'active' ? 'active' : '' }}">فعال</a>
            <a href="{{ route('webapp.agent.accounts', ['status' => 'expired', 'user_id' => $user->telegram_chat_id]) }}" class="filter-tab {{ request('status') == 'expired' ? 'active' : '' }}">منقضی</a>
            <a href="{{ route('webapp.agent.accounts', ['status' => 'soon', 'user_id' => $user->telegram_chat_id]) }}" class="filter-tab {{ request('status') == 'soon' ? 'active' : '' }}">رو به اتمام</a>
            <a href="{{ route('webapp.agent.accounts', ['status' => 'new', 'user_id' => $user->telegram_chat_id]) }}" class="filter-tab {{ request('status') == 'new' ? 'active' : '' }}">جدید</a>
        </div>
    </form>

    <!-- Accounts List -->
    @if(isset($accounts) && $accounts->count())
        @foreach($accounts as $account)
            <div class="account-card" id="card-{{ $account->id }}">
                <div class="account-header">
                    <div class="account-info">
                        <h3>{{ $account->username }}</h3>
                        <span>{{ $account->product->name ?? 'محصول حذف شده' }}</span>
                    </div>
                    @php
                        $statusClass = match($account->status) {
                            'active' => 'status-active',
                            'expired' => 'status-expired',
                            'pending' => 'status-pending',
                            default => ''
                        };
                        $statusText = match($account->status) {
                            'active' => 'فعال',
                            'expired' => 'منقضی',
                            'pending' => 'در انتظار',
                            'failed' => 'ناموفق',
                            default => $account->status
                        };
                    @endphp
                    <span class="status-badge {{ $statusClass }}">{{ $statusText }}</span>
                </div>

                <div class="account-details">
                    <div>
                        <i class="fas fa-server"></i> {{ $account->server->name ?? '-' }}
                    </div>
                    <div>
                        <i class="fas fa-calendar-alt"></i> 
                        {{ $account->expired_at ? \Morilog\Jalali\Jalalian::fromDateTime($account->expired_at)->format('Y/m/d') : 'نامحدود' }}
                    </div>
                </div>

                <div class="action-buttons">
                    @php
                        $link = $account->config_link ?: $account->subscription_url;
                        $linkLabel = $account->config_link ? 'لینک' : 'ساب';
                    @endphp
                    <button class="btn-action btn-copy" onclick="copySubLink('{{ addslashes($link) }}')">
                        <i class="fas fa-copy"></i> کپی {{ $linkLabel }}
                    </button>
                    
                    <button class="btn-action btn-renew" onclick="openRenewModal({{ $account->id }}, '{{ $account->username }}')">
                        <i class="fas fa-sync-alt"></i> تمدید
                    </button>
                    
                    <button class="btn-action btn-delete" onclick="openDeleteModal({{ $account->id }}, '{{ $account->username }}')">
                        <i class="fas fa-trash"></i> حذف
                    </button>
                </div>
            </div>
        @endforeach

        <div class="pagination-wrapper">
            {{ $accounts->links('pagination::simple-default') }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: var(--tg-theme-hint-color);">
            <i class="fas fa-box-open" style="font-size: 40px; margin-bottom: 10px; opacity: 0.5;"></i>
            <p>هیچ اکانتی یافت نشد.</p>
        </div>
    @endif

    <!-- Renew Modal -->
    <div id="renewModal" class="modal-overlay">
        <div class="modal-content">
            <h3 class="modal-title">تمدید اکانت <span id="renewUsername" style="color: var(--tg-theme-link-color);"></span></h3>
            
            <label style="display: block; text-align: right; font-size: 12px; margin-bottom: 5px;">تعداد روز تمدید:</label>
            <input type="number" id="renewDays" class="modal-input" value="30" min="1">
            
            <label style="display: block; text-align: right; font-size: 12px; margin-bottom: 5px;">حجم اضافه (گیگابایت) - اختیاری:</label>
            <input type="number" id="renewTraffic" class="modal-input" placeholder="مثلا 10 (خالی = بدون تغییر)" min="0">
            
            <div class="modal-actions">
                <button class="btn-modal btn-cancel" onclick="closeModal('renewModal')">انصراف</button>
                <button class="btn-modal btn-confirm" onclick="submitRenew()">تایید و تمدید</button>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content">
            <h3 class="modal-title" style="color: #e74c3c;">حذف اکانت</h3>
            <p style="font-size: 13px; margin-bottom: 20px;">
                آیا مطمئن هستید که می‌خواهید اکانت <b id="deleteUsername"></b> را حذف کنید؟
                <br>
                <span style="font-size: 11px; color: #999;">این عملیات غیرقابل بازگشت است و اکانت از سرور نیز پاک خواهد شد.</span>
            </p>
            
            <div class="modal-actions">
                <button class="btn-modal btn-cancel" onclick="closeModal('deleteModal')">انصراف</button>
                <button class="btn-modal btn-confirm" style="background: #e74c3c;" onclick="submitDelete()">بله، حذف کن</button>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
<script>
    let currentAccountId = null;
    let csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function copySubLink(url) {
        if (!url) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
                showTgAlert('لینک کپی شد!');
            }).catch(() => {
                showTgAlert('خطا در کپی، لطفا دستی کپی کنید.');
            });
        } else {
            showTgAlert(url); // Fallback
        }
    }

    function showTgAlert(msg) {
        if (typeof tg !== 'undefined' && tg && tg.showAlert) {
            tg.showAlert(msg);
        } else {
            alert(msg);
        }
    }

    // Modal Functions
    function openRenewModal(id, username) {
        currentAccountId = id;
        document.getElementById('renewUsername').innerText = username;
        document.getElementById('renewDays').value = 30;
        document.getElementById('renewTraffic').value = '';
        document.getElementById('renewModal').style.display = 'flex';
    }

    function openDeleteModal(id, username) {
        currentAccountId = id;
        document.getElementById('deleteUsername').innerText = username;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        currentAccountId = null;
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    }

    // Get Telegram WebApp Init Data
    function getTgInitData() {
        if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData) {
            return window.Telegram.WebApp.initData;
        }
        return '';
    }

    // Submit Actions
    function submitRenew() {
        if (!currentAccountId) return;
        
        const days = document.getElementById('renewDays').value;
        const traffic = document.getElementById('renewTraffic').value;
        const btn = document.querySelector('#renewModal .btn-confirm');
        
        if (!days || days < 1) {
            showTgAlert('لطفا تعداد روز معتبر وارد کنید.');
            return;
        }

        btn.disabled = true;
        btn.innerText = 'در حال انجام...';

        fetch(`/agent/accounts/${currentAccountId}/renew`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Telegram-Init-Data': getTgInitData()
            },
            body: JSON.stringify({ days: days, traffic: traffic })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTgAlert(data.message || 'تمدید با موفقیت انجام شد.');
                location.reload();
            } else {
                showTgAlert(data.message || 'خطا در تمدید اکانت.');
                btn.disabled = false;
                btn.innerText = 'تایید و تمدید';
            }
        })
        .catch(err => {
            console.error(err);
            showTgAlert('خطای ارتباط با سرور.');
            btn.disabled = false;
            btn.innerText = 'تایید و تمدید';
        });
    }

    function submitDelete() {
        if (!currentAccountId) return;
        
        const btn = document.querySelector('#deleteModal .btn-confirm');
        btn.disabled = true;
        btn.innerText = 'در حال حذف...';

        fetch(`/agent/accounts/${currentAccountId}/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Telegram-Init-Data': getTgInitData()
            },
            body: JSON.stringify({})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTgAlert('اکانت با موفقیت حذف شد.');
                const card = document.getElementById(`card-${currentAccountId}`);
                if (card) card.remove();
                closeModal('deleteModal');
            } else {
                showTgAlert(data.message || 'خطا در حذف اکانت.');
            }
        })
        .catch(err => {
            console.error(err);
            showTgAlert('خطای ارتباط با سرور.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = 'بله، حذف کن';
            // If delete failed, keep modal open. If success, it's closed in then block.
            // Actually, we should close modal on success only.
        });
    }
</script>
@endsection
