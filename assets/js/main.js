var MP = MP || {};

window.mktAjax = function(action, data, cb) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', MP.ajaxNonce);
    for (var k in data) if (Object.prototype.hasOwnProperty.call(data, k)) fd.append(k, data[k]);
    fetch(MP.ajax, {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(cb)
        .catch(function(){ mktToast('Ошибка сети. Попробуйте снова.', 'error'); });
};

window.mktRest = function(path, method, data, cb) {
    var opts = {
        method: method || 'GET',
        headers: {'X-WP-Nonce': MP.nonce, 'Content-Type': 'application/json'},
        credentials: 'same-origin',
    };
    if (data && method !== 'GET') opts.body = JSON.stringify(data);
    fetch(MP.rest + path, opts)
        .then(function(r){ return r.json(); })
        .then(cb)
        .catch(function(){ mktToast('Ошибка сети.', 'error'); });
};

window.mktSetLoading = function(btn, loading) {
    if (loading) {
        btn.dataset.origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '...';
    } else {
        btn.disabled = false;
        btn.textContent = btn.dataset.origText || btn.textContent;
    }
};

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.dash-nav-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            var section = item.dataset.section;
            document.querySelectorAll('.dash-nav-item').forEach(function(i){ i.classList.remove('active'); });
            document.querySelectorAll('.dash-section').forEach(function(s){ s.classList.remove('active'); });
            item.classList.add('active');
            var sec = document.getElementById('section-' + section);
            if (sec) sec.classList.add('active');
        });
    });

    var catalogSection = document.getElementById('section-catalog');
    if (typeof initCatalog === 'function') initCatalog();

    var statsGrid = document.getElementById('stats-grid');
    if (statsGrid) {
        mktRest('stats', 'GET', null, function(data) {
            document.querySelectorAll('[data-stat]').forEach(function(el) {
                var key = el.dataset.stat;
                if (data[key] !== undefined) {
                    el.textContent = key.indexOf('earned') !== -1
                        ? parseFloat(data[key]).toFixed(2) + ' ₽'
                        : data[key];
                }
            });
        });
    }

    var recentList = document.getElementById('recent-orders-list');
    if (recentList) {
        mktRest('orders?page=1', 'GET', null, function(data) {
            if (!data.items || !data.items.length) {
                recentList.innerHTML = '<div class="orders-empty">Заказов пока нет</div>';
                return;
            }
            recentList.innerHTML = renderOrdersTable(data.items.slice(0, 5));
        });
    }

    var ordersList = document.getElementById('orders-list');
    if (ordersList) {
        mktRest('orders', 'GET', null, function(data) {
            if (!data.items || !data.items.length) {
                ordersList.innerHTML = '<div class="orders-empty">Заказов пока нет</div>';
                return;
            }
            ordersList.innerHTML = renderOrdersTable(data.items);
        });
    }

    var myProductsList = document.getElementById('my-products-list');
    if (myProductsList) loadMyProducts();

    var saveCardBtn = document.getElementById('save-card-btn');
    if (saveCardBtn) {
        saveCardBtn.addEventListener('click', function() {
            var card = document.getElementById('payout-card').value.trim();
            if (!card) { mktToast('Введите реквизиты.', 'error'); return; }
            mktSetLoading(saveCardBtn, true);
            mktAjax('mkt_save_card', {card: card}, function(res) {
                mktSetLoading(saveCardBtn, false);
                if (res.success) mktToast(res.data.message, 'success');
                else mktToast(res.data.message, 'error');
            });
        });
    }

    var payoutBtn = document.getElementById('payout-btn');
    if (payoutBtn) {
        payoutBtn.addEventListener('click', function() {
            var amount = parseFloat(document.getElementById('payout-amount').value || 0);
            var card   = document.getElementById('payout-card').value.trim();
            if (!amount) { mktToast('Введите сумму.', 'error'); return; }
            mktSetLoading(payoutBtn, true);
            mktAjax('mkt_request_payout', {amount: amount, card: card}, function(res) {
                mktSetLoading(payoutBtn, false);
                if (res.success) {
                    mktToast(res.data.message, 'success');
                    document.getElementById('payout-amount').value = '';
                } else {
                    mktToast(res.data.message, 'error');
                }
            });
        });
    }

    var depositBtn = document.getElementById('deposit-btn');
    if (depositBtn) {
        depositBtn.addEventListener('click', function() {
            var amount = parseFloat(document.getElementById('deposit-amount').value || 0);
            if (!amount) { mktToast('Введите сумму.', 'error'); return; }
            mktSetLoading(depositBtn, true);
            mktAjax('mkt_get_deposit_url', {amount: amount}, function(res) {
                mktSetLoading(depositBtn, false);
                if (res.success) window.location.href = res.data.url;
                else mktToast(res.data.message, 'error');
            });
        });
        document.querySelectorAll('.preset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('deposit-amount').value = btn.dataset.amount;
                document.querySelectorAll('.preset-btn').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
            });
        });
    }

    var profileForm = document.getElementById('profile-form');
    if (profileForm) {
        document.getElementById('avatar-file').addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function(e){ document.getElementById('avatar-preview-profile').src = e.target.result; };
            reader.readAsDataURL(file);
        });
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var fd = new FormData(profileForm);
            fd.append('action', 'mkt_update_profile');
            fd.append('nonce', MP.ajaxNonce);
            var btn = profileForm.querySelector('[type=submit]');
            mktSetLoading(btn, true);
            fetch(MP.ajax, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    mktSetLoading(btn, false);
                    if (res.success) mktToast(res.data.message, 'success');
                    else {
                        document.getElementById('profile-error').textContent = res.data.message;
                        mktToast(res.data.message, 'error');
                    }
                });
        });
    }

    var createProductForm = document.getElementById('create-product-form');
    if (createProductForm) {
        mktAjax('mkt_get_categories', {}, function(res) {
            if (!res.success) return;
            var catSel = document.getElementById('product-category');
            var typeSel = document.getElementById('product-type');
            res.data.categories.forEach(function(c) {
                catSel.insertAdjacentHTML('beforeend', '<option value="' + c.id + '">' + c.name + '</option>');
            });
            res.data.types.forEach(function(t) {
                typeSel.insertAdjacentHTML('beforeend', '<option value="' + t.id + '">' + t.name + '</option>');
            });
        });

        document.getElementById('delivery-type').addEventListener('change', function() {
            var kg = document.getElementById('keys-group');
            if (kg) kg.style.display = this.value === 'auto' ? '' : 'none';
        });

        createProductForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = createProductForm.querySelector('[type=submit]');
            var err = document.getElementById('create-product-error');
            err.textContent = '';
            var data = {};
            new FormData(createProductForm).forEach(function(v, k){ data[k] = v; });
            mktSetLoading(btn, true);
            mktRest('product', 'POST', data, function(res) {
                mktSetLoading(btn, false);
                if (res.id) {
                    mktToast('Товар создан!', 'success');
                    mktModal.close('modal-create-product');
                    loadMyProducts();
                } else {
                    err.textContent = res.error || 'Ошибка.';
                }
            });
        });
    }

    var buyBtn = document.getElementById('buy-btn');
    if (buyBtn) {
        buyBtn.addEventListener('click', function() {
            mktModal.open('modal-buy-confirm');
        });
    }
    var confirmBuyBtn = document.getElementById('confirm-buy-btn');
    if (confirmBuyBtn) {
        confirmBuyBtn.addEventListener('click', function() {
            var product_id = confirmBuyBtn.dataset.product;
            mktSetLoading(confirmBuyBtn, true);
            mktAjax('mkt_buy', {product_id: product_id}, function(res) {
                mktSetLoading(confirmBuyBtn, false);
                mktModal.close('modal-buy-confirm');
                if (res.success) {
                    if (res.data.type === 'auto') {
                        document.getElementById('auto-key-value').textContent = res.data.key;
                        document.getElementById('copy-key-btn').dataset.copy = res.data.key;
                        mktModal.open('modal-auto-key');
                    } else {
                        mktToast(res.data.message, 'success');
                        setTimeout(function(){ window.location.href = res.data.redirect; }, 800);
                    }
                } else {
                    mktToast(res.data.message, 'error');
                    if (res.data.need_deposit) mktModal.open('modal-deposit');
                }
            });
        });
    }

    var logoutBtns = document.querySelectorAll('[data-action=logout]');
    logoutBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            mktAjax('mkt_logout', {}, function(res) {
                if (res.success) window.location.href = res.data.redirect;
            });
        });
    });
});

