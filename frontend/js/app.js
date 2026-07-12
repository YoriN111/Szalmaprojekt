// ── Module state ─────────────────────────────────────────────────────────────
const cart = { restaurantId: null, items: {} };
let _menuItems = [];
let _menuRid   = null;
let _driverMap = null;
let _menuCache = [];

// ── Cart ─────────────────────────────────────────────────────────────────────
function cartAdd(restaurantId, item) {
    if (cart.restaurantId !== null && cart.restaurantId !== restaurantId) {
        if (!confirm('Your cart has items from another restaurant. Clear it?')) return;
        cartClear();
    }
    cart.restaurantId = restaurantId;
    if (cart.items[item.id]) {
        cart.items[item.id].qty++;
    } else {
        cart.items[item.id] = { name: item.name, price: parseFloat(item.price), qty: 1 };
    }
}

function cartChange(itemId, delta) {
    if (!cart.items[itemId]) return;
    cart.items[itemId].qty += delta;
    if (cart.items[itemId].qty <= 0) {
        delete cart.items[itemId];
        if (!Object.keys(cart.items).length) cart.restaurantId = null;
    }
}

function cartClear() { cart.restaurantId = null; cart.items = {}; }
function cartTotal()  { return Object.values(cart.items).reduce((s, i) => s + i.price * i.qty, 0); }

// ── Helpers ───────────────────────────────────────────────────────────────────
const EMOJIS = ['🍕','🍔','🌮','🍜','🍣','🥗','🍛','🥙','🍝','🥩'];

