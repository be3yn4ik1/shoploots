(function(){
    var container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);

    window.mktToast = function(msg, type) {
        type = type || 'info';
        var t = document.createElement('div');
        t.className = 'toast toast-' + type;
        t.textContent = msg;
        container.appendChild(t);
        setTimeout(function(){
            t.classList.add('toast-out');
            setTimeout(function(){ container.removeChild(t); }, 300);
        }, 3500);
    };

    window.mktModal = {
        open: function(id) {
            var m = document.getElementById(id);
            if (m) m.classList.add('open');
        },
        close: function(id) {
            var m = document.getElementById(id);
            if (m) m.classList.remove('open');
        }
    };

    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-modal]');
        if (trigger) {
            e.preventDefault();
            mktModal.open(trigger.dataset.modal);
        }
        var closer = e.target.closest('[data-close]');
        if (closer) {
            e.preventDefault();
            mktModal.close(closer.dataset.close);
        }
        var overlay = e.target;
        if (overlay.classList.contains('modal-overlay') && overlay.classList.contains('open')) {
            overlay.classList.remove('open');
        }
        var copyBtn = e.target.closest('.copy-btn');
        if (copyBtn) {
            var text = copyBtn.dataset.copy || '';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function(){ mktToast('Скопировано!', 'success'); });
            } else {
                var tmp = document.createElement('textarea');
                tmp.value = text;
                document.body.appendChild(tmp);
                tmp.select();
                document.execCommand('copy');
                document.body.removeChild(tmp);
                mktToast('Скопировано!', 'success');
            }
        }
        var eyeBtn = e.target.closest('.eye-btn');
        if (eyeBtn) {
            var inputName = eyeBtn.dataset.target;
            var input = eyeBtn.closest('.input-eye').querySelector('input');
            if (input) input.type = input.type === 'password' ? 'text' : 'password';
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function(m){ m.classList.remove('open'); });
        }
    });
})();