function renderOrdersTable(items) {
    var statusMap = {
        created:'Создан', paid:'Оплачен', in_progress:'В процессе',
        completed:'Завершён', arbitration:'Арбитраж', canceled:'Отменён'
    };
    var classMap = {
        created:'status-created', paid:'status-paid', in_progress:'status-progress',
        completed:'status-done', arbitration:'status-arb', canceled:'status-cancel'
    };
    var html = '<table class="orders-table"><thead><tr><th>#</th><th>Товар</th><th>Сумма</th><th>Статус</th><th>Дата</th><th></th></tr></thead><tbody>';
    items.forEach(function(o) {
        var label = statusMap[o.status] || o.status;
        var cls   = classMap[o.status] || '';
        html += '<tr>';
        html += '<td>' + o.id + '</td>';
        html += '<td>' + escHtml(o.product_title) + '</td>';
        html += '<td><strong>' + escHtml(o.amount_fmt) + '</strong></td>';
        html += '<td><span class="order-status-badge ' + cls + '">' + label + '</span></td>';
        html += '<td>' + escHtml(o.date) + '</td>';
        html += '<td><a href="' + o.url + '" class="btn-sm btn-secondary">Открыть</a></td>';
        html += '</tr>';
    });
    html += '</tbody></table>';
    return html;
}

