# Frontend Food Delivery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a cartoon-styled, role-aware single-page frontend in vanilla HTML/CSS/JS that connects to the existing PHP REST API.

**Architecture:** SPA served at `frontend/index.html` — hash-based routing (`#restaurants`, `#login`, `#admin`, etc.), one CSS file for the cartoon theme, `api.js` for all fetch calls, `auth.js` for token storage, `app.js` for all view rendering and event handling. One backend patch needed: drivers can't currently list orders.

**Tech Stack:** HTML5, CSS3, vanilla JavaScript (ES2020), Google Fonts (Nunito), no build tools.

---

## File Map

| File | Responsibility |
|------|---------------|
| `frontend/index.html` | App shell — navbar, `#main` container, script tags |
| `frontend/style.css` | Full cartoon theme: colors, cards, buttons, badges, modals |
| `frontend/js/api.js` | All `fetch()` calls to `/api/*`, throws on non-2xx |
| `frontend/js/auth.js` | Read/write JWT + user from localStorage |
| `frontend/js/app.js` | Router, all views, all event handlers |
| `src/Controllers/OrderController.php` | **Patch:** allow driver role on `index()` |
| `src/Models/Order.php` | **Patch:** add `findByDriver()` method |

---

## Task 1: Backend Patch — Driver Orders

Drivers need to list their deliveries. Currently `GET /api/orders` only allows customer and admin.

**Files:**
- Modify: `src/Controllers/OrderController.php`
- Modify: `src/Models/Order.php`

- [ ] **Step 1: Add `findByDriver()` to Order model**

Open `src/Models/Order.php` and add after `findByCustomer()`:

```php
public static function findByDriver(int $driverId): array
{
    $stmt = Database::getInstance()->prepare(
        "SELECT * FROM orders
         WHERE status IN ('preparing','out_for_delivery')
         ORDER BY created_at DESC"
    );
    $stmt->execute();
    return $stmt->fetchAll();
}
```

- [ ] **Step 2: Patch `OrderController::index()` to allow driver role**

Open `src/Controllers/OrderController.php`. Replace the `index` method:

```php
public function index(array $request): void
{
    $user = AuthMiddleware::handle($request, ['customer', 'admin', 'driver']);

    if ($user->role === 'customer') {
        $orders = Order::findByCustomer((int)$user->sub);
    } elseif ($user->role === 'driver') {
        $orders = Order::findByDriver((int)$user->sub);
    } else {
        $orders = Order::findAll();
    }

    Response::json($orders);
}
```

- [ ] **Step 3: Verify with curl (after XAMPP is running)**

```bash
# Login as driver, then:
curl -H "Authorization: Bearer <driver_token>" http://localhost/Szalmaprojekt/api/orders
```
Expected: JSON array of orders with status preparing or out_for_delivery.

---

## Task 2: HTML Shell

**Files:**
- Create: `frontend/index.html`

- [ ] **Step 1: Create `frontend/index.html`**

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🍕 FoodieZone</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="#restaurants" class="navbar-brand">🍕 FoodieZone</a>
        <div class="navbar-links" id="navbar-links"></div>
    </nav>
    <div id="main"></div>

    <script src="js/api.js"></script>
    <script src="js/auth.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Open in browser**