function esc(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
const main  = () => document.getElementById('main');
const $     = (id) => document.getElementById(id);
const emoji = (id) => EMOJIS[id % EMOJIS.length];
const nav   = (hash) => { window.location.hash = hash; };

function badge(status) {
    return `<span class="badge badge-${esc(status)}">${esc(status.replace(/_/g,' '))}</span>`;
}

function toast(msg, type = '') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

async function geocode(address) {
    try {
        const res  = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(address)}&format=json&limit=1`, { headers: { 'Accept-Language': 'en' } });
        const data = await res.json();
        if (!data.length) return null;
        return { lat: parseFloat(data[0].lat), lon: parseFloat(data[0].lon) };
    } catch { return null; }
}

function destroyMap() {
    if (_driverMap) { try { _driverMap.remove(); } catch {} _driverMap = null; }
}

async function uploadImage(fileInput) {
    if (!fileInput?.files?.[0]) return null;
    const fd  = new FormData();
    fd.append('image', fileInput.files[0]);
    const res = await fetch('/Szalmaprojekt/api/upload', {
        method:  'POST',
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        body:    fd,
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.message || 'Upload failed');
    return json.data.url;
}

function imgOrEmoji(imageUrl, id, height = '180px') {
    return imageUrl
        ? `<div style="height:${height};background:url('${esc(imageUrl)}') center/cover;border-bottom:var(--border)"></div>`
        : `<div class="rest-emoji">${emoji(id)}</div>`;
}

function imagePreviewInput(existingUrl, fileId, urlId) {
    return `
        <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
            ${existingUrl ? `<img id="current-img" src="${esc(existingUrl)}" style="width:90px;height:70px;object-fit:cover;border-radius:10px;border:2px solid #E8E0D5">` : ''}
            <div style="flex:1;min-width:200px">
                <div style="font-size:.8rem;color:var(--muted);margin-bottom:4px">Upload new image</div>
                <input type="file" id="${fileId}" accept="image/*" class="form-control" style="padding:6px">
                <div style="font-size:.8rem;color:var(--muted);margin:6px 0 4px">Or paste image URL</div>
                <input class="form-control" id="${urlId}" name="image_url" placeholder="https://..." value="${esc(existingUrl || '')}">
            </div>
        </div>
        <div id="img-preview-${fileId}" style="margin-top:8px"></div>
    `;
}

function bindImagePreview(fileId, urlId) {
    const fileEl = $(fileId);
    if (!fileEl) return;
    fileEl.addEventListener('change', () => {
        const file = fileEl.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            $(`img-preview-${fileId}`).innerHTML =
                `<img src="${e.target.result}" style="max-height:120px;border-radius:10px;border:2px solid #E8E0D5">`;
            if ($(urlId)) $(urlId).value = '';
        };
        reader.readAsDataURL(file);
    });
}

// ── Navbar ────────────────────────────────────────────────────────────────────
function renderNavbar() {
    const links = document.getElementById('navbar-links');
    if (!Auth.loggedIn()) {
        links.innerHTML = `
            <a href="#login"    class="btn btn-sm">Login</a>
            <a href="#register" class="btn btn-sm btn-primary">Register</a>
        `;
        return;
    }
    const u = Auth.user();
    const roleLink = {
        customer: `<a href="#orders" class="btn btn-sm">My Orders</a>`,
        admin:    `<a href="#admin"  class="btn btn-sm">Dashboard</a>`,
        driver:   `<a href="#driver" class="btn btn-sm">Deliveries</a>`,
    }[u.role] || '';
    links.innerHTML = `
        <a href="#restaurants" class="btn btn-sm">Restaurants</a>
        ${roleLink}
        <span class="navbar-user">${esc(u.name)} · ${esc(u.role)}</span>
        <button class="btn btn-sm btn-danger" id="btn-logout">Logout</button>
    `;
    $('btn-logout').addEventListener('click', () => {
        Auth.clear(); cartClear(); destroyMap();
        window.location.hash = 'restaurants';
        route();
    });
}

// ── Login ─────────────────────────────────────────────────────────────────────
function renderLogin() {
    main().innerHTML = `
        <div class="auth-wrap"><div class="auth-card">
            <div class="auth-title">Welcome back 👋</div>
            <div class="auth-sub">Sign in to your account</div>
            <div id="auth-err"></div>
            <form id="login-form">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password" required>
                </div>
                <button class="btn btn-primary" style="width:100%">Login</button>
            </form>
            <p style="text-align:center;margin-top:16px;color:var(--muted)">
                No account? <a href="#register" style="color:var(--orange);font-weight:700">Register</a>
            </p>
        </div></div>
    `;
    $('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            const data = await API.post('/auth/login', { email: fd.get('email'), password: fd.get('password') });
            Auth.setSession(data.user, data.token);
            toast('Welcome back!', 'success');
            nav('restaurants');
        } catch (err) {
            $('auth-err').innerHTML = `<div class="alert alert-error">${esc(err.message)}</div>`;
        }
    });
}

// ── Register ──────────────────────────────────────────────────────────────────
function renderRegister() {
    main().innerHTML = `
        <div class="auth-wrap"><div class="auth-card">
            <div class="auth-title">Join FoodieZone 🍕</div>
            <div class="auth-sub">Create your account</div>
            <div id="auth-err"></div>
            <form id="reg-form">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">I am a…</label>
                    <select class="form-control" name="role">
                        <option value="customer">Customer</option>
                        <option value="driver">Driver</option>
                    </select>
                </div>
                <button class="btn btn-primary" style="width:100%">Create Account</button>
            </form>
            <p style="text-align:center;margin-top:16px;color:var(--muted)">
                Have an account? <a href="#login" style="color:var(--orange);font-weight:700">Login</a>
            </p>
        </div></div>
    `;
    $('reg-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            await API.post('/auth/register', { name: fd.get('name'), email: fd.get('email'), password: fd.get('password'), role: fd.get('role') });
            toast('Account created! Please login.', 'success');
            nav('login');
        } catch (err) {
            $('auth-err').innerHTML = `<div class="alert alert-error">${esc(err.message)}</div>`;
        }
    });
}

// ── Restaurant list ───────────────────────────────────────────────────────────
async function renderRestaurants() {
    main().innerHTML = '<div class="loader">Loading restaurants…</div>';
    try {
        const restaurants = await API.get('/restaurants');
        const hero = !Auth.loggedIn() ? `
            <div class="hero">
                <div class="hero-title">Hungry? 🍔<br>We got you.</div>
                <div class="hero-sub">Order from the best restaurants in town.</div>
                <div class="hero-btns">
                    <a href="#login"    class="btn btn-primary">Login</a>
                    <a href="#register" class="btn btn-secondary">Sign up free</a>
                </div>
            </div>
        ` : '';

        if (!restaurants.length) {
            main().innerHTML = hero + `<div class="page"><div class="empty"><div class="empty-icon">🍽️</div><p>No restaurants yet.</p></div></div>`;
            return;
        }

        const cards = restaurants.map(r => `
            <div class="card clickable" onclick="nav('restaurant/${r.id}')">
                ${imgOrEmoji(r.image_url, r.id)}
                <div class="card-body">
                    <div class="card-title">${esc(r.name)}</div>
                    ${r.cuisine ? `<div style="color:var(--orange);font-size:.85rem;font-weight:700;margin-bottom:4px">${esc(r.cuisine)}</div>` : ''}
                    ${r.description ? `<div class="card-text" style="margin-bottom:6px">${esc(r.description)}</div>` : ''}
                    <div class="card-text">📍 ${esc(r.address)}</div>
                    <div class="card-text">📞 ${esc(r.phone)}</div>
                    ${r.opening_hours ? `<div class="card-text">🕐 ${esc(r.opening_hours)}</div>` : ''}
                    <div style="margin-top:12px"><span class="btn btn-primary btn-sm">View Menu →</span></div>
                </div>
            </div>
        `).join('');

        main().innerHTML = `${hero}<div class="page">
            <div class="page-header">
                <div>
                    <div class="page-title">Restaurants</div>
                    <div class="page-subtitle">${restaurants.length} place${restaurants.length !== 1 ? 's' : ''} to eat</div>
                </div>
            </div>
            <div class="grid-3">${cards}</div>
        </div>`;
    } catch (err) {
        main().innerHTML = `<div class="page"><div class="alert alert-error">${esc(err.message)}</div></div>`;
    }
}

// ── Restaurant detail ─────────────────────────────────────────────────────────
async function renderRestaurant(id) {
    main().innerHTML = '<div class="loader">Loading menu…</div>';
    try {
        const [restaurant, items] = await Promise.all([
            API.get(`/restaurants/${id}`),
            API.get(`/restaurants/${id}/menu`),
        ]);
        _menuItems = items;
        _menuRid   = parseInt(id);

        const isCustomer = Auth.is('customer');
        const cartPanel  = isCustomer ? `
            <div class="cart-box" id="cart-box">
                <div class="cart-title">🛒 Your Cart</div>
                <div id="cart-items-list"></div>
                <div id="cart-total-row"></div>
                <button class="btn btn-success" id="btn-place-order" style="width:100%;margin-top:14px" disabled>Place Order</button>
            </div>
        ` : '';

        const heroImg = restaurant.image_url
            ? `<div style="height:260px;background:url('${esc(restaurant.image_url)}') center/cover;border-radius:var(--radius);border:var(--border);box-shadow:var(--shadow);margin-bottom:20px"></div>`
            : '';

        main().innerHTML = `
            <div class="page">
                ${heroImg}
                <div class="page-header">
                    <div>
                        <a href="#restaurants" class="btn btn-sm" style="margin-bottom:8px">← Back</a>
                        <div class="page-title">${emoji(restaurant.id)} ${esc(restaurant.name)}</div>
                        ${restaurant.cuisine ? `<div style="color:var(--orange);font-weight:700;margin-top:2px">${esc(restaurant.cuisine)}</div>` : ''}
                        <div class="page-subtitle" style="margin-top:4px">
                            📍 ${esc(restaurant.address)} · 📞 ${esc(restaurant.phone)}
                            ${restaurant.opening_hours ? ` · 🕐 ${esc(restaurant.opening_hours)}` : ''}
                        </div>
                        ${restaurant.description ? `<p style="margin-top:8px;color:var(--muted);font-size:.95rem;max-width:600px">${esc(restaurant.description)}</p>` : ''}
                    </div>
                </div>
                <div class="rest-layout">
                    <div class="card"><div class="card-body" id="menu-items-container"></div></div>
                    ${cartPanel}
                </div>
            </div>
        `;

        renderMenuItems();
        if (isCustomer) {
            renderCartBox();
            $('btn-place-order')?.addEventListener('click', () => placeOrder(restaurant.id));
        }
    } catch (err) {
        main().innerHTML = `<div class="page"><div class="alert alert-error">${esc(err.message)}</div></div>`;
    }
}

function renderMenuItems() {
    const el = $('menu-items-container');
    if (!el) return;
    const isCustomer = Auth.is('customer');

    if (!_menuItems.length) {
        el.innerHTML = '<p style="color:var(--muted);padding:20px 0">No items on the menu yet.</p>';
        return;
    }

    // Group by category
    const groups = {};
    _menuItems.forEach(item => {
        const cat = item.category || '';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(item);
    });

    el.innerHTML = Object.entries(groups).map(([cat, items]) => `
        ${cat ? `<div style="font-size:.75rem;font-weight:900;color:var(--muted);letter-spacing:.8px;margin:16px 0 8px;text-transform:uppercase">${esc(cat)}</div>` : ''}
        ${items.map(item => {
            const qty = cart.items[item.id]?.qty || 0;
            let ctrl = '';
            if (isCustomer && item.available) {
                ctrl = qty > 0
                    ? `<div class="qty-ctrl">
                           <button class="qty-btn" data-dec="${item.id}">−</button>
                           <span class="qty-num">${qty}</span>
                           <button class="qty-btn" data-inc="${item.id}">+</button>
                       </div>`
                    : `<button class="btn btn-sm btn-primary" data-add="${item.id}">+ Add</button>`;
            }
            return `
                <div class="menu-item">
                    ${item.image_url ? `<img src="${esc(item.image_url)}" style="width:72px;height:72px;object-fit:cover;border-radius:10px;border:2px solid #E8E0D5;flex-shrink:0;margin-right:4px" alt="">` : ''}
                    <div style="flex:1;min-width:0">
                        <div class="item-name">${esc(item.name)}</div>
                        <div class="item-desc">${esc(item.description || '')}</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
                        <span class="item-price">$${parseFloat(item.price).toFixed(2)}</span>
                        ${ctrl}
                        ${!item.available ? '<span class="badge" style="background:#ddd;color:#666">Unavailable</span>' : ''}
                    </div>
                </div>
            `;
        }).join('')}
    `).join('');

    el.querySelectorAll('[data-add]').forEach(btn => {
        btn.addEventListener('click', () => {
            const item = _menuItems.find(i => i.id == btn.dataset.add);
            if (!item) return;
            cartAdd(_menuRid, item); renderMenuItems(); renderCartBox();
        });
    });
    el.querySelectorAll('[data-inc]').forEach(btn => {
        btn.addEventListener('click', () => {
            const item = _menuItems.find(i => i.id == btn.dataset.inc);
            if (!item) return;
            cartAdd(_menuRid, item); renderMenuItems(); renderCartBox();
        });
    });
    el.querySelectorAll('[data-dec]').forEach(btn => {
        btn.addEventListener('click', () => {
            cartChange(parseInt(btn.dataset.dec), -1); renderMenuItems(); renderCartBox();
        });
    });
}

function renderCartBox() {
    const listEl  = $('cart-items-list');
    const totalEl = $('cart-total-row');
    const btn     = $('btn-place-order');
    if (!listEl) return;

    const entries = Object.entries(cart.items);
    if (!entries.length) {
        listEl.innerHTML = '<p style="color:var(--muted);font-size:.9rem;margin-bottom:8px">Add items to get started!</p>';
        if (totalEl) totalEl.innerHTML = '';
        if (btn) btn.disabled = true;
        return;
    }

    listEl.innerHTML = entries.map(([id, it]) => `
        <div class="cart-qty-row">
            <span style="flex:1;font-weight:600">${esc(it.name)}</span>
            <div class="qty-ctrl">
                <button class="qty-btn" data-cart-dec="${id}">−</button>
                <span class="qty-num">${it.qty}</span>
                <button class="qty-btn" data-cart-inc="${id}">+</button>
            </div>
            <span style="min-width:58px;text-align:right;font-weight:800">$${(it.price * it.qty).toFixed(2)}</span>
        </div>
    `).join('');

    listEl.querySelectorAll('[data-cart-inc]').forEach(b => {
        b.addEventListener('click', () => {
            const item = _menuItems.find(i => i.id == b.dataset.cartInc);
            if (item) { cartAdd(_menuRid, item); renderMenuItems(); renderCartBox(); }
        });
    });
    listEl.querySelectorAll('[data-cart-dec]').forEach(b => {
        b.addEventListener('click', () => {
            cartChange(parseInt(b.dataset.cartDec), -1); renderMenuItems(); renderCartBox();
        });
    });

    if (totalEl) totalEl.innerHTML = `<div class="cart-total" style="margin-top:12px"><span>Total</span><span>$${cartTotal().toFixed(2)}</span></div>`;
    if (btn) btn.disabled = false;
}

async function placeOrder(restaurantId) {
    const btn = $('btn-place-order');
    if (btn) btn.disabled = true;
    const items = Object.entries(cart.items).map(([id, it]) => ({ menu_item_id: parseInt(id), quantity: it.qty }));
    try {
        await API.post('/orders', { restaurant_id: parseInt(restaurantId), items });
        toast('Order placed! 🎉', 'success');
        cartClear();
        nav('orders');
    } catch (err) {
        toast(err.message, 'error');
        if (btn) btn.disabled = false;
    }
}

// ── Customer orders ───────────────────────────────────────────────────────────
async function renderOrders() {
    if (!Auth.loggedIn()) { nav('login'); return; }
    main().innerHTML = '<div class="loader">Loading orders…</div>';
    try {
        const orders  = await API.get('/orders');
        const isAdmin = Auth.is('admin');

        if (!orders.length) {
            main().innerHTML = `<div class="page">
                <div class="page-header"><div class="page-title">${isAdmin ? 'All Orders' : 'My Orders'}</div></div>
                <div class="empty"><div class="empty-icon">📦</div>
                <p>No orders yet. <a href="#restaurants" style="color:var(--orange);font-weight:700">Order something!</a></p></div>
            </div>`;
            return;
        }

        if (isAdmin) {
            const rows = orders.map(o => `<tr>
                <td>#${o.id}</td>
                <td>${esc(o.customer_name || '#'+o.customer_id)}</td>
                <td>${esc(o.restaurant_name || '#'+o.restaurant_id)}</td>
                <td>${statusSelect(o.id, o.status)}</td>
                <td>$${parseFloat(o.total_price).toFixed(2)}</td>
                <td>${new Date(o.created_at).toLocaleDateString()}</td>
            </tr>`).join('');

            main().innerHTML = `<div class="page">
                <div class="page-header"><div class="page-title">All Orders</div></div>
                <div class="tbl-wrap"><table>
                    <thead><tr><th>#</th><th>Customer</th><th>Restaurant</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table></div>
            </div>`;

            document.querySelectorAll('[data-status-order]').forEach(sel => {
                sel.addEventListener('change', async () => {
                    const prev = sel.dataset.current;
                    try {
                        await API.put(`/orders/${sel.dataset.statusOrder}/status`, { status: sel.value });
                        sel.dataset.current = sel.value;
                        toast('Status updated', 'success');
                    } catch (err) { toast(err.message, 'error'); sel.value = prev; }
                });
            });
            return;
        }

        // Customer card view
        const cards = orders.map(o => {
            const date = new Date(o.created_at).toLocaleString(undefined, { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
            return `<div class="order-card">
                <div class="order-card-header">
                    <div>
                        <div style="font-weight:900;font-size:1.05rem">${emoji(o.restaurant_id)} ${esc(o.restaurant_name || 'Restaurant')}</div>
                        <div style="font-size:.82rem;color:var(--muted);margin-top:2px">Order #${o.id} · ${date}</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0">
                        ${badge(o.status)}
                        <div style="font-weight:900;font-size:1.15rem;color:var(--orange);margin-top:4px">$${parseFloat(o.total_price).toFixed(2)}</div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="btn btn-sm btn-info" data-detail="${o.id}">View Items</button>
                    ${o.status === 'pending' ? `<button class="btn btn-sm btn-danger" data-cancel="${o.id}">Cancel Order</button>` : ''}
                </div>
                <div id="order-detail-${o.id}" style="display:none;margin-top:12px;border-top:2px dashed #E8E0D5;padding-top:12px">
                    <div class="loader" style="padding:12px">Loading…</div>
                </div>
            </div>`;
        }).join('');

        main().innerHTML = `<div class="page"><div class="page-header"><div class="page-title">My Orders</div></div>${cards}</div>`;

        document.querySelectorAll('[data-detail]').forEach(btn => {
            let loaded = false;
            btn.addEventListener('click', async () => {
                const id  = btn.dataset.detail;
                const box = $(`order-detail-${id}`);
                if (box.style.display === 'block') { box.style.display = 'none'; btn.textContent = 'View Items'; return; }
                box.style.display = 'block';
                btn.textContent = 'Hide Items';
                if (loaded) return;
                loaded = true;
                try {
                    const order = await API.get(`/orders/${id}`);
                    box.innerHTML = order.items.map(i => `
                        <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:.9rem;border-bottom:1px dashed #E8E0D5">
                            <span>${esc(i.item_name)} ×${i.quantity}</span>
                            <span style="font-weight:700">$${(parseFloat(i.unit_price)*i.quantity).toFixed(2)}</span>
                        </div>
                    `).join('');
                } catch (err) { box.innerHTML = `<span style="color:var(--red);font-size:.85rem">${esc(err.message)}</span>`; }
            });
        });

        document.querySelectorAll('[data-cancel]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Cancel this order?')) return;
                try { await API.delete(`/orders/${btn.dataset.cancel}`); toast('Cancelled', 'success'); renderOrders(); }
                catch (err) { toast(err.message, 'error'); }
            });
        });
    } catch (err) {
        main().innerHTML = `<div class="page"><div class="alert alert-error">${esc(err.message)}</div></div>`;
    }
}

function statusSelect(orderId, current) {
    const all  = ['pending','preparing','out_for_delivery','delivered','cancelled'];
    const opts = all.map(s => `<option value="${s}" ${s===current?'selected':''}>${s.replace(/_/g,' ')}</option>`).join('');
    return `<select class="form-control" style="padding:4px 8px;font-size:.8rem;width:auto" data-status-order="${orderId}" data-current="${current}">${opts}</select>`;
}

// ── Driver ────────────────────────────────────────────────────────────────────
async function renderDriver() {
    if (!Auth.is('driver')) { nav('restaurants'); return; }
    destroyMap();
    main().innerHTML = `
        <div class="page">
            <div class="page-header"><div class="page-title">Driver Dashboard 🚗</div></div>
            <div class="driver-layout">
                <div>
                    <div style="font-weight:900;margin-bottom:12px">Active Orders</div>
                    <div id="driver-list"><div class="loader">Loading…</div></div>
                </div>
                <div id="driver-detail">
                    <div class="empty" style="padding:60px 20px">
                        <div class="empty-icon">👈</div>
                        <p>Select an order to view details and map</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    await loadDriverOrders();
}

async function loadDriverOrders(autoSelectId = null) {
    const listEl = $('driver-list');
    if (!listEl) return;
    try {
        const orders = await API.get('/orders');
        if (!orders.length) {
            listEl.innerHTML = '<div class="empty"><div class="empty-icon">✅</div><p>No active orders right now.</p></div>';
            return;
        }
        listEl.innerHTML = orders.map(o => `
            <div class="driver-order-card" id="doc-${o.id}" data-oid="${o.id}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <span style="font-weight:900">Order #${o.id}</span>
                    ${badge(o.status)}
                </div>
                <div style="font-size:.85rem;font-weight:700;margin-bottom:2px">🍽️ ${esc(o.restaurant_name)}</div>
                <div style="font-size:.82rem;color:var(--muted)">📍 ${esc(o.restaurant_address)}</div>
                <div style="font-size:.85rem;font-weight:700;margin-top:6px;color:var(--orange)">$${parseFloat(o.total_price).toFixed(2)}</div>
            </div>
        `).join('');

        listEl.querySelectorAll('[data-oid]').forEach(card => {
            card.addEventListener('click', () => {
                listEl.querySelectorAll('.driver-order-card').forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                showDriverOrder(parseInt(card.dataset.oid));
            });
        });

        const firstId   = autoSelectId ?? orders[0].id;
        const firstCard = listEl.querySelector(`[data-oid="${firstId}"]`);
        if (firstCard) { firstCard.classList.add('active'); showDriverOrder(firstId); }
    } catch (err) {
        listEl.innerHTML = `<div class="alert alert-error">${esc(err.message)}</div>`;
    }
}

async function showDriverOrder(orderId) {
    const detail = $('driver-detail');
    if (!detail) return;
    destroyMap();
    detail.innerHTML = '<div class="loader">Loading order…</div>';
    try {
        const order = await API.get(`/orders/${orderId}`);
        const nextStatus = { preparing: 'out_for_delivery', out_for_delivery: 'delivered' };
        const nextLabel  = { preparing: '🚗 Start Delivery', out_for_delivery: '✅ Mark Delivered' };
        const next       = nextStatus[order.status];

        detail.innerHTML = `
            <div class="card"><div class="card-body">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
                    <div style="font-size:1.3rem;font-weight:900">Order #${order.id}</div>
                    ${badge(order.status)}
                </div>
                <div class="info-grid">
                    <div class="info-box">
                        <div class="info-box-label">PICKUP FROM</div>
                        <div style="font-weight:800;margin-bottom:4px">${esc(order.restaurant_name)}</div>
                        <div style="font-size:.85rem;color:var(--muted)">📍 ${esc(order.restaurant_address)}</div>
                        <div style="font-size:.85rem;color:var(--muted)">📞 ${esc(order.restaurant_phone)}</div>
                    </div>
                    <div class="info-box">
                        <div class="info-box-label">CUSTOMER</div>
                        <div style="font-weight:800;margin-bottom:4px">${esc(order.customer_name)}</div>
                        <div style="font-size:.85rem;color:var(--muted)">✉️ ${esc(order.customer_email)}</div>
                        <div style="font-size:.85rem;font-weight:800;margin-top:8px;color:var(--orange)">Total: $${parseFloat(order.total_price).toFixed(2)}</div>
                    </div>
                </div>
                <div style="margin-bottom:16px">
                    <div style="font-weight:900;margin-bottom:8px">Items</div>
                    ${order.items.map(i => `
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed #E8E0D5;font-size:.9rem">
                            <span>${esc(i.item_name)} ×${i.quantity}</span>
                            <span style="font-weight:700">$${(parseFloat(i.unit_price)*i.quantity).toFixed(2)}</span>
                        </div>
                    `).join('')}
                </div>
                ${next
                    ? `<button class="btn btn-success" id="btn-next-status" style="width:100%;margin-bottom:16px">${nextLabel[order.status]}</button>`
                    : `<div class="alert alert-success" style="margin-bottom:16px">✅ Order delivered!</div>`}
                <div id="order-map" class="map-box"></div>
                <p style="font-size:.8rem;color:var(--muted);text-align:center;margin-top:6px">📍 Restaurant pickup location</p>
            </div></div>
        `;

        if (next) {
            $('btn-next-status').addEventListener('click', async () => {
                try {
                    await API.put(`/orders/${order.id}/status`, { status: next });
                    toast('Status updated!', 'success');
                    await loadDriverOrders(order.id);
                } catch (err) { toast(err.message, 'error'); }
            });
        }

        const mapEl  = $('order-map');
        const coords = await geocode(order.restaurant_address);
        if (coords && mapEl) {
            _driverMap = L.map('order-map').setView([coords.lat, coords.lon], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
            }).addTo(_driverMap);
            L.marker([coords.lat, coords.lon])
                .addTo(_driverMap)
                .bindPopup(`<strong>${order.restaurant_name}</strong><br>📍 ${order.restaurant_address}`)
                .openPopup();
        } else if (mapEl) {
            mapEl.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);font-size:.9rem">Could not locate address on map</div>';
        }
    } catch (err) {
        detail.innerHTML = `<div class="alert alert-error">${esc(err.message)}</div>`;
    }
}

