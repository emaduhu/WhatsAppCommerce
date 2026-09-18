<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'WhatsApp Commerce for SMEs') }}</title>
    <style>
        :root {
            --bg: #f6f7f3;
            --surface: #ffffff;
            --surface-alt: #f1f6f3;
            --ink: #16221c;
            --muted: #5f6f67;
            --line: #dce6df;
            --brand: #128c7e;
            --brand-dark: #075e54;
            --blue: #255f99;
            --amber: #996b1d;
            --danger: #b42318;
            --ok-bg: #edf8f2;
            --warn-bg: #fff7e7;
            --danger-bg: #fff1f0;
            --shadow: 0 18px 48px rgba(21, 35, 28, .08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            letter-spacing: 0;
        }

        button, input, select, textarea { font: inherit; }
        button { cursor: pointer; }
        h1, h2, h3, p { margin: 0; }
        code { color: #26362d; overflow-wrap: anywhere; }

        .shell {
            width: min(1240px, 100%);
            margin: 0 auto;
            padding: 22px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 4px 0 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 8px;
            background: var(--brand-dark);
            color: white;
            box-shadow: var(--shadow);
        }

        .mark svg { width: 24px; height: 24px; }
        h1 { font-size: clamp(1.35rem, 2vw, 1.9rem); line-height: 1.12; }
        .subtle { color: var(--muted); }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            padding: 6px 11px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: rgba(255, 255, 255, .78);
            color: var(--muted);
            white-space: nowrap;
            font-size: .88rem;
            font-weight: 700;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--amber);
        }

        .dot.ready { background: var(--brand); }
        .dot.error { background: var(--danger); }

        .workspace {
            display: grid;
            grid-template-columns: minmax(280px, .78fr) minmax(0, 1.55fr);
            gap: 18px;
            align-items: start;
        }

        .stack { display: grid; gap: 14px; }

        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 61px;
            padding: 17px 18px;
            border-bottom: 1px solid var(--line);
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 800;
        }

        .panel-body { padding: 18px; }

        .tabs {
            display: flex;
            gap: 6px;
            padding: 5px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface-alt);
        }

        .tab {
            flex: 1;
            border: 0;
            min-height: 36px;
            border-radius: 6px;
            background: transparent;
            color: var(--muted);
            font-weight: 800;
        }

        .tab.active {
            background: white;
            color: var(--ink);
            box-shadow: 0 8px 18px rgba(21, 35, 28, .08);
        }

        label {
            display: grid;
            gap: 7px;
            color: var(--muted);
            font-size: .86rem;
            font-weight: 650;
        }

        input, select, textarea {
            width: 100%;
            min-height: 42px;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: #fbfdfb;
            color: var(--ink);
            padding: 10px 11px;
            outline: none;
        }

        textarea {
            min-height: 76px;
            resize: vertical;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(18, 140, 126, .13);
        }

        .split {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            border: 0;
            border-radius: 7px;
            background: var(--brand);
            color: white;
            padding: 0 14px;
            font-weight: 800;
        }

        .button:disabled {
            cursor: wait;
            opacity: .7;
        }

        .button.secondary {
            background: #e9f2ed;
            color: var(--brand-dark);
            border: 1px solid #cfe0d7;
        }

        .button.danger {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid #ffd6d2;
        }

        .notice {
            border-radius: 8px;
            padding: 12px;
            background: var(--ok-bg);
            color: var(--brand-dark);
            border: 1px solid #cfeada;
            line-height: 1.42;
        }

        .notice.warn {
            background: var(--warn-bg);
            color: var(--amber);
            border-color: #f4dea8;
        }

        .notice.error {
            background: var(--danger-bg);
            color: var(--danger);
            border-color: #ffd6d2;
        }

        .hidden { display: none !important; }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .metric {
            min-height: 92px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
            background: #fbfdfb;
        }

        .metric span {
            display: block;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 700;
        }

        .metric strong {
            display: block;
            margin-top: 8px;
            font-size: clamp(1.22rem, 3vw, 1.85rem);
            line-height: 1;
        }

        .connection-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .status-tile {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px;
            background: #fbfdfb;
            min-height: 76px;
        }

        .status-tile span {
            display: block;
            color: var(--muted);
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-tile strong {
            display: block;
            margin-top: 7px;
            font-size: .98rem;
        }

        .checkline {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: .86rem;
            font-weight: 650;
        }

        .checkline input {
            width: auto;
            min-height: auto;
        }

        .account-list, .products, .compact-list, .endpoint-list {
            display: grid;
            gap: 8px;
        }

        .account-row, .product-row, .compact-row, .endpoint {
            display: grid;
            gap: 10px;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 11px 12px;
            background: #fbfdfb;
        }

        .account-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .product-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .compact-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .endpoint {
            grid-template-columns: 72px minmax(0, 1fr);
        }

        .row-title {
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .row-meta {
            margin-top: 3px;
            color: var(--muted);
            font-size: .84rem;
            overflow-wrap: anywhere;
        }

        .badge, .verb {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 26px;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: .74rem;
            font-weight: 850;
            color: var(--brand-dark);
            background: #dff2e9;
            white-space: nowrap;
        }

        .badge.warn { color: var(--amber); background: var(--warn-bg); }
        .badge.blue { color: var(--blue); background: #eef5ff; }
        .price { color: var(--brand-dark); font-weight: 850; white-space: nowrap; }

        details.session-tools {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fbfdfb;
        }

        details.session-tools summary {
            cursor: pointer;
            padding: 12px;
            color: var(--muted);
            font-weight: 800;
        }

        details.session-tools .session-body {
            padding: 0 12px 12px;
        }

        @media (max-width: 980px) {
            .workspace, .metric-grid, .connection-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 640px) {
            .shell { padding: 16px; }
            .split, .account-row, .product-row, .compact-row, .endpoint {
                grid-template-columns: 1fr;
            }

            .pill { white-space: normal; }
        }
    </style>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div class="brand">
            <div class="mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" role="img">
                    <path d="M6.9 18.6 4 20l1.1-3.1A8.3 8.3 0 1 1 8 19.2l-1.1-.6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M9.1 8.4c.2-.4.4-.5.8-.5h.6c.2 0 .4.1.5.4l.7 1.6c.1.3.1.5-.1.7l-.5.6c.7 1.2 1.6 2.1 2.9 2.8l.6-.6c.2-.2.4-.2.7-.1l1.6.7c.3.1.4.3.4.6v.5c0 .4-.1.7-.5.9-.5.3-1.1.4-1.8.3-3.2-.5-5.8-3-6.2-6.2-.1-.6 0-1.2.3-1.7Z" fill="currentColor"/>
                </svg>
            </div>
            <div>
                <h1>{{ config('app.name', 'WhatsApp Commerce for SMEs') }}</h1>
                <p class="subtle">Merchant-owned WhatsApp accounts, shared commerce automation.</p>
            </div>
        </div>
        <div class="pill"><span class="dot" id="runtimeDot"></span><span id="runtimeStatus">Checking API</span></div>
    </header>

    <section class="workspace">
        <aside class="stack">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Access</h2>
                    <div class="tabs" role="tablist" aria-label="Access mode">
                        <button class="tab active" type="button" data-mode="login">Login</button>
                        <button class="tab" type="button" data-mode="register">Register</button>
                    </div>
                </div>
                <div class="panel-body stack">
                    <form id="loginForm" class="stack">
                        <label>Email<input name="email" type="email" autocomplete="email" required></label>
                        <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
                        <button class="button" type="submit">Login</button>
                    </form>

                    <form id="registerForm" class="stack hidden">
                        <label>Owner name<input name="name" autocomplete="name" required></label>
                        <label>Business name<input name="business_name" required></label>
                        <label>Email<input name="email" type="email" autocomplete="email" required></label>
                        <div class="split">
                            <label>Password<input name="password" type="password" autocomplete="new-password" required></label>
                            <label>Confirm password<input name="password_confirmation" type="password" autocomplete="new-password" required></label>
                        </div>
                        <button class="button" type="submit">Create merchant</button>
                    </form>

                    <div id="message" class="notice hidden"></div>
                    <div class="actions">
                        <button class="button secondary" id="refreshDashboard" type="button">Refresh</button>
                        <button class="button danger" id="clearSession" type="button">Logout</button>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2 class="panel-title">Merchant</h2></div>
                <div class="panel-body stack">
                    <label>Active merchant<select id="merchantSelect"></select></label>
                    <div class="notice warn hidden" id="merchantNotice"></div>
                    <details class="session-tools">
                        <summary>Session recovery</summary>
                        <div class="session-body stack">
                            <label>Bearer token<textarea id="tokenInput" spellcheck="false"></textarea></label>
                            <label>Merchant ID<input id="merchantInput" inputmode="numeric"></label>
                            <button class="button secondary" id="saveSession" type="button">Save session</button>
                        </div>
                    </details>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2 class="panel-title">Production URLs</h2></div>
                <div class="panel-body endpoint-list">
                    <div class="endpoint"><span class="verb">APP</span><code>{{ url('/') }}</code></div>
                    <div class="endpoint"><span class="verb">API</span><code>{{ url('/api') }}</code></div>
                    <div class="endpoint"><span class="verb">WA</span><code>{{ url('/api/webhooks/whatsapp') }}</code></div>
                </div>
            </section>
        </aside>

        <div class="stack">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">WhatsApp Connection</h2>
                        <p class="subtle" id="connectionSubtitle">No merchant selected</p>
                    </div>
                    <span class="badge warn" id="connectionBadge">Not connected</span>
                </div>
                <div class="panel-body stack">
                    <div class="connection-grid">
                        <div class="status-tile"><span>Signup</span><strong id="signupState">Waiting</strong></div>
                        <div class="status-tile"><span>Webhook</span><strong id="webhookState">Checking</strong></div>
                        <div class="status-tile"><span>Accounts</span><strong id="accountState">0 connected</strong></div>
                    </div>

                    <div class="split">
                        <label>WhatsApp number<input id="whatsappNumber" inputmode="tel" placeholder="+255752160150"></label>
                        <label>Registration PIN<input id="registrationPin" inputmode="numeric" maxlength="6" placeholder="Auto-generate"></label>
                    </div>
                    <label class="checkline"><input id="keepBusinessApp" type="checkbox" checked> Keep the WhatsApp Business app active on this number</label>
                    <label class="checkline"><input id="registerPhoneNumber" type="checkbox"> Cloud API only: move this number out of the app</label>
                    <div class="notice warn">For SMEs, keep the WhatsApp Business app active. Use Cloud API only when the merchant wants the number controlled only by this platform.</div>
                    <div class="actions">
                        <button class="button" id="startWhatsAppSetup" type="button">Connect with Meta</button>
                        <button class="button secondary" id="checkWhatsAppSetup" type="button">Check setup</button>
                    </div>
                    <div id="whatsAppSetupStatus" class="notice hidden"></div>
                    <div id="accountList" class="account-list"></div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Dashboard</h2>
                    <span class="subtle" id="merchantLabel">No merchant selected</span>
                </div>
                <div class="panel-body stack">
                    <div class="metric-grid">
                        <div class="metric"><span>Sales today</span><strong id="salesToday">0</strong></div>
                        <div class="metric"><span>Orders today</span><strong id="ordersToday">0</strong></div>
                        <div class="metric"><span>Messages today</span><strong id="messagesToday">0</strong></div>
                        <div class="metric"><span>Open chats</span><strong id="openChats">0</strong></div>
                    </div>
                    <div class="split">
                        <div>
                            <h3 class="panel-title">Low Stock</h3>
                            <div id="lowStockList" class="compact-list" style="margin-top: 10px;"></div>
                        </div>
                        <div>
                            <h3 class="panel-title">Recent Orders</h3>
                            <div id="recentOrdersList" class="compact-list" style="margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2 class="panel-title">Products</h2></div>
                <div class="panel-body stack">
                    <form id="productForm" class="stack">
                        <div class="split">
                            <label>Name<input name="name" required></label>
                            <label>SKU<input name="sku"></label>
                        </div>
                        <div class="split">
                            <label>Price<input name="price" type="number" step="0.01" min="0" required></label>
                            <label>Stock<input name="stock_quantity" type="number" min="0" value="0" required></label>
                        </div>
                        <label>Description<textarea name="description"></textarea></label>
                        <button class="button" type="submit">Add product</button>
                    </form>
                    <div id="products" class="products"></div>
                </div>
            </section>
        </div>
    </section>
</main>

<script>
    const api = `${window.location.origin}/api`;
    const state = {
        token: localStorage.getItem('wc_token') || '',
        merchant: localStorage.getItem('wc_merchant') || '',
        merchants: []
    };
    const whatsAppSignup = {waba_id: null, phone_number_id: null, verified_name: null, session_info: null};
    const $ = (selector) => document.querySelector(selector);

    function notice(selector, text, type = 'ok') {
        const element = $(selector);
        element.textContent = text;
        element.classList.remove('hidden', 'error', 'warn');
        if (type === 'error') element.classList.add('error');
        if (type === 'warn') element.classList.add('warn');
    }

    function hideNotice(selector) {
        const element = $(selector);
        element.textContent = '';
        element.classList.add('hidden');
        element.classList.remove('error', 'warn');
    }

    function setBusy(button, busy, busyText = 'Working...') {
        if (!button.dataset.label) button.dataset.label = button.textContent;
        button.disabled = busy;
        button.textContent = busy ? busyText : button.dataset.label;
    }

    function money(value) {
        return Number(value || 0).toLocaleString();
    }

    function resetWhatsAppSignup() {
        whatsAppSignup.waba_id = null;
        whatsAppSignup.phone_number_id = null;
        whatsAppSignup.verified_name = null;
        whatsAppSignup.session_info = null;
    }

    async function request(path, options = {}) {
        const headers = {'Accept': 'application/json', ...(options.headers || {})};
        if (options.body) headers['Content-Type'] = 'application/json';
        if (state.token) headers['Authorization'] = `Bearer ${state.token}`;

        const response = await fetch(`${api}${path}`, {...options, headers});
        const text = await response.text();
        let payload = null;
        try {
            payload = text ? JSON.parse(text) : null;
        } catch (_) {
            payload = {message: text};
        }
        if (!response.ok) {
            throw new Error(payload?.message || `Request failed with ${response.status}`);
        }
        return payload;
    }

    function persistSession(token, merchantId) {
        state.token = token || state.token;
        state.merchant = merchantId ? String(merchantId) : state.merchant;
        if (state.token) localStorage.setItem('wc_token', state.token);
        if (state.merchant) localStorage.setItem('wc_merchant', state.merchant);
        syncSessionFields();
    }

    function clearSession() {
        localStorage.removeItem('wc_token');
        localStorage.removeItem('wc_merchant');
        state.token = '';
        state.merchant = '';
        state.merchants = [];
        syncSessionFields();
        renderMerchants();
        renderAccounts([]);
        renderDashboard({});
        renderProducts([]);
    }

    function syncSessionFields() {
        $('#tokenInput').value = state.token;
        $('#merchantInput').value = state.merchant;
        const merchant = state.merchants.find((item) => String(item.id) === String(state.merchant));
        const label = merchant ? `${merchant.name} (#${merchant.id})` : (state.merchant ? `Merchant #${state.merchant}` : 'No merchant selected');
        $('#merchantLabel').textContent = label;
        $('#connectionSubtitle').textContent = label;
    }

    function renderMerchants() {
        const select = $('#merchantSelect');
        select.innerHTML = '';
        if (!state.merchants.length) {
            const option = document.createElement('option');
            option.value = state.merchant || '';
            option.textContent = state.merchant ? `Merchant #${state.merchant}` : 'Login to load merchants';
            select.appendChild(option);
            $('#merchantNotice').classList.toggle('hidden', Boolean(state.merchant));
            if (!state.merchant) notice('#merchantNotice', 'Create or login to a merchant account.', 'warn');
            return;
        }

        for (const merchant of state.merchants) {
            const option = document.createElement('option');
            option.value = merchant.id;
            option.textContent = `${merchant.name} (#${merchant.id})`;
            select.appendChild(option);
        }
        if (!state.merchant || !state.merchants.some((item) => String(item.id) === String(state.merchant))) {
            state.merchant = String(state.merchants[0].id);
            localStorage.setItem('wc_merchant', state.merchant);
        }
        select.value = state.merchant;
        hideNotice('#merchantNotice');
        syncSessionFields();
    }

    function setConfigState(config) {
        const ready = Boolean(config?.enabled);
        $('#signupState').textContent = ready ? 'Ready' : 'Needs Meta config';
        $('#webhookState').textContent = ready ? 'Configured' : 'Waiting';
        $('#connectionBadge').textContent = ready ? 'Ready to connect' : 'Setup incomplete';
        $('#connectionBadge').classList.toggle('warn', !ready);
        $('#connectionBadge').classList.toggle('blue', ready);
        return ready;
    }

    function renderAccounts(accounts) {
        const list = $('#accountList');
        list.innerHTML = '';
        $('#accountState').textContent = `${accounts.length} connected`;

        if (!accounts.length) {
            const empty = document.createElement('div');
            empty.className = 'notice warn';
            empty.textContent = 'No WhatsApp account connected for this merchant.';
            list.appendChild(empty);
            $('#connectionBadge').textContent = 'Not connected';
            $('#connectionBadge').classList.add('warn');
            $('#connectionBadge').classList.remove('blue');
            return;
        }

        $('#connectionBadge').textContent = 'Connected';
        $('#connectionBadge').classList.remove('warn');
        $('#connectionBadge').classList.add('blue');

        for (const account of accounts) {
            const row = document.createElement('div');
            row.className = 'account-row';

            const details = document.createElement('div');
            const title = document.createElement('div');
            title.className = 'row-title';
            title.textContent = account.display_phone_number || account.phone_number_id || 'WhatsApp account';
            const meta = document.createElement('div');
            meta.className = 'row-meta';
            const registrationText = account.registration_ok === false ? ' | Cloud registration pending' : '';
            meta.textContent = `${account.verified_name || 'Unverified name'} | WABA ${account.waba_id} | Phone ID ${account.phone_number_id}${registrationText}`;
            details.append(title, meta);

            if (account.registration_ok === false) {
                const warning = document.createElement('div');
                warning.className = 'row-meta';
                const existingAppNumber = String(account.registration_error || '').toLowerCase().includes('existing whatsapp account');
                warning.textContent = existingAppNumber
                    ? 'This number is already on the WhatsApp Business app. Keep it there and click Connect with Meta again with "Keep the WhatsApp Business app active" checked to enable coexistence.'
                    : (account.registration_error || 'Disconnect this number from the WhatsApp mobile app, wait up to 3 minutes, then retry Cloud registration.');
                details.appendChild(warning);
            }

            const actions = document.createElement('div');
            actions.className = 'actions';

            const badge = document.createElement('span');
            badge.className = account.registration_ok === false ? 'badge warn' : 'badge';
            badge.textContent = account.connection_mode === 'business_app_coexistence' ? 'COEXISTENCE' : (account.registration_ok === false ? 'REGISTRATION PENDING' : (account.onboarding_status || account.status || 'ACTIVE'));
            actions.appendChild(badge);

            if (account.registration_ok === false && !String(account.registration_error || '').toLowerCase().includes('existing whatsapp account')) {
                const retry = document.createElement('button');
                retry.className = 'button secondary';
                retry.type = 'button';
                retry.textContent = 'Retry Cloud registration';
                retry.addEventListener('click', () => retryCloudRegistration(account.id, retry));
                actions.appendChild(retry);
            }

            row.append(details, actions);
            list.appendChild(row);
        }
    }

    async function retryCloudRegistration(accountId, button) {
        if (!state.token || !state.merchant) {
            notice('#whatsAppSetupStatus', 'Login first, then choose a merchant.', 'error');
            return;
        }

        try {
            setBusy(button, true, 'Registering...');
            const pin = $('#registrationPin').value.trim();
            const payload = pin ? {registration_pin: pin} : {};
            const result = await request(`/merchants/${state.merchant}/whatsapp/accounts/${accountId}/register`, {method: 'POST', body: JSON.stringify(payload)});
            notice('#whatsAppSetupStatus', `Cloud API registration completed for ${result.account.display_phone_number || result.account.phone_number_id}.`);
            await Promise.all([loadAccounts(), loadDashboard()]);
        } catch (error) {
            notice('#whatsAppSetupStatus', error.message, 'error');
            await loadAccounts();
        } finally {
            setBusy(button, false);
        }
    }

    function renderCompactList(selector, rows, emptyText, mapRow) {
        const list = $(selector);
        list.innerHTML = '';
        if (!rows || !rows.length) {
            const row = document.createElement('div');
            row.className = 'compact-row';
            row.textContent = emptyText;
            list.appendChild(row);
            return;
        }
        for (const item of rows) {
            const row = document.createElement('div');
            row.className = 'compact-row';
            const mapped = mapRow(item);
            const detail = document.createElement('div');
            const title = document.createElement('div');
            title.className = 'row-title';
            title.textContent = mapped.title;
            const meta = document.createElement('div');
            meta.className = 'row-meta';
            meta.textContent = mapped.meta;
            detail.append(title, meta);
            const side = document.createElement('span');
            side.className = mapped.sideClass || 'badge';
            side.textContent = mapped.side;
            row.append(detail, side);
            list.appendChild(row);
        }
    }

    function renderDashboard(data) {
        $('#salesToday').textContent = money(data.today?.sales);
        $('#ordersToday').textContent = data.today?.orders || 0;
        $('#messagesToday').textContent = data.today?.messages || 0;
        $('#openChats').textContent = data.today?.open_conversations || 0;

        renderCompactList('#lowStockList', data.low_stock || [], 'No low-stock products.', (item) => ({
            title: item.name,
            meta: item.sku || 'No SKU',
            side: item.stock_quantity ?? 0,
            sideClass: 'badge warn'
        }));

        renderCompactList('#recentOrdersList', data.recent_orders || [], 'No recent orders.', (item) => ({
            title: item.number || item.order_number || `Order #${item.id}`,
            meta: item.customer?.phone || item.status || 'New order',
            side: money(item.total),
            sideClass: 'price'
        }));
    }

    function renderProducts(products) {
        const list = $('#products');
        list.innerHTML = '';
        if (!products.length) {
            const empty = document.createElement('div');
            empty.className = 'product-row';
            empty.textContent = 'No products yet.';
            list.appendChild(empty);
            return;
        }

        for (const product of products) {
            const row = document.createElement('div');
            row.className = 'product-row';
            const detail = document.createElement('div');
            const title = document.createElement('div');
            title.className = 'row-title';
            title.textContent = product.name;
            const meta = document.createElement('div');
            meta.className = 'row-meta';
            meta.textContent = `${product.sku || 'No SKU'} | Stock ${product.stock_quantity ?? 0}`;
            detail.append(title, meta);
            const price = document.createElement('span');
            price.className = 'price';
            price.textContent = money(product.price);
            row.append(detail, price);
            list.appendChild(row);
        }
    }

    async function loadMe() {
        if (!state.token) return;
        const data = await request('/auth/me');
        state.merchants = data.merchants || [];
        renderMerchants();
    }

    async function loadDashboard() {
        if (!state.token || !state.merchant) {
            notice('#message', 'Login or choose a merchant.', 'warn');
            return;
        }
        const data = await request(`/merchants/${state.merchant}/dashboard`);
        renderDashboard(data);
    }

    async function loadProducts() {
        if (!state.token || !state.merchant) {
            renderProducts([]);
            return;
        }
        const data = await request(`/merchants/${state.merchant}/products`);
        renderProducts(data.data || []);
    }

    async function loadAccounts() {
        if (!state.token || !state.merchant) {
            renderAccounts([]);
            return;
        }
        const accounts = await request(`/merchants/${state.merchant}/whatsapp/accounts`);
        renderAccounts(accounts || []);
    }

    async function refreshWorkspace() {
        if (!state.token || !state.merchant) return;
        await Promise.all([loadDashboard(), loadProducts(), checkWhatsAppConfig(false)]);
        await loadAccounts();
    }

    window.addEventListener('message', (event) => {
        if (!String(event.origin).includes('facebook.com')) return;
        let data = event.data;
        if (typeof data === 'string') {
            try { data = JSON.parse(data); } catch (_) { return; }
        }
        if (data?.type !== 'WA_EMBEDDED_SIGNUP') return;
        whatsAppSignup.session_info = data;
        if (data.event === 'FINISH' || data.event === 'FINISH_ONLY_WABA' || data.event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') {
            whatsAppSignup.waba_id = data.data?.waba_id || data.data?.wabaID || whatsAppSignup.waba_id;
            whatsAppSignup.phone_number_id = data.data?.phone_number_id || data.data?.phoneNumberID || whatsAppSignup.phone_number_id;
            whatsAppSignup.verified_name = data.data?.business_name || data.data?.verified_name || whatsAppSignup.verified_name;
        }
    });

    function loadFacebookSdk(appId, version) {
        return new Promise((resolve, reject) => {
            if (window.FB) {
                FB.init({appId, xfbml: false, version});
                resolve(FB);
                return;
            }

            window.fbAsyncInit = function () {
                FB.init({appId, xfbml: false, version});
                resolve(FB);
            };

            const existing = document.getElementById('facebook-jssdk');
            if (existing) {
                existing.addEventListener('error', () => reject(new Error('Could not load Meta SDK.')), {once: true});
                return;
            }

            const script = document.createElement('script');
            script.id = 'facebook-jssdk';
            script.src = 'https://connect.facebook.net/en_US/sdk.js';
            script.onerror = () => reject(new Error('Could not load Meta SDK.'));
            document.body.appendChild(script);
        });
    }

    async function checkWhatsAppConfig(showResult = true) {
        if (!state.token || !state.merchant) {
            if (showResult) notice('#whatsAppSetupStatus', 'Login first, then choose a merchant.', 'error');
            return null;
        }

        const config = await request(`/merchants/${state.merchant}/whatsapp/onboarding/config`);
        const ready = setConfigState(config);
        if (showResult) {
            notice(
                '#whatsAppSetupStatus',
                ready ? `Meta setup is ready. Webhook: ${config.callback_url}` : (config.message || 'Meta Embedded Signup is not configured.'),
                ready ? 'ok' : 'error'
            );
        }
        return config;
    }

    async function startWhatsAppSetup() {
        const button = $('#startWhatsAppSetup');
        if (!state.token || !state.merchant) {
            notice('#whatsAppSetupStatus', 'Login first, then choose a merchant.', 'error');
            return;
        }

        const number = $('#whatsappNumber').value.trim();
        if (!number) {
            notice('#whatsAppSetupStatus', 'Enter the merchant WhatsApp number first.', 'error');
            return;
        }

        try {
            setBusy(button, true, 'Opening Meta...');
            resetWhatsAppSignup();
            const config = await checkWhatsAppConfig(false);
            if (!config || !config.enabled) {
                notice('#whatsAppSetupStatus', config?.message || 'Meta Embedded Signup is not configured.', 'error');
                setBusy(button, false);
                return;
            }

            const FB = await loadFacebookSdk(config.app_id, config.graph_version);
            notice('#whatsAppSetupStatus', 'Meta setup opened. Complete the business authorization in the popup.', 'warn');
            const keepBusinessApp = $('#keepBusinessApp').checked;
            const loginOptions = keepBusinessApp ? {
                config_id: config.config_id,
                response_type: 'code',
                override_default_response_type: true,
                extras: {
                    version: 'v4',
                    featureType: 'whatsapp_business_app_onboarding',
                    features: [{name: 'app_only_install'}],
                    sessionInfoVersion: '3'
                }
            } : {
                config_id: config.config_id,
                scope: 'business_management,whatsapp_business_management,whatsapp_business_messaging',
                auth_type: 'rerequest',
                return_scopes: true,
                response_type: 'code',
                override_default_response_type: true,
                extras: {
                    setup: {phone_number: number},
                    featureType: '',
                    sessionInfoVersion: '3'
                }
            };

            FB.login((response) => {
                (async () => {
                    try {
                        const code = response?.authResponse?.code;
                        const loginAccessToken = response?.authResponse?.accessToken;
                        if (!code && !loginAccessToken) {
                            const status = response?.status ? ` Status: ${response.status}.` : '';
                            notice('#whatsAppSetupStatus', `Meta setup finished without an authorization code or access token.${status} Check the Meta Login for Business configuration and try again.`, 'error');
                            return;
                        }

                        if (!code && loginAccessToken) {
                            notice('#whatsAppSetupStatus', 'Meta returned a connected login session instead of an authorization code. Finishing setup with the returned access token for this test...', 'warn');
                        }

                        const payload = {
                            connection_mode: $('#keepBusinessApp').checked ? 'business_app_coexistence' : 'cloud_api_only',
                            whatsapp_number: number,
                            code: code || null,
                            access_token: code ? null : loginAccessToken,
                            access_token_expires_in: response?.authResponse?.expiresIn || null,
                            granted_scopes: response?.authResponse?.grantedScopes || null,
                            denied_scopes: response?.authResponse?.deniedScopes || null,
                            waba_id: whatsAppSignup.waba_id,
                            phone_number_id: whatsAppSignup.phone_number_id,
                            verified_name: whatsAppSignup.verified_name,
                            registration_pin: $('#registrationPin').value.trim() || null,
                            register_phone_number: !$('#keepBusinessApp').checked && $('#registerPhoneNumber').checked,
                            session_info: whatsAppSignup.session_info
                        };
                        const result = await request(`/merchants/${state.merchant}/whatsapp/onboarding/complete`, {method: 'POST', body: JSON.stringify(payload)});
                        notice('#whatsAppSetupStatus', `WhatsApp connected: ${result.account.display_phone_number || number}.`);
                        await Promise.all([loadAccounts(), loadDashboard()]);
                    } catch (error) {
                        notice('#whatsAppSetupStatus', error.message, 'error');
                    } finally {
                        setBusy(button, false);
                    }
                })();
            }, loginOptions);
        } catch (error) {
            notice('#whatsAppSetupStatus', error.message, 'error');
            setBusy(button, false);
        }
    }

    document.querySelectorAll('.tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach((button) => button.classList.remove('active'));
            tab.classList.add('active');
            $('#loginForm').classList.toggle('hidden', tab.dataset.mode !== 'login');
            $('#registerForm').classList.toggle('hidden', tab.dataset.mode !== 'register');
        });
    });

    $('#loginForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = event.currentTarget.querySelector('button[type="submit"]');
        const body = Object.fromEntries(new FormData(event.currentTarget));
        try {
            setBusy(button, true, 'Logging in...');
            const data = await request('/auth/login', {method: 'POST', body: JSON.stringify(body)});
            state.merchants = data.merchants || [];
            persistSession(data.token, state.merchant || data.merchants?.[0]?.id);
            renderMerchants();
            notice('#message', 'Logged in.');
            await refreshWorkspace();
        } catch (error) {
            notice('#message', error.message, 'error');
        } finally {
            setBusy(button, false);
        }
    });

    $('#registerForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = event.currentTarget.querySelector('button[type="submit"]');
        const body = Object.fromEntries(new FormData(event.currentTarget));
        try {
            setBusy(button, true, 'Creating...');
            const data = await request('/auth/register', {method: 'POST', body: JSON.stringify(body)});
            state.merchants = data.merchant ? [data.merchant] : [];
            persistSession(data.token, data.merchant?.id);
            renderMerchants();
            notice('#message', 'Merchant created.');
            await refreshWorkspace();
        } catch (error) {
            notice('#message', error.message, 'error');
        } finally {
            setBusy(button, false);
        }
    });

    $('#productForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!state.token || !state.merchant) {
            notice('#message', 'Login first, then choose a merchant.', 'error');
            return;
        }

        const button = event.currentTarget.querySelector('button[type="submit"]');
        const form = Object.fromEntries(new FormData(event.currentTarget));
        const payload = {
            ...form,
            type: 'PRODUCT',
            price: Number(form.price || 0),
            stock_quantity: Number(form.stock_quantity || 0),
            track_stock: true,
            is_active: true
        };

        try {
            setBusy(button, true, 'Saving...');
            await request(`/merchants/${state.merchant}/products`, {method: 'POST', body: JSON.stringify(payload)});
            event.currentTarget.reset();
            notice('#message', 'Product saved.');
            await Promise.all([loadProducts(), loadDashboard()]);
        } catch (error) {
            notice('#message', error.message, 'error');
        } finally {
            setBusy(button, false);
        }
    });

    $('#merchantSelect').addEventListener('change', async (event) => {
        state.merchant = event.currentTarget.value;
        localStorage.setItem('wc_merchant', state.merchant);
        syncSessionFields();
        try {
            await refreshWorkspace();
        } catch (error) {
            notice('#message', error.message, 'error');
        }
    });

    $('#refreshDashboard').addEventListener('click', async () => {
        try {
            await refreshWorkspace();
            notice('#message', 'Dashboard refreshed.');
        } catch (error) {
            notice('#message', error.message, 'error');
        }
    });

    $('#saveSession').addEventListener('click', async () => {
        persistSession($('#tokenInput').value.trim(), $('#merchantInput').value.trim());
        try {
            await loadMe();
            await refreshWorkspace();
            notice('#message', 'Session saved.');
        } catch (error) {
            notice('#message', error.message, 'error');
        }
    });

    $('#clearSession').addEventListener('click', () => {
        clearSession();
        notice('#message', 'Logged out.');
    });

    $('#startWhatsAppSetup').addEventListener('click', startWhatsAppSetup);
    $('#keepBusinessApp').addEventListener('change', () => {
        const keepApp = $('#keepBusinessApp').checked;
        $('#registerPhoneNumber').checked = false;
        $('#registerPhoneNumber').disabled = keepApp;
        $('#registrationPin').disabled = keepApp;
    });
    $('#keepBusinessApp').dispatchEvent(new Event('change'));
    $('#checkWhatsAppSetup').addEventListener('click', async () => {
        try {
            await checkWhatsAppConfig(true);
            await loadAccounts();
        } catch (error) {
            notice('#whatsAppSetupStatus', error.message, 'error');
        }
    });

    fetch('/up')
        .then((response) => {
            $('#runtimeStatus').textContent = response.ok ? 'API online' : 'Health check failed';
            $('#runtimeDot').classList.toggle('ready', response.ok);
            $('#runtimeDot').classList.toggle('error', !response.ok);
        })
        .catch(() => {
            $('#runtimeStatus').textContent = 'Health check failed';
            $('#runtimeDot').classList.add('error');
        });

    syncSessionFields();
    renderMerchants();
    renderProducts([]);
    if (state.token) {
        loadMe()
            .then(refreshWorkspace)
            .catch(() => {
                clearSession();
                notice('#message', 'Saved session expired. Login again.', 'warn');
            });
    }
</script>
</body>
</html>