Navigate to `http://localhost/Szalmaprojekt/frontend/` — you should see a blank page with no errors in the console (scripts not loaded yet, that's fine).

---

## Task 3: Stylesheet

**Files:**
- Create: `frontend/style.css`

- [ ] **Step 1: Create `frontend/style.css`**

```css
/* === Variables === */
:root {
    --orange:  #FF6B35;
    --yellow:  #FFD93D;
    --green:   #4ECB71;
    --blue:    #4B9FFF;
    --pink:    #FF6BA8;
    --red:     #FF4444;
    --bg:      #FFF8F0;
    --white:   #FFFFFF;
    --text:    #2C2C2C;
    --muted:   #888888;
    --border:  3px solid #2C2C2C;
    --shadow:  4px 4px 0px #2C2C2C;
    --shadow-lg: 6px 6px 0px #2C2C2C;
    --radius:  18px;
    --radius-sm: 10px;
    --radius-lg: 28px;
}

/* === Reset === */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Nunito', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
}

a { text-decoration: none; color: inherit; }

/* === Navbar === */
.navbar {
    background: var(--orange);
    border-bottom: var(--border);
    padding: 12px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 0 #2C2C2C;
    position: sticky;
    top: 0;
    z-index: 100;
}
.navbar-brand {
    font-size: 1.5rem;
    font-weight: 900;
    color: white;
    text-shadow: 2px 2px 0 #2C2C2C;
    margin-right: auto;
}
.navbar-links { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.navbar-user {
    background: white;
    border: var(--border);
    border-radius: var(--radius-sm);
    padding: 4px 12px;
    font-weight: 700;
    font-size: 0.85rem;
}

/* === Buttons === */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border: var(--border);
    border-radius: var(--radius-sm);
    font-family: 'Nunito', sans-serif;
    font-weight: 800;
    font-size: 0.9rem;
    cursor: pointer;
    box-shadow: var(--shadow);
    transition: transform .1s, box-shadow .1s;
    background: var(--white);
    color: var(--text);
}
.btn:hover  { transform: translate(-2px,-2px); box-shadow: var(--shadow-lg); }
.btn:active { transform: translate(2px,2px);   box-shadow: 2px 2px 0 #2C2C2C; }
.btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

.btn-primary   { background: var(--orange); color: white; }
.btn-secondary { background: var(--yellow); color: var(--text); }
.btn-success   { background: var(--green);  color: white; }
.btn-danger    { background: var(--pink);   color: white; }
.btn-info      { background: var(--blue);   color: white; }
.btn-sm        { padding: 6px 14px; font-size: 0.8rem; }

/* === Cards === */
.card {
    background: var(--white);
    border: var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: transform .15s, box-shadow .15s;
}
.card.clickable:hover { transform: translate(-2px,-2px); box-shadow: var(--shadow-lg); cursor: pointer; }
.card-body   { padding: 20px; }
.card-title  { font-size: 1.1rem; font-weight: 800; margin-bottom: 6px; }
.card-text   { color: var(--muted); font-size: 0.9rem; }

/* === Grids === */
.grid-3 { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
.grid-2 { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }

/* === Page wrapper === */
.page { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }

/* === Page header === */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
}
.page-title    { font-size: 2rem; font-weight: 900; }
.page-subtitle { color: var(--muted); font-size: 0.9rem; margin-top: 2px; }

/* === Forms === */
.form-group   { margin-bottom: 16px; }
.form-label   { display: block; font-weight: 700; font-size: 0.9rem; margin-bottom: 6px; }
.form-control {
    width: 100%;
    padding: 10px 14px;
    border: var(--border);
    border-radius: var(--radius-sm);
    font-family: 'Nunito', sans-serif;
    font-size: 1rem;
    background: var(--white);
    box-shadow: 2px 2px 0 #2C2C2C;
    transition: box-shadow .1s;
}
.form-control:focus { outline: none; box-shadow: 4px 4px 0 var(--orange); }

/* === Badges === */
.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 0.75rem;
    font-weight: 800;
    border: 2px solid #2C2C2C;
    letter-spacing: .5px;
}
.badge-pending          { background: var(--yellow); }
.badge-preparing        { background: var(--orange); color: white; }
.badge-out_for_delivery { background: var(--blue);   color: white; }
.badge-delivered        { background: var(--green);  color: white; }
.badge-cancelled        { background: #ddd; color: #666; }

/* === Alerts === */
.alert {
    padding: 12px 16px;
    border: var(--border);
    border-radius: var(--radius-sm);
    font-weight: 600;
    margin-bottom: 16px;
}
.alert-error   { background: #FFE4E4; border-color: var(--red);   color: var(--red); }
.alert-success { background: #E4FFE9; border-color: var(--green); color: #2A7A3B; }

/* === Auth === */
.auth-wrap  { min-height: calc(100vh - 72px); display: flex; align-items: center; justify-content: center; padding: 24px; }
.auth-card  { background: var(--white); border: var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow); padding: 40px; width: 100%; max-width: 420px; }
.auth-title { font-size: 2rem; font-weight: 900; text-align: center; margin-bottom: 6px; }
.auth-sub   { text-align: center; color: var(--muted); margin-bottom: 28px; font-size: .95rem; }

/* === Hero === */
.hero       { text-align: center; padding: 60px 20px 40px; }
.hero-title { font-size: 3rem; font-weight: 900; line-height: 1.1; margin-bottom: 8px; }
.hero-sub   { font-size: 1.2rem; color: var(--muted); margin-bottom: 24px; }
.hero-btns  { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

/* === Restaurant card emoji === */
.rest-emoji { font-size: 3rem; text-align: center; padding: 20px; background: var(--bg); border-bottom: var(--border); }

/* === Menu items === */
.menu-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0;
    border-bottom: 2px dashed #E8E0D5;
    gap: 12px;
}
.menu-item:last-child { border-bottom: none; }
.item-name  { font-weight: 700; }
.item-desc  { font-size: .85rem; color: var(--muted); }
.item-price { font-weight: 900; font-size: 1.1rem; color: var(--orange); white-space: nowrap; }

/* === Restaurant detail layout === */
.rest-layout { display: grid; grid-template-columns: 1fr 300px; gap: 24px; align-items: start; }

/* === Cart === */
.cart-box   { background: var(--yellow); border: var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px; position: sticky; top: 80px; }
.cart-title { font-size: 1.2rem; font-weight: 900; margin-bottom: 14px; }
.cart-row   { display: flex; justify-content: space-between; padding: 5px 0; font-size: .9rem; }
.cart-total { border-top: var(--border); margin-top: 10px; padding-top: 10px; font-weight: 900; font-size: 1.1rem; display: flex; justify-content: space-between; }

/* === Modal === */
.modal-bg {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000; padding: 20px;
}
.modal-box {
    background: var(--white);
    border: var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 32px;
    width: 100%; max-width: 480px;
    max-height: 90vh; overflow-y: auto;
}
.modal-title   { font-size: 1.4rem; font-weight: 900; margin-bottom: 20px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

/* === Tabs === */
.tab-nav { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.tab-btn {
    padding: 8px 18px;
    border: var(--border);
    border-radius: var(--radius-sm);
    background: var(--white);
    font-family: 'Nunito', sans-serif;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 2px 2px 0 #2C2C2C;
    transition: all .1s;
}
.tab-btn.active { background: var(--orange); color: white; box-shadow: none; transform: translate(2px,2px); }

/* === Table === */
.tbl-wrap { overflow-x: auto; }
table     { width: 100%; border-collapse: collapse; }
th { background: var(--orange); color: white; padding: 10px 14px; text-align: left; font-weight: 800; border: 2px solid #2C2C2C; font-size: .9rem; }
td { padding: 8px 14px; border: 2px solid #E8E0D5; font-size: .85rem; }
tr:nth-child(even) td { background: var(--bg); }

/* === Misc === */
.loader { text-align: center; padding: 48px; font-size: 1.2rem; color: var(--muted); }
.empty  { text-align: center; padding: 60px 20px; color: var(--muted); }
.empty-icon { font-size: 4rem; margin-bottom: 12px; }

/* === Toast === */
.toast {
    position: fixed; bottom: 20px; right: 20px;
    background: var(--text); color: white;
    border: var(--border); border-radius: var(--radius-sm);
    padding: 12px 20px; font-weight: 700;
    box-shadow: var(--shadow); z-index: 9999;
    animation: slideIn .2s ease;
}
.toast.success { background: var(--green); }
.toast.error   { background: var(--red); }
@keyframes slideIn { from { transform: translateY(20px); opacity:0; } to { transform: translateY(0); opacity:1; } }

/* === Responsive === */
@media (max-width: 768px) {
    .rest-layout { grid-template-columns: 1fr; }
    .hero-title  { font-size: 2rem; }
    .grid-3, .grid-2 { grid-template-columns: 1fr; }
    .page-title  { font-size: 1.5rem; }
}
```

- [ ] **Step 2: Verify styles load**

Reload `http://localhost/Szalmaprojekt/frontend/` — background should be warm white `#FFF8F0` (no yellow-white plain default).

---

## Task 4: API Client

**Files:**
- Create: `frontend/js/api.js`

- [ ] **Step 1: Create `frontend/js/api.js`**

```js
const API = (() => {
    function base() {
        const p = window.location.pathname;
        const i = p.toLowerCase().indexOf('szalmaprojekt');
        const root = i === -1 ? '' : p.slice(0, i + 'szalmaprojekt'.length);
        return window.location.origin + root + '/api';
    }

    async function req(method, endpoint, body, token) {
        const headers = { 'Content-Type': 'application/json' };
        if (token) headers['Authorization'] = 'Bearer ' + token;
        const opts = { method, headers };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetch(base() + endpoint, opts);
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Request failed');
        return json.data;
    }

    return {
        // Auth
        register: (b)            => req('POST', '/auth/register', b),
        login:    (b)            => req('POST', '/auth/login', b),

        // Restaurants
        getRestaurants:   (t)         => req('GET',    '/restaurants',       null, t),
        getRestaurant:    (id)        => req('GET',    `/restaurants/${id}`),
        createRestaurant: (b, t)      => req('POST',   '/restaurants',       b, t),
        updateRestaurant: (id, b, t)  => req('PUT',    `/restaurants/${id}`, b, t),
        deleteRestaurant: (id, t)     => req('DELETE', `/restaurants/${id}`, null, t),

        // Menu
        getMenu:        (rid)          => req('GET',    `/restaurants/${rid}/menu`),
        createMenuItem: (rid, b, t)    => req('POST',   `/restaurants/${rid}/menu`,         b, t),
        updateMenuItem: (rid, mid, b, t) => req('PUT',  `/restaurants/${rid}/menu/${mid}`,  b, t),
        deleteMenuItem: (rid, mid, t)  => req('DELETE', `/restaurants/${rid}/menu/${mid}`,  null, t),

        // Orders
        getOrders:         (t)         => req('GET',    '/orders',               null, t),
        createOrder:       (b, t)      => req('POST',   '/orders',               b, t),
        updateOrderStatus: (id, s, t)  => req('PUT',    `/orders/${id}/status`,  { status: s }, t),
        cancelOrder:       (id, t)     => req('DELETE', `/orders/${id}`,         null, t),
    };
})();
```

---

## Task 5: Auth Module

**Files:**
- Create: `frontend/js/auth.js`

- [ ] **Step 1: Create `frontend/js/auth.js`**

```js
const Auth = (() => {
    const TK = 'fz_token';
    const UK = 'fz_user';
    return {
        save:      (token, user) => { localStorage.setItem(TK, token); localStorage.setItem(UK, JSON.stringify(user)); },
        clear:     ()            => { localStorage.removeItem(TK); localStorage.removeItem(UK); },
        token:     ()            => localStorage.getItem(TK),
        user:      ()            => { const u = localStorage.getItem(UK); return u ? JSON.parse(u) : null; },
        loggedIn:  ()            => !!localStorage.getItem(TK),
        role:      ()            => { const u = localStorage.getItem(UK); return u ? JSON.parse(u).role : null; },
    };
})();
```

---

## Task 6: App Core — Router + Navbar + Helpers

**Files:**
- Create: `frontend/js/app.js` (initial version, will be extended in Tasks 7–10)

- [ ] **Step 1: Create `frontend/js/app.js` with core structure**

```js
const App = {
    // ── Cart state ──────────────────────────────────────────────────────────
    cart: [],
    cartRid: null,

    // ── Bootstrap ───────────────────────────────────────────────────────────
    init() {
        this.navbar();
        this.route();
        window.addEventListener('hashchange', () => this.route());
    },

    // ── Router ──────────────────────────────────────────────────────────────
    route() {
        const hash   = window.location.hash.replace('#', '') || 'restaurants';
        const parts  = hash.split('/');
        const view   = parts[0];
        const param  = parts[1];

        this.setMain('<div class="loader">🍳 Loading…</div>');

        switch (view) {
            case 'login':       this.renderLogin();              break;
            case 'register':    this.renderRegister();           break;
            case 'restaurants': this.renderRestaurants();        break;
            case 'restaurant':  this.renderRestaurantDetail(param); break;
            case 'orders':      this.renderCustomerOrders();     break;
            case 'admin':       this.renderAdmin(param || 'restaurants'); break;
            case 'driver':      this.renderDriver();             break;
            default:            this.renderRestaurants();
        }
    },

    // ── Navbar ──────────────────────────────────────────────────────────────
    navbar() {
        const user  = Auth.user();
        let links   = '';

        if (!user) {
            links = `
                <a href="#login"    class="btn btn-secondary btn-sm">🔑 Login</a>
                <a href="#register" class="btn btn-success   btn-sm">✨ Sign Up</a>`;
        } else {
            if (user.role === 'customer') {
                links = `
                    <a href="#restaurants" class="btn btn-secondary btn-sm">🍽️ Restaurants</a>
                    <a href="#orders"      class="btn btn-info btn-sm">📦 My Orders</a>`;
            } else if (user.role === 'admin') {
                links = `
                    <a href="#restaurants" class="btn btn-secondary btn-sm">🍽️ Restaurants</a>
                    <a href="#admin"       class="btn btn-info btn-sm">⚙️ Dashboard</a>`;
            } else if (user.role === 'driver') {
                links = `<a href="#driver" class="btn btn-info btn-sm">🚴 Deliveries</a>`;
            }
            links += `
                <span class="navbar-user">👤 ${this.esc(user.name)}</span>
                <button class="btn btn-danger btn-sm" onclick="App.logout()">🚪 Logout</button>`;
        }

        document.getElementById('navbar-links').innerHTML = links;
    },

    logout() {
        Auth.clear();
        this.cart = [];
        this.cartRid = null;
        this.navbar();
        window.location.hash = '#restaurants';
    },

    // ── Utilities ────────────────────────────────────────────────────────────
    setMain(html)  { document.getElementById('main').innerHTML = html; },
    esc(str)       { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); },
    fmt(n)         { return '$' + parseFloat(n).toFixed(2); },

    badge(status) {
        const labels = {
            pending:          '⏳ Pending',
            preparing:        '👨‍🍳 Preparing',
            out_for_delivery: '🚴 On the way',
            delivered:        '✅ Delivered',
            cancelled:        '❌ Cancelled',
        };
        return `<span class="badge badge-${status}">${labels[status] || status}</span>`;
    },

    rEmoji() {
        const e = ['🍕','🍔','🌮','🍜','🍣','🥗','🍛','🥩','🍝','🌯'];
        return e[Math.floor(Math.random() * e.length)];
    },

    toast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = `toast ${type}`;
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3000);
    },

    modal(html) {
        document.getElementById('modal-bg')?.remove();
        const el = document.createElement('div');
        el.id = 'modal-bg';
        el.className = 'modal-bg';
        el.innerHTML = `<div class="modal-box">${html}</div>`;
        el.addEventListener('click', e => { if (e.target === el) el.remove(); });
        document.body.appendChild(el);
    },

    closeModal() { document.getElementById('modal-bg')?.remove(); },

    guard(role = null) {
        if (!Auth.loggedIn()) { window.location.hash = '#login'; return false; }
        if (role && Auth.role() !== role) { this.toast('Access denied ❌', 'error'); return false; }
        return true;
    },
};

window.addEventListener('load', () => App.init());
```

- [ ] **Step 2: Open browser and check console**

Navigate to `http://localhost/Szalmaprojekt/frontend/` — navbar should render with Login + Sign Up buttons. No console errors.

---

## Task 7: Auth Views (Login + Register)

**Files:**
- Modify: `frontend/js/app.js` — add `renderLogin()` and `renderRegister()`

- [ ] **Step 1: Add login and register methods to `app.js` before the closing `};`**

```js
    // ── LOGIN ─────────────────────────────────────────────────────────────────
    renderLogin() {
        this.setMain(`
            <div class="auth-wrap">
                <div class="auth-card">
                    <div class="auth-title">🔑 Welcome back!</div>
                    <div class="auth-sub">Log in to order delicious food</div>
                    <div id="auth-err"></div>
                    <form id="login-form">
                        <div class="form-group">
                            <label class="form-label">📧 Email</label>
                            <input class="form-control" type="email" name="email" placeholder="you@example.com" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">🔒 Password</label>
                            <input class="form-control" type="password" name="password" placeholder="••••••••" required>
                        </div>
                        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">🚀 Login</button>
                    </form>
                    <p style="text-align:center;margin-top:18px;color:var(--muted)">
                        No account? <a href="#register" style="color:var(--orange);font-weight:800">Sign up!</a>
                    </p>
                </div>
            </div>`);

        document.getElementById('login-form').addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                const d = await API.login({ email: fd.get('email'), password: fd.get('password') });
                Auth.save(d.token, d.user);
                this.navbar();
                window.location.hash = d.user.role === 'admin' ? '#admin'
                                      : d.user.role === 'driver' ? '#driver'
                                      : '#restaurants';
            } catch (err) {
                document.getElementById('auth-err').innerHTML =
                    `<div class="alert alert-error">❌ ${this.esc(err.message)}</div>`;
            }
        });
    },

    // ── REGISTER ──────────────────────────────────────────────────────────────
    renderRegister() {
        this.setMain(`
            <div class="auth-wrap">
                <div class="auth-card">
                    <div class="auth-title">✨ Join FoodieZone!</div>
                    <div class="auth-sub">Create your account and start ordering</div>
                    <div id="auth-err"></div>
                    <form id="reg-form">
                        <div class="form-group">
                            <label class="form-label">👤 Name</label>
                            <input class="form-control" type="text" name="name" placeholder="Your full name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">📧 Email</label>
                            <input class="form-control" type="email" name="email" placeholder="you@example.com" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">🔒 Password</label>
                            <input class="form-control" type="password" name="password" placeholder="At least 6 characters" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">👥 Role</label>
                            <select class="form-control" name="role">
                                <option value="customer">🛍️ Customer</option>
                                <option value="driver">🚴 Driver</option>
                            </select>
                        </div>
                        <button class="btn btn-success" type="submit" style="width:100%;justify-content:center">🎉 Create Account</button>
                    </form>
                    <p style="text-align:center;margin-top:18px;color:var(--muted)">
                        Have an account? <a href="#login" style="color:var(--orange);font-weight:800">Log in!</a>
                    </p>
                </div>
            </div>`);

        document.getElementById('reg-form').addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                await API.register({ name: fd.get('name'), email: fd.get('email'), password: fd.get('password'), role: fd.get('role') });
                this.toast('🎉 Account created! Please log in.');
                window.location.hash = '#login';
            } catch (err) {
                document.getElementById('auth-err').innerHTML =
                    `<div class="alert alert-error">❌ ${this.esc(err.message)}</div>`;
            }
        });
    },
```

- [ ] **Step 2: Test login view**

Navigate to `http://localhost/Szalmaprojekt/frontend/#login` — cartoon login card appears, centered. Try wrong credentials — error alert appears. (Needs XAMPP running for actual API test.)

---

## Task 8: Restaurant Views + Cart

**Files:**
- Modify: `frontend/js/app.js` — add restaurant list, detail, cart methods

- [ ] **Step 1: Add restaurant and cart methods to `app.js` before `};`**

```js
    // ── RESTAURANTS LIST ──────────────────────────────────────────────────────
    async renderRestaurants() {
        try {
            const list = await API.getRestaurants(Auth.token());
            const user = Auth.user();

            const hero = user ? '' : `
                <div class="hero">
                    <div class="hero-title">🍕 FoodieZone</div>
                    <div class="hero-sub">Order from the best restaurants in town!</div>
                    <div class="hero-btns">
                        <a href="#login"    class="btn btn-primary">🔑 Login</a>
                        <a href="#register" class="btn btn-success">✨ Sign Up</a>
                    </div>
                </div>`;

            const cards = list.length === 0
                ? `<div class="empty"><div class="empty-icon">🍽️</div><p>No restaurants yet!</p></div>`
                : list.map(r => `
                    <div class="card clickable" onclick="window.location.hash='#restaurant/${r.id}'">
                        <div class="rest-emoji">${this.rEmoji()}</div>
                        <div class="card-body">
                            <div class="card-title">${this.esc(r.name)}</div>
                            <div class="card-text">📍 ${this.esc(r.address)}</div>
                            <div class="card-text" style="margin-top:4px">📞 ${this.esc(r.phone)}</div>
                            <div style="margin-top:14px">
                                <a href="#restaurant/${r.id}" class="btn btn-primary btn-sm">🍽️ View Menu</a>
                            </div>
                        </div>
                    </div>`).join('');

            this.setMain(`
                ${hero}
                <div class="page">
                    <div class="page-header">
                        <div>
                            <div class="page-title">🏪 Restaurants</div>
                            <div class="page-subtitle">${list.length} places to eat</div>
                        </div>
                    </div>
                    <div class="grid-3">${cards}</div>
                </div>`);
        } catch (err) {
            this.setMain(`<div class="page"><div class="alert alert-error">❌ ${this.esc(err.message)}</div></div>`);
        }
    },

    // ── RESTAURANT DETAIL ─────────────────────────────────────────────────────
    async renderRestaurantDetail(id) {
        try {
            const [rest, menu] = await Promise.all([API.getRestaurant(id), API.getMenu(id)]);
            const isCustomer = Auth.loggedIn() && Auth.role() === 'customer';

            const menuRows = menu.filter(i => i.available).map(item => `
                <div class="menu-item">
                    <div>
                        <div class="item-name">🍴 ${this.esc(item.name)}</div>
                        ${item.description ? `<div class="item-desc">${this.esc(item.description)}</div>` : ''}
                    </div>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="item-price">${this.fmt(item.price)}</div>
                        ${isCustomer ? `<button class="btn btn-secondary btn-sm" onclick="App.addToCart(${item.id},'${this.esc(item.name)}',${item.price},${id})">➕ Add</button>` : ''}
                    </div>
                </div>`).join('') || `<div class="empty"><div class="empty-icon">🍽️</div><p>No items yet!</p></div>`;

            const menuCard = `<div class="card"><div class="card-body">${menuRows}</div></div>`;
            const cartCard = isCustomer ? this.cartHTML() : '';

            this.setMain(`
                <div class="page">
                    <div class="page-header">
                        <div>
                            <a href="#restaurants" class="btn btn-secondary btn-sm" style="margin-bottom:8px">← Back</a>
                            <div class="page-title">🍽️ ${this.esc(rest.name)}</div>
                            <div class="page-subtitle">📍 ${this.esc(rest.address)} &nbsp;|&nbsp; 📞 ${this.esc(rest.phone)}</div>
                        </div>
                    </div>
                    ${isCustomer
                        ? `<div class="rest-layout">${menuCard}${cartCard}</div>`
                        : menuCard}
                </div>`);
        } catch (err) {
            this.setMain(`<div class="page"><div class="alert alert-error">❌ ${this.esc(err.message)}</div></div>`);
        }
    },

    // ── CART ──────────────────────────────────────────────────────────────────
    cartHTML() {
        const total = this.cart.reduce((s, i) => s + i.price * i.qty, 0);
        const rows  = this.cart.length === 0
            ? `<p style="color:var(--muted);text-align:center;padding:10px">Cart is empty 🛒</p>`
            : this.cart.map(i => `<div class="cart-row"><span>${i.qty}× ${this.esc(i.name)}</span><span>${this.fmt(i.price * i.qty)}</span></div>`).join('');

        return `
            <div class="cart-box" id="cart-box">
                <div class="cart-title">🛒 Your Cart</div>
                ${rows}
                <div class="cart-total"><span>Total</span><span>${this.fmt(total)}</span></div>
                <button class="btn btn-primary" style="width:100%;justify-content:center;margin-top:14px"
                    onclick="App.placeOrder()" ${this.cart.length === 0 ? 'disabled' : ''}>🚀 Place Order</button>
                ${this.cart.length > 0 ? `<button class="btn btn-danger btn-sm" style="width:100%;justify-content:center;margin-top:8px" onclick="App.clearCart()">🗑️ Clear</button>` : ''}
            </div>`;
    },

    refreshCart() {
        const box = document.getElementById('cart-box');
        if (box) box.outerHTML = this.cartHTML();
    },

    addToCart(id, name, price, rid) {
        if (this.cartRid && this.cartRid !== String(rid)) {
            if (!confirm('Clear cart from other restaurant?')) return;
            this.cart = [];
        }
        this.cartRid = String(rid);
        const ex = this.cart.find(i => i.id === id);
        if (ex) { ex.qty++; } else { this.cart.push({ id, name, price: parseFloat(price), qty: 1 }); }
        this.refreshCart();
        this.toast(`➕ ${name} added!`);
    },

    clearCart() { this.cart = []; this.cartRid = null; this.refreshCart(); },

    async placeOrder() {
        if (!this.guard('customer')) return;
        if (!this.cart.length) return;
        try {
            await API.createOrder({
                restaurant_id: parseInt(this.cartRid),
                items: this.cart.map(i => ({ menu_item_id: i.id, quantity: i.qty })),
            }, Auth.token());
            this.cart = []; this.cartRid = null;
            this.toast('🎉 Order placed!');
            window.location.hash = '#orders';
        } catch (err) { this.toast('❌ ' + err.message, 'error'); }
    },
```

- [ ] **Step 2: Verify restaurant list renders**

Navigate to `http://localhost/Szalmaprojekt/frontend/` — restaurant cards appear in a colorful grid. Click one — menu detail page loads with back button.

---

## Task 9: Customer Orders View

**Files:**
- Modify: `frontend/js/app.js` — add `renderCustomerOrders()` and `cancelOrder()`

- [ ] **Step 1: Add customer order methods to `app.js` before `};`**

```js
    // ── CUSTOMER ORDERS ───────────────────────────────────────────────────────
    async renderCustomerOrders() {
        if (!this.guard('customer')) return;
        try {
            const orders = await API.getOrders(Auth.token());

            const cards = orders.length === 0
                ? `<div class="empty">
                       <div class="empty-icon">📦</div>
                       <p>No orders yet! Go order some food 🍕</p>
                       <a href="#restaurants" class="btn btn-primary" style="margin-top:16px">Browse Restaurants</a>
                   </div>`
                : orders.map(o => `
                    <div class="card">
                        <div class="card-body">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
                                <div>
                                    <div class="card-title">Order #${o.id}</div>
                                    <div class="card-text">🕐 ${new Date(o.created_at).toLocaleString()}</div>
                                    <div style="margin-top:8px">${this.badge(o.status)}</div>
                                </div>
                                <div style="text-align:right">
                                    <div style="font-size:1.3rem;font-weight:900;color:var(--orange)">${this.fmt(o.total_price)}</div>
                                    ${o.status === 'pending'
                                        ? `<button class="btn btn-danger btn-sm" style="margin-top:8px" onclick="App.cancelOrder(${o.id})">❌ Cancel</button>`
                                        : ''}
                                </div>
                            </div>
                        </div>
                    </div>`).join('');

            this.setMain(`
                <div class="page">
                    <div class="page-header">
                        <div class="page-title">📦 My Orders</div>
                        <button class="btn btn-secondary" onclick="App.renderCustomerOrders()">🔄 Refresh</button>
                    </div>
                    <div class="grid-2">${cards}</div>
                </div>`);
        } catch (err) {
            this.setMain(`<div class="page"><div class="alert alert-error">❌ ${this.esc(err.message)}</div></div>`);
        }
    },

    async cancelOrder(id) {
        if (!confirm('Cancel this order?')) return;
        try {
            await API.cancelOrder(id, Auth.token());
            this.toast('Order cancelled');
            this.renderCustomerOrders();
        } catch (err) { this.toast('❌ ' + err.message, 'error'); }
    },
```

---

## Task 10: Admin Dashboard

**Files:**
- Modify: `frontend/js/app.js` — add all admin methods

- [ ] **Step 1: Add admin methods to `app.js` before `};`**

```js
    // ── ADMIN DASHBOARD ───────────────────────────────────────────────────────
    async renderAdmin(tab = 'restaurants') {
        if (!this.guard('admin')) return;
        this.setMain(`
            <div class="page">
                <div class="page-header">
                    <div class="page-title">⚙️ Admin Dashboard</div>
                    <button class="btn btn-primary" onclick="App.showCreateRestaurantModal()">➕ New Restaurant</button>
                </div>
                <div class="tab-nav">
                    <button class="tab-btn ${tab==='restaurants'?'active':''}" onclick="App.renderAdmin('restaurants')">🏪 My Restaurants</button>
                    <button class="tab-btn ${tab==='orders'?'active':''}"      onclick="App.renderAdmin('orders')">📋 All Orders</button>
                </div>
                <div id="admin-body"><div class="loader">🍳 Loading…</div></div>
            </div>`);

        tab === 'restaurants' ? this.loadAdminRestaurants() : this.loadAdminOrders();
    },

    async loadAdminRestaurants() {
        try {
            const all  = await API.getRestaurants(Auth.token());
            const user = Auth.user();
            const mine = all.filter(r => r.admin_id == user.id);

            const html = mine.length === 0
                ? `<div class="empty"><div class="empty-icon">🏪</div><p>No restaurants yet!</p></div>`
                : mine.map(r => `
                    <div class="card" style="margin-bottom:12px">
                        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                            <div>
                                <div class="card-title">🏪 ${this.esc(r.name)}</div>
                                <div class="card-text">📍 ${this.esc(r.address)} | 📞 ${this.esc(r.phone)}</div>
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                <button class="btn btn-info btn-sm"       onclick="App.showMenuManager(${r.id},'${this.esc(r.name)}')">🍽️ Menu</button>
                                <button class="btn btn-secondary btn-sm"  onclick="App.showEditRestaurantModal(${r.id},'${this.esc(r.name)}','${this.esc(r.address)}','${this.esc(r.phone)}')">✏️ Edit</button>
                                <button class="btn btn-danger btn-sm"     onclick="App.deleteRestaurant(${r.id})">🗑️ Delete</button>
                            </div>
                        </div>
                    </div>`).join('');

            document.getElementById('admin-body').innerHTML = html;
        } catch (err) {
            document.getElementById('admin-body').innerHTML = `<div class="alert alert-error">❌ ${this.esc(err.message)}</div>`;
        }
    },

    showCreateRestaurantModal() {
        this.modal(`
            <div class="modal-title">➕ New Restaurant</div>
            <form id="cr-form">
                <div class="form-group"><label class="form-label">🏪 Name</label>
                    <input class="form-control" name="name" required placeholder="Restaurant name"></div>
                <div class="form-group"><label class="form-label">📍 Address</label>
                    <input class="form-control" name="address" required placeholder="Street address"></div>
                <div class="form-group"><label class="form-label">📞 Phone</label>
                    <input class="form-control" name="phone" required placeholder="+1234567890"></div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="App.closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">➕ Create</button>
                </div>
            </form>`);
        document.getElementById('cr-form').addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                await API.createRestaurant({ name: fd.get('name'), address: fd.get('address'), phone: fd.get('phone') }, Auth.token());
                this.closeModal(); this.toast('🎉 Restaurant created!'); this.loadAdminRestaurants();
            } catch (err) { this.toast('❌ ' + err.message, 'error'); }
        });
    },

    showEditRestaurantModal(id, name, address, phone) {
        this.modal(`
            <div class="modal-title">✏️ Edit Restaurant</div>
            <form id="er-form">
                <div class="form-group"><label class="form-label">🏪 Name</label>
                    <input class="form-control" name="name" value="${name}" required></div>
                <div class="form-group"><label class="form-label">📍 Address</label>
                    <input class="form-control" name="address" value="${address}" required></div>
                <div class="form-group"><label class="form-label">📞 Phone</label>
                    <input class="form-control" name="phone" value="${phone}" required></div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="App.closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save</button>
                </div>
            </form>`);
        document.getElementById('er-form').addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                await API.updateRestaurant(id, { name: fd.get('name'), address: fd.get('address'), phone: fd.get('phone') }, Auth.token());
                this.closeModal(); this.toast('✅ Updated!'); this.loadAdminRestaurants();
            } catch (err) { this.toast('❌ ' + err.message, 'error'); }
        });
    },

    async deleteRestaurant(id) {
        if (!confirm('Delete this restaurant and all its menu items?')) return;
        try {
            await API.deleteRestaurant(id, Auth.token());
            this.toast('🗑️ Deleted'); this.loadAdminRestaurants();
        } catch (err) { this.toast('❌ ' + err.message, 'error'); }
    },

    async showMenuManager(rid, rname) {
        try {
            const menu = await API.getMenu(rid);
            const rows = menu.map(item => `
                <tr>
                    <td>${this.esc(item.name)}</td>
                    <td>${this.esc(item.description || '—')}</td>
                    <td>${this.fmt(item.price)}</td>
                    <td>${item.available ? '✅' : '❌'}</td>
                    <td style="display:flex;gap:6px">
                        <button class="btn btn-secondary btn-sm" onclick="App.showEditMenuItemModal(${rid},${item.id},'${this.esc(item.name)}','${this.esc(item.description||'')}',${item.price},${item.available})">✏️</button>
                        <button class="btn btn-danger btn-sm"    onclick="App.deleteMenuItem(${rid},${item.id})">🗑️</button>
                    </td>
                </tr>`).join('');

            this.modal(`
                <div class="modal-title">🍽️ Menu — ${this.esc(rname)}</div>
                <button class="btn btn-success btn-sm" style="margin-bottom:14px" onclick="App.showAddMenuItemModal(${rid},'${this.esc(rname)}')">➕ Add Item</button>
                ${menu.length === 0
                    ? '<p style="color:var(--muted)">No items yet.</p>'
                    : `<div class="tbl-wrap"><table><thead><tr><th>Name</th><th>Description</th><th>Price</th><th>Available</th><th>Actions</th></tr></thead><tbody>${rows}</tbody></table></div>`}
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="App.closeModal()">Close</button>
                </div>`);
        } catch (err) { this.toast('❌ ' + err.message, 'error'); }
    },

    showAddMenuItemModal(rid, rname) {
        this.modal(`
            <div class="modal-title">➕ Add Menu Item</div>
            <form id="ami-form">
                <div class="form-group"><label class="form-label">🍴 Name</label>
                    <input class="form-control" name="name" required placeholder="Item name"></div>
                <div class="form-group"><label class="form-label">📝 Description</label>
                    <input class="form-control" name="description" placeholder="Optional"></div>
                <div class="form-group"><label class="form-label">💰 Price ($)</label>
                    <input class="form-control" name="price" type="number" step="0.01" min="0.01" required placeholder="9.99"></div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="App.showMenuManager(${rid},'${this.esc(rname)}')">← Back</button>
                    <button type="submit" class="btn btn-success">➕ Add</button>
                </div>
            </form>`);
        document.getElementById('ami-form').addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                await API.createMenuItem(rid, { name: fd.get('name'), description: fd.get('description'), price: fd.get('price') }, Auth.token());
                this.toast('✅ Item added!'); this.showMenuManager(rid, rname);
            } catch (err) { this.toast('❌ ' + err.message, 'error'); }
        });
    },

    showEditMenuItemModal(rid, mid, name, desc, price, avail) {
        this.modal(`
            <div class="modal-title">✏️ Edit Menu Item</div>
            <form id="emi-form">
                <div class="form-group"><label class="form-label">🍴 Name</label>
                    <input class="form-control" name="name" value="${name}" required></div>
                <div class="form-group"><label class="form-label">📝 Description</label>
                    <input class="form-control" name="description" value="${desc}"></div>
                <div class="form-group"><label class="form-label">💰 Price ($)</label>
                    <input class="form-control" name="price" type="number" step="0.01" min="0.01" value="${price}" required></div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:700;cursor:pointer">
                        <input type="checkbox" name="available" ${avail ? 'checked' : ''}> Available
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="App.showMenuManager(${rid},'')">← Back</button>
                    <button type="submit" class="btn btn-primary">💾 Save</button>
                </div>
            </form>`);
        document.getElementById('emi-form').addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                await API.updateMenuItem(rid, mid, { name: fd.get('name'), description: fd.get('description'), price: fd.get('price'), available: fd.get('available') ? 1 : 0 }, Auth.token());
                this.toast('✅ Updated!'); this.showMenuManager(rid, name);
            } catch (err) { this.toast('❌ ' + err.message, 'error'); }
        });
    },

    async deleteMenuItem(rid, mid) {
        if (!confirm('Delete this menu item?')) return;
        try {
            await API.deleteMenuItem(rid, mid, Auth.token());
            this.toast('🗑️ Deleted'); this.showMenuManager(rid, '');
        } catch (err) { this.toast('❌ ' + err.message, 'error'); }
    },

    async loadAdminOrders() {
        try {
            const orders = await API.getOrders(Auth.token());
            if (!orders.length) {
                document.getElementById('admin-body').innerHTML = `<div class="empty"><div class="empty-icon">📋</div><p>No orders yet!</p></div>`;
                return;
            }
            const rows = orders.map(o => `
                <tr>
                    <td>#${o.id}</td>
                    <td>${new Date(o.created_at).toLocaleString()}</td>
                    <td>${this.fmt(o.total_price)}</td>
                    <td>${this.badge(o.status)}</td>
                    <td>
                        <select class="form-control" style="padding:4px 8px;font-size:.8rem" onchange="App.updateOrderStatus(${o.id},this.value)">
                            ${['pending','preparing','out_for_delivery','delivered','cancelled'].map(s => `<option value="${s}" ${o.status===s?'selected':''}>${s}</option>`).join('')}
                        </select>
                    </td>
                </tr>`).join('');
            document.getElementById('admin-body').innerHTML = `
                <div class="tbl-wrap">
                    <table><thead><tr><th>ID</th><th>Date</th><th>Total</th><th>Status</th><th>Update</th></tr></thead>
                    <tbody>${rows}</tbody></table>
                </div>`;
        } catch (err) {
            document.getElementById('admin-body').innerHTML = `<div class="alert alert-error">❌ ${this.esc(err.message)}</div>`;
        }
    },

    async updateOrderStatus(id, status) {
        try {
            await API.updateOrderStatus(id, status, Auth.token());
            this.toast(`✅ Order #${id} → ${status}`);
        } catch (err) { this.toast('❌ ' + err.message, 'error'); }
    },
