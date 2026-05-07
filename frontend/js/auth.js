const Auth = (() => {
    let _user = null;

    function init() {
        try { _user = JSON.parse(localStorage.getItem('user')); } catch { _user = null; }
    }

    function setSession(user, token) {
        _user = user;
        localStorage.setItem('token', token);
        localStorage.setItem('user', JSON.stringify(user));
    }

    function clear() {
        _user = null;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
    }

    return {
        init,
        setSession,
        clear,
        user:     () => _user,
        loggedIn: () => !!_user,
        role:     () => _user?.role ?? null,
        is:       (r) => _user?.role === r,
        id:       () => _user?.id ?? null,
    };
})();