// ── Admin dashboard ───────────────────────────────────────────────────────────
async function renderAdmin(tab = 'restaurants') {
    if (!Auth.is('admin')) { nav('restaurants'); return; }
    main().innerHTML = `
        <div class="page">
            <div class="page-header"><div class="page-title">Admin Dashboard ⚡</div></div>
            <div class="tab-nav">
                <button class="tab-btn ${tab==='restaurants'?'active':''}" onclick="nav('admin/restaurants')">Restaurants</button>
                <button class="tab-btn ${tab==='menu'?'active':''}"        onclick="nav('admin/menu')">Menu Items</button>
                <button class="tab-btn ${tab==='orders'?'active':''}"      onclick="nav('admin/orders')">Orders</button>
                <button class="tab-btn ${tab==='users'?'active':''}"       onclick="nav('admin/users')">Users</button>
            </div>
            <div id="tab-content"><div class="loader">Loading…</div></div>
        </div>
    `;
    if      (tab === 'restaurants') await adminRestaurants();
    else if (tab === 'menu')        await adminMenu();
    else if (tab === 'orders')      await adminOrders();
    else if (tab === 'users')       await adminUsers();
}

// ── Admin: Restaurants ────────────────────────────────────────────────────────
async function adminRestaurants() {
    const el = $('tab-content');
    try {
        const all  = await API.get('/restaurants');
        const mine = all.filter(r => r.admin_id == Auth.id());

        const cards = mine.length
            ? mine.map(r => `
                <div class="card">
                    ${imgOrEmoji(r.image_url, r.id, '140px')}
                    <div class="card-body">
                        <div class="card-title">${esc(r.name)}</div>
                        ${r.cuisine ? `<div style="color:var(--orange);font-size:.82rem;font-weight:700;margin-bottom:4px">${esc(r.cuisine)}</div>` : ''}
                        ${r.description ? `<div class="card-text" style="margin-bottom:4px">${esc(r.description)}</div>` : ''}
                        <div class="card-text">📍 ${esc(r.address)}</div>
                        <div class="card-text">📞 ${esc(r.phone)}</div>
                        ${r.opening_hours ? `<div class="card-text">🕐 ${esc(r.opening_hours)}</div>` : ''}
                        <div style="margin-top:12px;display:flex;gap:8px">
                            <button class="btn btn-sm" data-edit-rest="${r.id}">Edit</button>
                            <button class="btn btn-sm btn-danger" data-del-rest="${r.id}">Delete</button>
                        </div>
                    </div>
                </div>
            `).join('')
            : '<div class="empty"><div class="empty-icon">🏪</div><p>No restaurants yet. Create one!</p></div>';

        el.innerHTML = `
            <div style="margin-bottom:20px">
                <button class="btn btn-primary" id="btn-new-rest">+ New Restaurant</button>
            </div>
            <div class="grid-3">${cards}</div>
        `;

        $('btn-new-rest').addEventListener('click', () => showRestaurantModal(null, adminRestaurants));
        el.querySelectorAll('[data-edit-rest]').forEach(btn => {
            btn.addEventListener('click', () => {
                const r = mine.find(x => x.id == btn.dataset.editRest);
                if (r) showRestaurantModal(r, adminRestaurants);
            });
        });
        el.querySelectorAll('[data-del-rest]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Delete this restaurant and all its menu items?')) return;
                try { await API.delete(`/restaurants/${btn.dataset.delRest}`); toast('Deleted', 'success'); adminRestaurants(); }
                catch (err) { toast(err.message, 'error'); }
            });
        });
    } catch (err) {
        el.innerHTML = `<div class="alert alert-error">${esc(err.message)}</div>`;
    }
}