```

---

## Task 11: Driver Dashboard

**Files:**
- Modify: `frontend/js/app.js` — add driver methods

- [ ] **Step 1: Add driver methods to `app.js` before `};`**

```js
    // ── DRIVER DASHBOARD ──────────────────────────────────────────────────────
    async renderDriver() {
        if (!this.guard('driver')) return;
        try {
            const orders = await API.getOrders(Auth.token());

            const cards = orders.length === 0
                ? `<div class="empty"><div class="empty-icon">🚴</div><p>No active deliveries right now!</p></div>`
                : orders.map(o => `
                    <div class="card">
                        <div class="card-body">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
                                <div>
                                    <div class="card-title">Order #${o.id}</div>
                                    <div class="card-text">🕐 ${new Date(o.created_at).toLocaleString()}</div>
                                    <div style="margin-top:8px">${this.badge(o.status)}</div>
                                </div>
                                <div style="text-align:right">
                                    <div style="font-size:1.3rem;font-weight:900;color:var(--orange)">${this.fmt(o.total_price)}</div>
                                    ${o.status === 'preparing'
                                        ? `<button class="btn btn-info btn-sm" style="margin-top:8px" onclick="App.driverUpdateStatus(${o.id},'out_for_delivery')">🚴 Pick Up</button>`
                                        : ''}
                                    ${o.status === 'out_for_delivery'
                                        ? `<button class="btn btn-success btn-sm" style="margin-top:8px" onclick="App.driverUpdateStatus(${o.id},'delivered')">✅ Delivered</button>`
                                        : ''}
                                </div>
                            </div>
                        </div>
                    </div>`).join('');

            this.setMain(`
                <div class="page">
                    <div class="page-header">
                        <div class="page-title">🚴 My Deliveries</div>
                        <button class="btn btn-secondary" onclick="App.renderDriver()">🔄 Refresh</button>
                    </div>
                    <div class="grid-2">${cards}</div>
                </div>`);
        } catch (err) {
            this.setMain(`<div class="page"><div class="alert alert-error">❌ ${this.esc(err.message)}</div></div>`);
        }
    },

    async driverUpdateStatus(id, status) {
        try {
            await API.updateOrderStatus(id, status, Auth.token());
            this.toast(status === 'delivered' ? '✅ Delivered!' : '🚴 On the way!');
            this.renderDriver();
        } catch (err) { this.toast('❌ ' + err.message, 'error'); }
    },
