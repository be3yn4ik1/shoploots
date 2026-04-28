document.addEventListener('DOMContentLoaded', function() {
    var chatEl = document.getElementById('chat-messages');
    if (!chatEl) return;

    var orderId   = chatEl.dataset.order;
    var userId    = parseInt(chatEl.dataset.user, 10);
    var lastId    = 0;
    var polling;

    function loadMessages(initial) {
        var url = MP.rest + 'chat/' + orderId + '?since=' + (initial ? 0 : lastId);
        fetch(url, {headers:{'X-WP-Nonce': MP.nonce}, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (!data.messages) return;
                if (initial) chatEl.innerHTML = '';
                data.messages.forEach(function(msg) {
                    appendMessage(msg);
                    lastId = Math.max(lastId, msg.id);
                });
                if (initial || data.messages.length) scrollToBottom();
            });
    }

    function appendMessage(msg) {
        var div = document.createElement('div');
        if (msg.is_system) {
            div.className = 'chat-message system';
            div.innerHTML = '<div class="chat-bubble">' + msg.message + '</div>';
        } else {
            var isOwn = msg.user_id === userId;
            div.className = 'chat-message' + (isOwn ? ' own' : '');
            div.innerHTML =
                '<img class="chat-msg-avatar" src="' + msg.avatar + '" alt="">' +
                '<div>' +
                  '<div class="chat-bubble">' + msg.message + '</div>' +
                  '<div class="chat-msg-meta">' + escHtml(msg.name) + ' · ' + msg.time + '</div>' +
                '</div>';
        }
        chatEl.appendChild(div);
    }

    function scrollToBottom() {
        chatEl.scrollTop = chatEl.scrollHeight;
    }

    loadMessages(true);
    polling = setInterval(function(){ loadMessages(false); }, 3000);

    var sendBtn   = document.getElementById('chat-send-btn');
    var chatInput = document.getElementById('chat-input');

    if (sendBtn && chatInput) {
        function sendMessage() {
            var msg = chatInput.value.trim();
            if (!msg) return;
            sendBtn.disabled = true;
            fetch(MP.rest + 'chat/' + orderId, {
                method: 'POST',
                headers: {'X-WP-Nonce': MP.nonce, 'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({message: msg}),
            })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                sendBtn.disabled = false;
                if (data.id) {
                    appendMessage(data);
                    scrollToBottom();
                    chatInput.value = '';
                    lastId = data.id;
                } else {
                    mktToast(data.error || 'Ошибка.', 'error');
                }
            });
        }
        sendBtn.addEventListener('click', sendMessage);
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }

    var confirmBtn = document.getElementById('confirm-order-btn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            mktSetLoading(confirmBtn, true);
            mktAjax('mkt_confirm_order', {order_id: orderId}, function(res) {
                mktSetLoading(confirmBtn, false);
                if (res.success) {
                    mktToast(res.data.message, 'success');
                    setTimeout(function(){ location.reload(); }, 1000);
                } else {
                    mktToast(res.data.message, 'error');
                }
            });
        });
    }

    var openArbBtn = document.getElementById('open-arb-btn');
    if (openArbBtn) {
        openArbBtn.addEventListener('click', function() {
            var reason = document.getElementById('arb-reason').value.trim();
            var err    = document.getElementById('arb-error');
            if (!reason) { err.textContent = 'Укажите причину.'; return; }
            err.textContent = '';
            mktSetLoading(openArbBtn, true);
            mktAjax('mkt_open_arbitration', {order_id: orderId, reason: reason}, function(res) {
                mktSetLoading(openArbBtn, false);
                if (res.success) {
                    mktToast(res.data.message, 'success');
                    mktModal.close('modal-arbitration');
                    setTimeout(function(){ location.reload(); }, 1000);
                } else {
                    err.textContent = res.data.message;
                }
            });
        });
    }

    window.addEventListener('beforeunload', function(){ clearInterval(polling); });
});
