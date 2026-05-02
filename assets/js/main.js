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
            var refCountEl = document.getElementById('ref-count-val');
            if (refCountEl && data.ref_count !== undefined) {
                refCountEl.textContent = data.ref_count;
            }
        });
    }

    var payoutHistoryList = document.getElementById('payout-history-list');
    if (payoutHistoryList) {
        mktRest('my-payouts', 'GET', null, function(data) {
            var statusLabels = {pending: 'Ожидает', completed: 'Выплачено', rejected: 'Отклонено'};
            var statusCls    = {pending: 'status-paid', completed: 'status-done', rejected: 'status-cancel'};
            if (!data.items || !data.items.length) {
                payoutHistoryList.innerHTML = '<div class="orders-empty">Заявок на вывод пока нет.</div>';
                return;
            }
            var html = '<table class="orders-table"><thead><tr><th>Сумма</th><th>Реквизиты</th><th>Статус</th><th>Дата</th></tr></thead><tbody>';
            data.items.forEach(function(w) {
                var lbl = statusLabels[w.status] || w.status;
                var cls = statusCls[w.status] || '';
                html += '<tr>';
                html += '<td style="white-space:nowrap"><strong>' + escHtml(w.amount_fmt) + '</strong></td>';
                html += '<td>' + escHtml(w.method) + '</td>';
                html += '<td><span class="order-status-badge ' + cls + '">' + lbl + '</span></td>';
                html += '<td>' + escHtml(w.date) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            payoutHistoryList.innerHTML = html;
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
            var catSel  = document.getElementById('product-category');
            var typeSel = document.getElementById('product-type');
            res.data.categories.forEach(function(c) {
                catSel.insertAdjacentHTML('beforeend', '<option value="' + c.id + '">' + escHtml(c.name) + '</option>');
            });
            res.data.types.forEach(function(t) {
                typeSel.insertAdjacentHTML('beforeend', '<option value="' + t.id + '">' + escHtml(t.name) + '</option>');
            });
        });

        var deliveryType = document.getElementById('delivery-type');
        if (deliveryType) {
            deliveryType.addEventListener('change', function() {
                var kg = document.getElementById('keys-group');
                if (kg) kg.style.display = this.value === 'auto' ? '' : 'none';
            });
            deliveryType.dispatchEvent(new Event('change'));
        }

        var imgInput = document.getElementById('product-image');
        if (imgInput) {
            imgInput.addEventListener('change', function() {
                var file = this.files[0];
                if (!file) return;
                var reader = new FileReader();
                reader.onload = function(e) {
                    var preview = document.getElementById('product-img-preview');
                    var placeholder = document.getElementById('product-img-placeholder');
                    preview.src = e.target.result;
                    preview.style.display = '';
                    if (placeholder) placeholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            });
        }

        createProductForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = createProductForm.querySelector('[type=submit]');
            var err = document.getElementById('create-product-error');
            err.textContent = '';
            mktSetLoading(btn, true);
            var fd = new FormData(createProductForm);
            fd.append('action', 'mkt_create_product');
            fd.append('nonce', MP.ajaxNonce);
            fetch(MP.ajax, {method: 'POST', body: fd, credentials: 'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    mktSetLoading(btn, false);
                    if (res.success) {
                        mktToast('Товар создан!', 'success');
                        mktModal.close('modal-create-product');
                        createProductForm.reset();
                        var preview = document.getElementById('product-img-preview');
                        var placeholder = document.getElementById('product-img-placeholder');
                        if (preview)     { preview.src = ''; preview.style.display = 'none'; }
                        if (placeholder) placeholder.style.display = '';
                        loadMyProducts();
                    } else {
                        err.textContent = res.data.message || 'Ошибка.';
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
                    mktToast(res.data.message, 'success');
                    setTimeout(function(){ window.location.href = res.data.redirect; }, 800);
                } else {
                    mktToast(res.data.message, 'error');
                    if (res.data.need_deposit) mktModal.open('modal-deposit');
                }
            });
        });
    }

    var saveProductBtn = document.getElementById('save-product-btn');
    if (saveProductBtn) {
        saveProductBtn.addEventListener('click', function() {
            var modal     = document.getElementById('modal-edit-product');
            var id        = modal.querySelector('#edit-product-id').value;
            var title     = modal.querySelector('#edit-product-title').value.trim();
            var price     = modal.querySelector('#edit-product-price').value;
            var priceSale = modal.querySelector('#edit-product-price-sale').value || 0;
            var desc      = modal.querySelector('#edit-product-desc').value.trim();
            var howTo     = modal.querySelector('#edit-product-howto').value.trim();
            var addKeys   = modal.querySelector('#edit-product-keys').value.trim();
            var err       = document.getElementById('edit-product-error');
            if (!title || !price) { err.textContent = 'Заполните название и цену.'; return; }
            err.textContent = '';
            mktSetLoading(saveProductBtn, true);
            var body = {title: title, price: price, price_sale: priceSale, description: desc, how_to: howTo};
            if (addKeys) body.add_keys = addKeys;
            mktRest('product/' + id, 'PUT', body, function(res) {
                mktSetLoading(saveProductBtn, false);
                if (res.message) {
                    mktToast(res.message, 'success');
                    mktModal.close('modal-edit-product');
                    loadMyProducts();
                } else {
                    err.textContent = res.error || 'Ошибка сохранения.';
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
    var html = '<table class="orders-table"><thead><tr><th>Товар</th><th>Сумма</th><th>Статус</th><th>Дата</th><th></th></tr></thead><tbody>';
    items.forEach(function(o) {
        var label = statusMap[o.status] || o.status;
        var cls   = classMap[o.status] || '';
        html += '<tr>';
        html += '<td>' + escHtml(o.product_title) + '</td>';
        html += '<td style="white-space:nowrap"><strong>' + escHtml(o.amount_fmt) + '</strong></td>';
        html += '<td><span class="order-status-badge ' + cls + '">' + label + '</span></td>';
        html += '<td>' + escHtml(o.date) + '</td>';
        var unread = o.unread_count || 0;
        var badge  = unread > 0 ? '<span class="unread-badge">' + unread + '</span>' : '';
        html += '<td><a href="' + o.url + '" class="btn-sm btn-secondary">Открыть' + badge + '</a></td>';
        html += '</tr>';
    });
    html += '</tbody></table>';
    return html;
}

var _myProductsCache = [];

function loadMyProducts() {
    var list = document.getElementById('my-products-list');
    if (!list) return;
    list.innerHTML = '<div class="loader-sm"></div>';
    mktRest('my-products', 'GET', null, function(data) {
        _myProductsCache = data.items || [];
        if (!_myProductsCache.length) {
            list.innerHTML = '<div class="orders-empty">Товаров нет. Создайте первый!</div>';
            return;
        }
        var html = '<div class="products-manage-grid">';
        _myProductsCache.forEach(function(p) {
            html += '<div class="product-manage-row" data-id="' + p.id + '">';
            html += '<div class="product-manage-info">';
            html += '<div class="product-manage-title"><a href="' + escHtml(p.url) + '" target="_blank" class="product-manage-preview-link">' + escHtml(p.title) + '</a></div>';
            html += '<div class="product-manage-meta">';
            html += '<span class="tag ' + (p.delivery === 'auto' ? 'tag-auto' : 'tag-manual') + '">' + (p.delivery === 'auto' ? '⚡ Авто' : '👤 Ручная') + '</span>';
            html += '<span class="tag">' + escHtml(p.amount_fmt || p.price + ' ₽') + '</span>';
            if (p.delivery === 'auto') html += '<span class="product-keys-count">Ключей: <strong>' + p.keys_count + '</strong></span>';
            html += '</div></div>';
            html += '<div class="product-manage-actions">';
            html += '<button class="btn-sm btn-secondary" onclick="mktEditProduct(' + p.id + ')">Редактировать</button>';
            html += '<button class="btn-sm btn-danger" onclick="mktDeleteProduct(' + p.id + ')">Удалить</button>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        list.innerHTML = html;
    });
}

window.mktEditProduct = function(id) {
    var p = null;
    for (var i = 0; i < _myProductsCache.length; i++) {
        if (_myProductsCache[i].id === id) { p = _myProductsCache[i]; break; }
    }
    if (!p) { mktToast('Товар не найден.', 'error'); return; }
    var modal = document.getElementById('modal-edit-product');
    if (!modal) return;
    modal.querySelector('#edit-product-id').value         = p.id;
    modal.querySelector('#edit-product-title').value      = p.title || '';
    modal.querySelector('#edit-product-price').value      = p.price_base || '';
    modal.querySelector('#edit-product-price-sale').value = p.price_sale || 0;
    modal.querySelector('#edit-product-desc').value       = p.description || '';
    modal.querySelector('#edit-product-howto').value      = (p.how_to || '').replace(/<br\s*\/?>/gi, '\n');
    var keysGroup = modal.querySelector('#edit-keys-group');
    if (keysGroup) keysGroup.style.display = p.delivery === 'auto' ? '' : 'none';
    modal.querySelector('#edit-product-keys').value = '';
    document.getElementById('edit-product-error').textContent = '';
    mktModal.open('modal-edit-product');
};


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
