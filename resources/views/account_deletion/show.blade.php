<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delete {{ $app_name }} Account</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f6f8;
            --card: #fff;
            --text: #1f2933;
            --muted: #5c6b7a;
            --line: #e4e8ee;
            --brand: #2563eb;
            --brand-dark: #1d4ed8;
            --ok: #0f766e;
            --ok-bg: #ecfdf5;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Noto Sans SC", sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }
        .wrap { max-width: 760px; margin: 0 auto; padding: 24px 16px 48px; }
        .lang { text-align: right; margin-bottom: 12px; }
        .lang button {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 999px;
            padding: 6px 12px;
            margin-left: 6px;
            cursor: pointer;
        }
        .lang button.active { background: var(--brand); color: #fff; border-color: var(--brand); }
        .card {
            background: var(--card);
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            margin-bottom: 16px;
        }
        .brand { display: flex; gap: 14px; align-items: center; }
        .brand img { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; background: #eee; }
        h1 { font-size: 26px; margin: 0 0 6px; }
        .sub { color: var(--muted); margin: 0; }
        h2 { font-size: 18px; margin: 0 0 12px; }
        ol { padding-left: 20px; margin: 0; }
        li { margin: 8px 0; }
        .ok {
            background: var(--ok-bg);
            color: var(--ok);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        label { display: block; font-weight: 600; margin: 14px 0 6px; }
        input, textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 16px;
            font-family: inherit;
        }
        textarea { min-height: 90px; resize: vertical; }
        .error { color: #b91c1c; font-size: 13px; margin-top: 4px; }
        button.submit {
            margin-top: 18px;
            width: 100%;
            border: 0;
            background: var(--brand);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            padding: 13px 16px;
            cursor: pointer;
        }
        button.submit:hover { background: var(--brand-dark); }
        .muted { color: var(--muted); font-size: 14px; }
        .copy { margin: 0 0 10px; }
        .meta { margin: 8px 0 0; color: var(--muted); font-size: 14px; }
        .info-row { display: flex; gap: 12px; margin: 8px 0; font-size: 14px; }
        .info-row dt { min-width: 120px; color: var(--muted); flex-shrink: 0; }
        .info-row dd { margin: 0; }
        [data-lang] { display: none; }
        [data-lang].show { display: block; }
        span[data-lang].show, .copy[data-lang].show { display: inline; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="lang">
        <button type="button" data-set-lang="en">English</button>
        <button type="button" data-set-lang="zh">中文</button>
    </div>

    @if (session('deletion_submitted'))
        <div class="ok" data-lang="en">Your deletion request has been received. We will delete the matched account and related personal data within 7 days.</div>
        <div class="ok" data-lang="zh">已收到你的删除申请。我们将在 7 天内删除匹配到的账号及相关个人数据。</div>
    @endif

    @if ($errors->any())
        <div class="card">
            @foreach ($errors->all() as $error)
                <div class="error">{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card">
        <div class="brand">
            @if (!empty($logo))
                <img src="{{ $logo }}" alt="{{ $app_name }}">
            @endif
            <div>
                <h1 data-lang="en">Delete your {{ $app_name }} account</h1>
                <h1 data-lang="zh">删除 {{ $app_name }} 账号</h1>
                @if ($developer_name || $company_name)
                    <p class="meta" data-lang="en">{{ $app_name }} is developed and operated by {{ $developer_name ?: $company_name }}@if ($company_name && $company_name !== $developer_name) ({{ $company_name }})@endif.</p>
                    <p class="meta" data-lang="zh">{{ $app_name }} 由开发者 {{ $developer_name ?: $company_name }}@if ($company_name && $company_name !== $developer_name)（{{ $company_name }}）@endif 开发并运营。</p>
                @endif
            </div>
        </div>
    </div>

    @if ($developer_name || $company_name || $developer_address || $contact_email)
    <div class="card" id="developer-info">
        <h2 data-lang="en">Developer information</h2>
        <h2 data-lang="zh">开发者信息</h2>
        <dl>
            @if ($developer_name)
                <div class="info-row">
                    <dt data-lang="en">Developer</dt>
                    <dt data-lang="zh">开发者</dt>
                    <dd>{{ $developer_name }}</dd>
                </div>
            @endif
            @if ($company_name)
                <div class="info-row">
                    <dt data-lang="en">Company</dt>
                    <dt data-lang="zh">公司</dt>
                    <dd>{{ $company_name }}</dd>
                </div>
            @endif
            @if ($app_name)
                <div class="info-row">
                    <dt data-lang="en">App name</dt>
                    <dt data-lang="zh">应用名称</dt>
                    <dd>{{ $app_name }}</dd>
                </div>
            @endif
            @if ($developer_address)
                <div class="info-row">
                    <dt data-lang="en">Registered address</dt>
                    <dt data-lang="zh">注册地址</dt>
                    <dd>{{ $developer_address }}</dd>
                </div>
            @endif
            @if ($developer_phone)
                <div class="info-row">
                    <dt data-lang="en">Phone</dt>
                    <dt data-lang="zh">联系电话</dt>
                    <dd>{{ $developer_phone }}</dd>
                </div>
            @endif
            @if ($contact_email)
                <div class="info-row">
                    <dt data-lang="en">Contact email</dt>
                    <dt data-lang="zh">联系邮箱</dt>
                    <dd>{{ $contact_email }}</dd>
                </div>
            @endif
        </dl>
        <p class="muted">
            <span data-lang="en">This account deletion page is provided by {{ $developer_name ?: $company_name ?: $app_name }}, the Google Play developer of {{ $app_name }}@if ($company_name) ({{ $company_name }})@endif.</span>
            <span data-lang="zh">本账号删除页面由 Google Play 开发者 {{ $developer_name ?: $company_name ?: $app_name }}@if ($company_name && $company_name !== $developer_name)（{{ $company_name }}）@endif 提供。</span>
        </p>
    </div>
    @endif

    <div class="card">
        <h2 data-lang="en">How to request account deletion</h2>
        <h2 data-lang="zh">如何申请删除账号</h2>
        <div data-lang="en">
            <p>You can request deletion of your {{ $app_name }} account and related personal data from {{ $developer_name ?: $company_name ?: $app_name }} in either of the following ways. You do not need to keep the app installed.</p>
            <ol>
                <li><strong>In the app:</strong> open {{ $app_name }} → Me / Settings → Delete account (or Sign out / Cancel account) → confirm. The account is deactivated immediately.</li>
                <li><strong>On this page:</strong> fill in the form below with the email, login account, or user ID used in {{ $app_name }}, then submit. We process matched requests within 7 days.</li>
            </ol>
        </div>
        <div data-lang="zh">
            <p>你可以通过以下任一方式向 {{ $developer_name ?: $company_name ?: $app_name }} 申请删除 {{ $app_name }} 账号及相关个人数据，无需保持应用已安装。</p>
            <ol>
                <li><strong>应用内：</strong>打开 {{ $app_name }} → 我的 / 设置 → 删除账号（或注销账号）→ 确认。账号会立即停用。</li>
                <li><strong>本页面：</strong>在下方表单填写你在 {{ $app_name }} 使用的邮箱、登录账号或用户 ID 并提交。匹配到的申请将在 7 天内处理。</li>
            </ol>
        </div>
    </div>

    <div class="card">
        <h2 data-lang="en">Request deletion</h2>
        <h2 data-lang="zh">提交删除申请</h2>
        <form method="post" action="{{ url()->current() }}">
            @csrf
            <label>
                <span class="copy" data-lang="en">Account, email, or user ID</span>
                <span class="copy" data-lang="zh">账号、邮箱或用户 ID</span>
            </label>
            <input type="text" name="identifier" value="{{ old('identifier') }}" required maxlength="191" placeholder="email / account / user ID">

            <label>
                <span class="copy" data-lang="en">Contact email (optional)</span>
                <span class="copy" data-lang="zh">联系邮箱（选填）</span>
            </label>
            <input type="email" name="email" value="{{ old('email') }}" maxlength="100">

            <label>
                <span class="copy" data-lang="en">Additional details (optional)</span>
                <span class="copy" data-lang="zh">补充说明（选填）</span>
            </label>
            <textarea name="reason" maxlength="500">{{ old('reason') }}</textarea>

            <button class="submit" type="submit">
                <span class="copy" data-lang="en">Submit deletion request</span>
                <span class="copy" data-lang="zh">提交删除申请</span>
            </button>
        </form>
        @if ($contact_email)
            <p class="muted" style="margin-top:14px">
                <span data-lang="en">If you cannot identify your account, contact {{ $contact_email }}.</span>
                <span data-lang="zh">若无法确认账号，请联系 {{ $contact_email }}。</span>
            </p>
        @endif
    </div>

    <div class="card" id="data-deletion-info">
        <h2 data-lang="en">Data that will be deleted</h2>
        <h2 data-lang="zh">将会删除的数据</h2>
        <div data-lang="en">
            <p>After we confirm your request, {{ $app_name }} will delete or anonymize:</p>
            <ol>
                <li>Account credentials and profile: login account, email, phone number, nickname, avatar, and third-party login bindings (Google / Apple / Facebook, etc.).</li>
                <li>Device identifiers stored with the account, such as device UUID and device tokens.</li>
                <li>User-generated personal content bound to the account, such as profile archives and feedback contact information.</li>
            </ol>
            <p>Deletion of personal data is completed within 7 days after the request is matched. The account cannot be used to sign in afterwards.</p>
        </div>
        <div data-lang="zh">
            <p>在确认申请后，{{ $app_name }} 将删除或匿名化以下数据：</p>
            <ol>
                <li>账号凭证与资料：登录账号、邮箱、手机号、昵称、头像，以及第三方登录绑定（Google / Apple / Facebook 等）。</li>
                <li>与账号关联的设备标识，如设备 UUID、设备推送 token。</li>
                <li>绑定在账号上的用户个人内容，如个人档案、反馈联系方式。</li>
            </ol>
            <p>匹配到账号后，个人数据将在 7 天内删除。删除完成后该账号无法再登录。</p>
        </div>
    </div>

    <div class="card">
        <h2 data-lang="en">Data that may be retained</h2>
        <h2 data-lang="zh">可能保留的数据</h2>
        <div data-lang="en">
            <ol>
                <li><strong>Payment and membership records:</strong> anonymized transaction / subscription / refund records may be kept for up to 3 years, as required by tax, accounting, and app-store payment rules.</li>
                <li><strong>Security and fraud logs:</strong> limited access logs may be kept for up to 90 days to prevent abuse.</li>
                <li><strong>Legal holds:</strong> data we are required by law to keep will be retained only for the legally required period, then deleted or anonymized.</li>
            </ol>
            <p>Retained records are disconnected from your identity (account, email, phone, and name are removed or replaced with an anonymous identifier).</p>
        </div>
        <div data-lang="zh">
            <ol>
                <li><strong>支付与会员记录：</strong>匿名化后的交易 / 订阅 / 退款记录可能保留最多 3 年，用于税务、会计及应用商店支付合规。</li>
                <li><strong>安全与风控日志：</strong>有限的访问日志最多保留 90 天，用于防止滥用。</li>
                <li><strong>法律要求：</strong>依法必须保留的数据仅在法定期限内保留，到期后删除或匿名化。</li>
            </ol>
            <p>保留的记录会与你的身份断开关联（账号、邮箱、手机号、姓名会被删除或替换为匿名标识）。</p>
        </div>
    </div>
</div>
<script>
    (function () {
        var buttons = document.querySelectorAll('[data-set-lang]');
        function setLang(lang) {
            document.querySelectorAll('[data-lang]').forEach(function (el) {
                el.classList.toggle('show', el.getAttribute('data-lang') === lang);
            });
            buttons.forEach(function (btn) {
                btn.classList.toggle('active', btn.getAttribute('data-set-lang') === lang);
            });
            document.documentElement.lang = lang === 'zh' ? 'zh-CN' : 'en';
        }
        var initial = ((navigator.language || '').toLowerCase().indexOf('zh') === 0) ? 'zh' : 'en';
        setLang(initial);
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setLang(btn.getAttribute('data-set-lang'));
            });
        });
    })();
</script>
</body>
</html>