function loadMyProducts() {
    var list = document.getElementById('my-products-list');
    if (!list) return;
    list.innerHTML = '<div class="loader-sm"></div>';
    mktRest('my-products', 'GET', null, function(data) {
        if (!data.items || !data.items.length) {
            list.innerHTML = '<div class="orders-empty">Товаров нет. Создайте первый!</div>';
            return;
        }
        var html = '<div class="products-manage-grid">';
        data.items.forEach(function(p) {
            html += '<div class="product-manage-row" data-id="' + p.id + '">';
            html += '<div class="product-manage-info">';
            html += '<div class="product-manage-title">' + escHtml(p.title) + '</div>';
            html += '<div class="product-manage-meta">';
            html += '<span class="tag ' + (p.delivery === 'auto' ? 'tag-auto' : 'tag-manual') + '">' + (p.delivery === 'auto' ? '⚡ Авто' : '👤 Ручная') + '</span>';
            html += '<span class="tag">' + escHtml(p.amount_fmt || p.price + ' ₽') + '</span>';
            if (p.delivery === 'auto') html += '<span class="product-keys-count">Ключей: <strong>' + p.keys_count + '</strong></span>';
            html += '</div></div>';
            html += '<div class="product-manage-actions">';
            html += '<a href="' + p.url + '" class="btn-sm btn-secondary" target="_blank">Просмотр</a>';
            html += '<button class="btn-sm btn-danger" onclick="mktDeleteProduct(' + p.id + ')">Удалить</button>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        list.innerHTML = html;
    });
}

window.mktDeleteProduct = function(id) {
    if (!confirm('Удалить товар?')) return;
    mktRest('product/' + id, 'DELETE', null, function(res) {
        if (res.message) {
            mktToast(res.message, 'success');
            loadMyProducts();
        } else {
            mktToast(res.error || 'Ошибка.', 'error');
        }
    });
};

function escHtml(str) {
    if (typeof str !== 'string') return String(str);
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
window.escHtml = escHtml;
