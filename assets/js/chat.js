document.addEventListener('DOMContentLoaded', function () {
    var chatEl = document.getElementById('chat-messages');
    if (!chatEl) return;

    var orderId = chatEl.dataset.order;
    var userId  = parseInt(chatEl.dataset.user, 10);
    var lastId  = 0;
    var polling;
    var currentRating = 5;

    function loadMessages(initial) {
        var url = MP.rest + 'chat/' + orderId + '?since=' + (initial ? 0 : lastId);
        fetch(url, {headers: {'X-WP-Nonce': MP.nonce}, credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.messages) return;
                if (initial) chatEl.innerHTML = '';
                data.messages.forEach(function (msg) {
                    appendMessage(msg);
                    if (msg.id > lastId) lastId = msg.id;
                });
                if (initial || data.messages.length) scrollToBottom();
                if (lastId > 0) markRead(lastId);
            });
    }

    function markRead(msgId) {
        if (!MP.ajaxNonce || !userId) return;
        var fd = new FormData();
        fd.append('action',   'mkt_mark_read');
        fd.append('nonce',    MP.ajaxNonce);
        fd.append('order_id', orderId);
        fd.append('msg_id',   msgId);
        fetch(MP.ajax, {method: 'POST', body: fd, credentials: 'same-origin'});
    }

    function appendMessage(msg) {
        var div = document.createElement('div');
        if (msg.is_system) {
            div.className = 'chat-message system';
            div.innerHTML = '<div class="chat-bubble">' + msg.message + '</div>';
        } else {
            var isOwn      = msg.user_id === userId;
            var adminBadge = msg.is_admin ? '<span class="chat-admin-badge">Админ</span> ' : '';
            div.className  = 'chat-message' + (isOwn ? ' own' : '');
            div.innerHTML  =
                '<img class="chat-msg-avatar" src="' + escHtml(msg.avatar || '') + '" alt="">' +
                '<div>' +
                    '<div class="chat-bubble">' + msg.message + '</div>' +
                    '<div class="chat-msg-meta">' + adminBadge + escHtml(msg.name) + ' · ' + escHtml(msg.time) + '</div>' +
                '</div>';
        }
        chatEl.appendChild(div);
    }

    function scrollToBottom() {
        chatEl.scrollTop = chatEl.scrollHeight;
    }

    loadMessages(true);
    polling = setInterval(function () { loadMessages(false); }, 3000);

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
            .then(function (r) { return r.json(); })
            .then(function (data) {
                sendBtn.disabled = false;
                if (data.id) {
                    appendMessage(data);
                    scrollToBottom();
                    chatInput.value = '';
                    lastId = data.id;
                    markRead(data.id);
                } else {
                    mktToast(data.error || 'Ошибка отправки.', 'error');
                }
            });
        }
        sendBtn.addEventListener('click', sendMessage);
        chatInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });
    }

    var confirmBtn = document.getElementById('confirm-order-btn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (!confirm('Подтвердить получение товара? Средства будут переведены продавцу.')) return;
            mktSetLoading(confirmBtn, true);
            mktAjax('mkt_confirm_order', {order_id: orderId}, function (res) {
                mktSetLoading(confirmBtn, false);
                if (res.success) {
                    mktToast(res.data.message, 'success');
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    mktToast(res.data.message, 'error');
                }
            });
        });
    }

    var openArbBtn = document.getElementById('open-arb-btn');
    if (openArbBtn) {
        openArbBtn.addEventListener('click', function () {
            var reason = document.getElementById('arb-reason').value.trim();
            var err    = document.getElementById('arb-error');
            if (!reason) { err.textContent = 'Укажите причину.'; return; }
            err.textContent = '';
            mktSetLoading(openArbBtn, true);
            mktAjax('mkt_open_arbitration', {order_id: orderId, reason: reason}, function (res) {
                mktSetLoading(openArbBtn, false);
                if (res.success) {
                    mktToast(res.data.message, 'success');
                    mktModal.close('modal-arbitration');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    err.textContent = res.data.message;
                }
            });
        });
    }

    var adminConfirmBtn = document.getElementById('admin-confirm-btn');
    if (adminConfirmBtn) {
        adminConfirmBtn.addEventListener('click', function () {
            if (!confirm('Подтвердить заказ? Средства будут переведены продавцу.')) return;
            mktSetLoading(adminConfirmBtn, true);
            mktAjax('mkt_admin_confirm_order', {order_id: orderId}, function (res) {
                mktSetLoading(adminConfirmBtn, false);
                if (res.success) {
                    mktToast(res.data.message, 'success');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    mktToast(res.data.message, 'error');
                }
            });
        });
    }

    var adminCancelBtn = document.getElementById('admin-cancel-btn');
    if (adminCancelBtn) {
        adminCancelBtn.addEventListener('click', function () {
            if (!confirm('Отменить заказ? Средства будут возвращены покупателю.')) return;
            mktSetLoading(adminCancelBtn, true);
            mktAjax('mkt_admin_cancel_order', {order_id: orderId}, function (res) {
                mktSetLoading(adminCancelBtn, false);
                if (res.success) {
                    mktToast(res.data.message, 'success');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    mktToast(res.data.message, 'error');
                }
            });
        });
    }

    var starRating = document.getElementById('star-rating');
    if (starRating) {
        var stars = starRating.querySelectorAll('.star');
        function updateStars(val) {
            stars.forEach(function (s) {
                s.classList.toggle('active', parseInt(s.dataset.val) <= val);
            });
        }
        stars.forEach(function (star) {
            star.addEventListener('click', function () {
                currentRating = parseInt(star.dataset.val);
                updateStars(currentRating);
            });
            star.addEventListener('mouseenter', function () {
                updateStars(parseInt(star.dataset.val));
            });
        });
        starRating.addEventListener('mouseleave', function () {
            updateStars(currentRating);
        });
    }

    var submitReviewBtn = document.getElementById('submit-review-btn');
    if (submitReviewBtn) {
        submitReviewBtn.addEventListener('click', function () {
            var text = document.getElementById('review-text').value.trim();
            var err  = document.getElementById('review-error');
            if (!text) { err.textContent = 'Напишите текст отзыва.'; return; }
            err.textContent = '';
            mktSetLoading(submitReviewBtn, true);
            mktAjax('mkt_submit_review', {
                order_id: submitReviewBtn.dataset.order,
                rating:   currentRating,
                text:     text,
            }, function (res) {
                mktSetLoading(submitReviewBtn, false);
                if (res.success) {
                    mktToast(res.data.message, 'success');
                    var card = submitReviewBtn.closest('.order-review-card');
                    if (card) {
                        card.className = 'card order-review-done';
                        card.innerHTML = '<svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Отзыв отправлен. Спасибо!';
                    }
                } else {
                    err.textContent = res.data.message;
                }
            });
        });
    }

    window.addEventListener('beforeunload', function () { clearInterval(polling); });
});
