const API = (() => {
    const BASE = '/Szalmaprojekt/api';

    async function req(method, path, body) {
        const opts = { method, headers: { 'Content-Type': 'application/json' } };
        const tok = localStorage.getItem('token');
        if (tok) opts.headers.Authorization = `Bearer ${tok}`;
        if (body != null) opts.body = JSON.stringify(body);

        const res = await fetch(BASE + path, opts);
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || `HTTP ${res.status}`);
        return json.data;
    }

    return {
        get:    (p)    => req('GET',    p),
        post:   (p, b) => req('POST',   p, b),
        put:    (p, b) => req('PUT',    p, b),
        delete: (p)    => req('DELETE', p),
    };
})();
