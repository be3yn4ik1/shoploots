document.addEventListener('DOMContentLoaded', function() {
    var tabs = document.querySelectorAll('.auth-tab');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            tabs.forEach(function(t){ t.classList.remove('active'); });
            document.querySelectorAll('.auth-form').forEach(function(f){ f.classList.remove('active'); });
            tab.classList.add('active');
            var target = document.getElementById(tab.dataset.tab + '-form');
            if (target) target.classList.add('active');
        });
    });

    var ref = new URLSearchParams(window.location.search).get('ref');
    if (ref) {
        var inv = document.getElementById('invite-code');
        if (inv) { inv.value = ref.toUpperCase(); checkInvite(ref); }
        var regTab = document.querySelector('[data-tab=register]');
        if (regTab) regTab.click();
    }

    var inviteInput = document.getElementById('invite-code');
    if (inviteInput) {
        var invTimer;
        inviteInput.addEventListener('input', function() {
            clearTimeout(invTimer);
            var val = this.value.trim();
            if (val.length === 10) {
                invTimer = setTimeout(function(){ checkInvite(val); }, 400);
            } else {
                setInviteStatus('', '');
            }
        });
    }

    function checkInvite(code) {
        var fd = new FormData();
        fd.append('action', 'mkt_check_invite');
        fd.append('nonce', MP.ajaxNonce);
        fd.append('code', code);
        fetch(MP.ajax, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if (res.success && res.data.valid) setInviteStatus('valid', '✓ ' + res.data.name);
                else setInviteStatus('invalid', '✗ Недействителен');
            });
    }

    function setInviteStatus(cls, text) {
        var el = document.getElementById('invite-status');
        if (!el) return;
        el.className = 'invite-status' + (cls ? ' ' + cls : '');
        el.textContent = text;
    }

    document.querySelectorAll('.auth-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var action = form.dataset.action;
            var errEl  = form.querySelector('.form-error');
            var btn    = form.querySelector('[type=submit]');
            errEl.textContent = '';
            mktSetLoading(btn, true);

            var data = {};
            new FormData(form).forEach(function(v, k){ data[k] = v; });

            if (action === 'mkt_register' && MP.recaptchaKey && typeof grecaptcha !== 'undefined') {
                var resp = grecaptcha.getResponse();
                if (!resp) {
                    errEl.textContent = 'Пройдите проверку reCAPTCHA.';
                    mktSetLoading(btn, false);
                    return;
                }
                data.captcha = resp;
            }

            mktAjax(action, data, function(res) {
                mktSetLoading(btn, false);
                if (res.success) {
                    mktToast('Успешно!', 'success');
                    setTimeout(function(){ window.location.href = res.data.redirect; }, 600);
                } else {
                    errEl.textContent = res.data.message || 'Произошла ошибка.';
                    if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
                }
            });
        });
    });
});