```

---

## Task 12: Final Smoke Test

- [ ] **Step 1: Start XAMPP (Apache + MySQL) if not running**

- [ ] **Step 2: Verify all files exist**

```
frontend/
  index.html
  style.css
  js/
    api.js
    auth.js
    app.js
```

- [ ] **Step 3: Open `http://localhost/Szalmaprojekt/frontend/`**

Expected:
- Cartoon navbar (orange, Nunito font) with Login + Sign Up buttons
- Hero section + empty restaurant list (or seeded restaurants if DB is running)

- [ ] **Step 4: Test register flow**

Navigate to `#register` → create a customer account → redirected to `#login` → login → redirected to restaurant list.

- [ ] **Step 5: Test order flow**

Login as customer → browse restaurant → click "View Menu" → add items → click "Place Order" → redirected to My Orders → order shows as "pending" → cancel it.

- [ ] **Step 6: Test admin flow**

Login as `admin1@example.com / password` → admin dashboard → create a restaurant → open menu manager → add a menu item → go to All Orders tab → change an order status.

- [ ] **Step 7: Test driver flow**

Login as `driver1@example.com / password` → driver dashboard → see preparing orders → click "Pick Up" → status changes to out_for_delivery → click "Delivered".

---

## Self-Review

| Spec/Requirement | Covered | Task |
|---|---|---|
| Restaurant list (public) | ✅ | Task 8 |
| Restaurant detail + menu | ✅ | Task 8 |
| Customer: add to cart + place order | ✅ | Task 8 |
| Customer: view orders + cancel pending | ✅ | Task 9 |
| Admin: restaurant CRUD | ✅ | Task 10 |
| Admin: menu item CRUD | ✅ | Task 10 |
| Admin: order status updates | ✅ | Task 10 |
| Driver: see active orders | ✅ | Task 1 + Task 11 |
| Driver: mark out_for_delivery / delivered | ✅ | Task 11 |
| Login / Register / Logout | ✅ | Task 7 |
| Cartoon style (Nunito, colors, shadows) | ✅ | Task 3 |
| JWT token stored in localStorage | ✅ | Task 5 |
| Role-based nav + guards | ✅ | Task 6 |
| Driver orders backend fix | ✅ | Task 1 |