function showRestaurantModal(existing, onSuccess) {
    const modal = document.createElement('div');
    modal.className = 'modal-bg';
    modal.innerHTML = `
        <div class="modal-box">
            <div class="modal-title">${existing ? 'Edit' : 'New'} Restaurant</div>
            <form id="rest-modal-form">
                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input class="form-control" name="name" value="${esc(existing?.name || '')}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Cuisine type</label>
                    <input class="form-control" name="cuisine" placeholder="e.g. Italian, Fast Food, Asian…" value="${esc(existing?.cuisine || '')}">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2" placeholder="Tell customers what makes you special…">${esc(existing?.description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Address *</label>
                    <input class="form-control" name="address" value="${esc(existing?.address || '')}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone *</label>
                    <input class="form-control" name="phone" value="${esc(existing?.phone || '')}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Opening hours</label>
                    <input class="form-control" name="opening_hours" placeholder="e.g. Mon–Sun: 10:00 – 22:00" value="${esc(existing?.opening_hours || '')}">
                </div>
                <div class="form-group">
                    <label class="form-label">Cover image</label>
                    ${imagePreviewInput(existing?.image_url, 'rest-img-file', 'rest-img-url')}
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" id="btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-modal-submit">${existing ? 'Save Changes' : 'Create Restaurant'}</button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    bindImagePreview('rest-img-file', 'rest-img-url');
    $('btn-modal-cancel').addEventListener('click', () => modal.remove());

    $('rest-modal-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = $('btn-modal-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';
        const fd = new FormData(e.target);
        try {
            let imageUrl = $('rest-img-url')?.value || existing?.image_url || null;
            if ($('rest-img-file')?.files?.[0]) imageUrl = await uploadImage($('rest-img-file'));

            const body = {
                name:          fd.get('name'),
                cuisine:       fd.get('cuisine'),
                description:   fd.get('description'),
                address:       fd.get('address'),
                phone:         fd.get('phone'),
                opening_hours: fd.get('opening_hours'),
                image_url:     imageUrl || null,
            };
            if (existing) {
                await API.put(`/restaurants/${existing.id}`, body);
                toast('Updated!', 'success');
            } else {
                await API.post('/restaurants', body);
                toast('Restaurant created!', 'success');
            }
            modal.remove();
            onSuccess();
        } catch (err) {
            toast(err.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = existing ? 'Save Changes' : 'Create Restaurant';
        }
    });
}

// ── Admin: Menu items ─────────────────────────────────────────────────────────
async function adminMenu() {
    const el = $('tab-content');
    try {
        const all  = await API.get('/restaurants');
        const mine = all.filter(r => r.admin_id == Auth.id());
        if (!mine.length) {
            el.innerHTML = '<div class="empty"><div class="empty-icon">🍽️</div><p>Create a restaurant first.</p></div>';
            return;
        }

        el.innerHTML = `
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
                <select class="form-control" id="menu-rest-sel" style="width:auto">
                    ${mine.map(r => `<option value="${r.id}">${esc(r.name)}</option>`).join('')}
                </select>
                <button class="btn btn-primary btn-sm" id="btn-new-item">+ Add Item</button>
            </div>
            <div id="menu-items-list"></div>
        `;

        async function loadItems() {
            const rid    = $('menu-rest-sel').value;
            const listEl = $('menu-items-list');
            listEl.innerHTML = '<div class="loader">Loading…</div>';
            _menuCache = await API.get(`/restaurants/${rid}/menu`);

            if (!_menuCache.length) {
                listEl.innerHTML = '<div class="empty"><div class="empty-icon">🍽️</div><p>No items yet.</p></div>';
                return;
            }

            listEl.innerHTML = `<div class="card"><div class="card-body">` +
                _menuCache.map((item, i) => `
                    <div class="menu-item">
                        ${item.image_url ? `<img src="${esc(item.image_url)}" style="width:72px;height:72px;object-fit:cover;border-radius:10px;border:2px solid #E8E0D5;flex-shrink:0;margin-right:4px" alt="">` : ''}
                        <div style="flex:1;min-width:0">
                            <div class="item-name">${esc(item.name)}</div>
                            ${item.category ? `<div style="font-size:.75rem;color:var(--orange);font-weight:700">${esc(item.category)}</div>` : ''}
                            <div class="item-desc">${esc(item.description || '')}</div>
                            <div style="margin-top:4px">
                                ${item.available
                                    ? '<span class="badge" style="background:var(--green);color:white">Available</span>'
                                    : '<span class="badge" style="background:#ddd;color:#666">Unavailable</span>'}
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                            <span class="item-price">$${parseFloat(item.price).toFixed(2)}</span>
                            <button class="btn btn-sm" data-edit-idx="${i}">Edit</button>
                            <button class="btn btn-sm btn-danger" data-del-item="${item.id}">×</button>
                        </div>
                    </div>
                `).join('') +
                `</div></div>`;

            listEl.querySelectorAll('[data-edit-idx]').forEach(btn => {
                btn.addEventListener('click', () =>
                    showMenuItemModal(parseInt($('menu-rest-sel').value), _menuCache[parseInt(btn.dataset.editIdx)], loadItems)
                );
            });
            listEl.querySelectorAll('[data-del-item]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm('Delete this item?')) return;
                    try { await API.delete(`/restaurants/${$('menu-rest-sel').value}/menu/${btn.dataset.delItem}`); toast('Deleted', 'success'); loadItems(); }
                    catch (err) { toast(err.message, 'error'); }
                });
            });
        }

        $('menu-rest-sel').addEventListener('change', loadItems);
        $('btn-new-item').addEventListener('click', () => showMenuItemModal(parseInt($('menu-rest-sel').value), null, loadItems));
        loadItems();
    } catch (err) {
        el.innerHTML = `<div class="alert alert-error">${esc(err.message)}</div>`;
    }
}

function showMenuItemModal(restaurantId, existing, onSuccess) {
    const modal = document.createElement('div');
    modal.className = 'modal-bg';
    modal.innerHTML = `
        <div class="modal-box">
            <div class="modal-title">${existing ? 'Edit' : 'Add'} Menu Item</div>
            <form id="item-modal-form">
                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input class="form-control" name="name" value="${esc(existing?.name || '')}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input class="form-control" name="category" placeholder="e.g. Starters, Pizza, Desserts…" value="${esc(existing?.category || '')}">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2">${esc(existing?.description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Price ($) *</label>
                    <input class="form-control" type="number" name="price" step="0.01" min="0" value="${esc(existing?.price || '')}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Available</label>
                    <select class="form-control" name="available">
                        <option value="1" ${!existing || existing.available ? 'selected' : ''}>Yes</option>
                        <option value="0" ${existing && !existing.available  ? 'selected' : ''}>No</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Photo</label>
                    ${imagePreviewInput(existing?.image_url, 'item-img-file', 'item-img-url')}
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" id="btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-modal-submit">${existing ? 'Save Changes' : 'Add Item'}</button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    bindImagePreview('item-img-file', 'item-img-url');
    $('btn-modal-cancel').addEventListener('click', () => modal.remove());

    $('item-modal-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = $('btn-modal-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';
        const fd = new FormData(e.target);
        try {
            let imageUrl = $('item-img-url')?.value || existing?.image_url || null;
            if ($('item-img-file')?.files?.[0]) imageUrl = await uploadImage($('item-img-file'));

            const body = {
                name:        fd.get('name'),
                category:    fd.get('category'),
                description: fd.get('description'),
                price:       parseFloat(fd.get('price')),
                available:   parseInt(fd.get('available')),
                image_url:   imageUrl || null,
            };
            if (existing) {
                await API.put(`/restaurants/${restaurantId}/menu/${existing.id}`, body);
                toast('Updated!', 'success');
            } else {
                await API.post(`/restaurants/${restaurantId}/menu`, body);
                toast('Item added!', 'success');
            }
            modal.remove();
            onSuccess();
        } catch (err) {
            toast(err.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = existing ? 'Save Changes' : 'Add Item';
        }
    });
}

// ── Admin: Orders ─────────────────────────────────────────────────────────────
async function adminOrders() {
    const el = $('tab-content');
    try {
        const orders = await API.get('/orders');
        if (!orders.length) { el.innerHTML = '<div class="empty"><div class="empty-icon">📦</div><p>No orders yet.</p></div>'; return; }
        el.innerHTML = `<div class="tbl-wrap"><table>
            <thead><tr><th>#</th><th>Customer</th><th>Restaurant</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
            <tbody>${orders.map(o => `<tr>
                <td>#${o.id}</td>
                <td>${esc(o.customer_name||'#'+o.customer_id)}</td>
                <td>${esc(o.restaurant_name||'#'+o.restaurant_id)}</td>
                <td>${statusSelect(o.id, o.status)}</td>
                <td>$${parseFloat(o.total_price).toFixed(2)}</td>
                <td>${new Date(o.created_at).toLocaleDateString()}</td>
            </tr>`).join('')}</tbody>
        </table></div>`;
        el.querySelectorAll('[data-status-order]').forEach(sel => {
            sel.addEventListener('change', async () => {
                const prev = sel.dataset.current;
                try { await API.put(`/orders/${sel.dataset.statusOrder}/status`, { status: sel.value }); sel.dataset.current = sel.value; toast('Updated', 'success'); }
                catch (err) { toast(err.message, 'error'); sel.value = prev; }
            });
        });
    } catch (err) { el.innerHTML = `<div class="alert alert-error">${esc(err.message)}</div>`; }
}

// ── Admin: Users ──────────────────────────────────────────────────────────────
async function adminUsers() {
    const el = $('tab-content');
    try {
        const users = await API.get('/users');
        el.innerHTML = `<div class="tbl-wrap"><table>
            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
            <tbody>${users.map(u => {
                const roleColor = u.role==='admin'?'var(--orange)':u.role==='driver'?'var(--blue)':'var(--green)';
                return `<tr>
                    <td>#${u.id}</td><td>${esc(u.name)}</td><td>${esc(u.email)}</td>
                    <td><span class="badge" style="background:${roleColor};color:white">${esc(u.role)}</span></td>
                    <td>${new Date(u.created_at).toLocaleDateString()}</td>
                    <td>${u.id!==Auth.id()
                        ? `<button class="btn btn-sm btn-danger" data-del-user="${u.id}">Delete</button>`
                        : '<span style="color:var(--muted);font-size:.8rem">You</span>'}</td>
                </tr>`;
            }).join('')}</tbody>
        </table></div>`;
        el.querySelectorAll('[data-del-user]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Delete this user? This cannot be undone.')) return;
                try { await API.delete(`/users/${btn.dataset.delUser}`); toast('Deleted', 'success'); adminUsers(); }
                catch (err) { toast(err.message, 'error'); }
            });
        });
    } catch (err) { el.innerHTML = `<div class="alert alert-error">${esc(err.message)}</div>`; }
}

// ── Router ────────────────────────────────────────────────────────────────────
function handleVerifiedParam() {
    const params = new URLSearchParams(window.location.search);
    if (!params.has('verified')) return;
    if (params.get('verified') === '1') {
        toast('Email verified! You can now log in.', 'success');
    } else {
        toast('Invalid or expired verification link.', 'error');
    }
    window.history.replaceState({}, '', window.location.pathname + window.location.hash);
}

function route() {
    Auth.init();
    renderNavbar();
    handleVerifiedParam();
    const hash = window.location.hash.replace(/^#/, '') || 'restaurants';
    const [page, sub] = hash.split('/');
    switch (page) {
        case 'login':      return renderLogin();
        case 'register':   return renderRegister();
        case 'restaurant': return renderRestaurant(sub);
        case 'orders':     return renderOrders();
        case 'admin':      return renderAdmin(sub);
        case 'driver':     return renderDriver();
        default:           return renderRestaurants();
    }
}

window.addEventListener('hashchange', route);
window.addEventListener('DOMContentLoaded', route);
