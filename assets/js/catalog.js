document.addEventListener('DOMContentLoaded', function () {
    initSellerGrid();
});

function initSellerGrid() {
    var sellerGrid = document.getElementById('seller-products-grid');
    if (!sellerGrid) return;
    var sellerId   = sellerGrid.dataset.seller;
    var paginEl    = document.getElementById('seller-pagination');
    var countEl    = document.getElementById('seller-product-count');
    var page = 1;

    function load() {
        sellerGrid.innerHTML = '<div class="loader-sm" style="grid-column:1/-1"></div>';
        mktRest('seller/' + sellerId + '/products?page=' + page, 'GET', null, function (data) {
            sellerGrid.innerHTML = '';
            if (!data.items || !data.items.length) {
                sellerGrid.innerHTML = '<p style="grid-column:1/-1;color:var(--text-secondary);padding:40px 0;text-align:center">Товаров нет</p>';
                return;
            }
            if (countEl) countEl.textContent = 'Всего: ' + data.total;
            data.items.forEach(function (p) {
                sellerGrid.insertAdjacentHTML('beforeend', renderProductCard(p));
            });
            renderSellerPagination(data.total_pages, data.page, paginEl, function (n) { page = n; load(); });
        });
    }
    load();
}

function renderSellerPagination(total, cur, paginEl, cb) {
    if (!paginEl) return;
    paginEl.innerHTML = '';
    if (total <= 1) { paginEl.style.display = 'none'; return; }
    paginEl.style.display = '';
    for (var i = 1; i <= total; i++) {
        (function (n) {
            var btn = document.createElement('button');
            btn.textContent = n;
            if (n === cur) btn.classList.add('active');
            btn.addEventListener('click', function () { cb(n); window.scrollTo({ top: 0, behavior: 'smooth' }); });
            paginEl.appendChild(btn);
        })(i);
    }
}

function renderStars(avg, count) {
    if (!count) return '';
    var html = '<div class="product-grid-rating">';
    for (var i = 1; i <= 5; i++) {
        html += '<span style="color:' + (i <= Math.round(avg) ? '#0077ff' : '#d1d5db') + ';font-size:.85rem">★</span>';
    }
    html += '<span class="product-grid-rating-meta">' + parseFloat(avg).toFixed(1) + ' (' + count + ')</span></div>';
    return html;
}

function renderProductCard(p) {
    var priceHtml = p.price_sale > 0
        ? '<span class="p-old">' + p.price_base + ' ₽</span><span class="p-new">' + p.price_sale + ' ₽</span>'
        : '<span class="p-main">' + p.price + ' ₽</span>';
    var thumb = p.thumbnail
        ? '<img src="' + p.thumbnail + '" alt="" loading="lazy">'
        : '<div style="width:100%;height:100%;background:#e8eaed;display:flex;align-items:center;justify-content:center;font-size:2rem">📦</div>';
    var dTag = p.delivery === 'auto'
        ? '<span class="tag tag-auto">⚡ Авто</span>'
        : '<span class="tag tag-manual">👤 Ручная</span>';
    var sTag = p.delivery === 'auto'
        ? (p.in_stock ? '<span class="tag tag-stock">' + p.keys_count + ' шт</span>' : '<span class="tag tag-out">Нет</span>')
        : '';
    var ratingHtml = renderStars(p.rating_avg || 0, p.reviews_count || 0);
    return '<a href="' + p.url + '" class="product-grid-card">' +
        '<div class="product-grid-img">' + thumb + '</div>' +
        '<div class="product-grid-body">' +
            '<div class="product-grid-title">' + escHtml(p.title) + '</div>' +
            '<div class="product-grid-tags">' + dTag + sTag + '</div>' +
            '<div class="product-grid-price">' + priceHtml + '</div>' +
            ratingHtml +
        '</div>' +
        '<div class="product-grid-footer">' +
            '<span class="user-mini" style="font-size:.78rem">' +
                '<img src="' + escHtml(p.seller_avatar) + '" width="18" style="border-radius:50%;height:18px;object-fit:cover"> ' +
                escHtml(p.seller_name) +
            '</span>' +
        '</div>' +
    '</a>';
}

document.addEventListener('DOMContentLoaded', function () {
    var grid    = document.getElementById('products-grid');
    if (!grid) return;

    var catSel   = document.getElementById('filter-category');
    var typeSel  = document.getElementById('filter-type');
    var searchEl = document.getElementById('catalog-search');
    var sortEl   = document.getElementById('catalog-sort');
    var paginEl  = document.getElementById('catalog-pagination');
    var countEl  = document.getElementById('catalog-count');
    var applyBtn = document.getElementById('apply-filters');
    var resetBtn = document.getElementById('reset-filters');

    var page = 1;
    var searchTimer;

    mktAjax('mkt_get_categories', {}, function (res) {
        if (!res.success) return;
        res.data.categories.forEach(function (c) {
            if (catSel) catSel.insertAdjacentHTML('beforeend', '<option value="' + escHtml(c.slug) + '">' + escHtml(c.name) + '</option>');
        });
        res.data.types.forEach(function (t) {
            if (typeSel) typeSel.insertAdjacentHTML('beforeend', '<option value="' + escHtml(t.slug) + '">' + escHtml(t.name) + '</option>');
        });
    });

    function load() {
        grid.innerHTML = '<div class="loader-sm" style="grid-column:1/-1"></div>';
        var q = 'products?page=' + page
            + '&category=' + encodeURIComponent(catSel  ? catSel.value         : '')
            + '&type='     + encodeURIComponent(typeSel ? typeSel.value        : '')
            + '&search='   + encodeURIComponent(searchEl ? searchEl.value.trim() : '');

        mktRest(q, 'GET', null, function (data) {
            if (!data.items || !data.items.length) {
                grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--text-secondary)">Товаров не найдено</p>';
                if (paginEl) paginEl.innerHTML = '';
                if (countEl) countEl.textContent = '';
                return;
            }
            renderProducts(data.items);
            if (countEl) countEl.textContent = 'Найдено: ' + data.total;
            renderPagination(data.total_pages, data.page);
        });
    }

    function renderProducts(items) {
        grid.innerHTML = '';
        items.forEach(function (p) {
            grid.insertAdjacentHTML('beforeend', renderProductCard(p));
        });
    }

    function renderPagination(total, cur) {
        if (!paginEl) return;
        paginEl.innerHTML = '';
        if (total <= 1) { paginEl.style.display = 'none'; return; }
        paginEl.style.display = '';
        for (var i = 1; i <= total; i++) {
            (function (n) {
                var btn = document.createElement('button');
                btn.textContent = n;
                if (n === cur) btn.classList.add('active');
                btn.addEventListener('click', function () {
                    page = n;
                    load();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
                paginEl.appendChild(btn);
            })(i);
        }
    }

    if (applyBtn) applyBtn.addEventListener('click', function () { page = 1; load(); });
    if (resetBtn) resetBtn.addEventListener('click', function () {
        if (catSel)   catSel.value   = '';
        if (typeSel)  typeSel.value  = '';
        if (searchEl) searchEl.value = '';
        if (sortEl)   sortEl.value   = 'date';
        page = 1;
        load();
    });
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { page = 1; load(); }, 500);
        });
    }

    load();
});
