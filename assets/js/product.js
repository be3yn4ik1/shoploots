document.addEventListener('DOMContentLoaded', function () {
    if (typeof MKT_PRODUCT === 'undefined') return;

    var product = MKT_PRODUCT;

    trackRecentlyViewed(product);
    renderRecentlyViewed(product.id);
    loadSimilarProducts(product.category, product.id);
    loadReviews(false);

    var loadMoreBtn = document.getElementById('load-more-reviews');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function () {
            reviewPage++;
            loadReviews(true);
        });
    }
});

var reviewPage = 1;
var reviewTotal = 0;

function trackRecentlyViewed(product) {
    try {
        var list = JSON.parse(localStorage.getItem('mkt_viewed') || '[]');
        list = list.filter(function (p) { return p.id !== product.id; });
        list.unshift({ id: product.id, title: product.title, price: product.price, thumbnail: product.thumbnail, url: product.url });
        list = list.slice(0, 13);
        localStorage.setItem('mkt_viewed', JSON.stringify(list));
    } catch (e) {}
}

function renderRecentlyViewed(currentId) {
    var section = document.getElementById('recently-viewed-section');
    var grid    = document.getElementById('recently-viewed-grid');
    if (!section || !grid) return;
    try {
        var list = JSON.parse(localStorage.getItem('mkt_viewed') || '[]');
        var filtered = list.filter(function (p) { return p.id !== currentId; }).slice(0, 12);
        if (!filtered.length) return;
        section.style.display = '';
        filtered.forEach(function (p) {
            var thumb = p.thumbnail
                ? '<img src="' + p.thumbnail + '" alt="" loading="lazy">'
                : '<div style="width:100%;height:100%;background:#e8eaed;display:flex;align-items:center;justify-content:center;font-size:1.5rem">📦</div>';
            grid.insertAdjacentHTML('beforeend',
                '<a href="' + escHtml(p.url) + '" class="recently-card">' +
                    '<div class="recently-thumb">' + thumb + '</div>' +
                    '<div class="recently-body">' +
                        '<div class="recently-title">' + escHtml(p.title) + '</div>' +
                        '<div class="recently-price">' + parseFloat(p.price).toFixed(0) + ' ₽</div>' +
                    '</div>' +
                '</a>'
            );
        });
    } catch (e) {}
}

function loadSimilarProducts(category, currentId) {
    var grid    = document.getElementById('similar-products-grid');
    var section = document.getElementById('similar-products-section');
    if (!grid) return;

    var q = 'products?page=1&category=' + encodeURIComponent(category || '');
    mktRest(q, 'GET', null, function (data) {
        var items = (data.items || []).filter(function (p) { return p.id !== currentId; }).slice(0, 8);
        if (!items.length) {
            if (section) section.style.display = 'none';
            return;
        }
        grid.innerHTML = '';
        items.forEach(function (p) {
            var priceHtml = p.price_sale > 0
                ? '<span class="p-old">' + p.price_base + ' ₽</span><span class="p-new">' + p.price_sale + ' ₽</span>'
                : '<span class="p-main">' + p.price + ' ₽</span>';
            var thumb = p.thumbnail
                ? '<img src="' + p.thumbnail + '" alt="" loading="lazy">'
                : '<div style="width:100%;height:100%;background:#e8eaed;display:flex;align-items:center;justify-content:center;font-size:2rem">📦</div>';
            var dTag = p.delivery === 'auto' ? '<span class="tag tag-auto">⚡ Авто</span>' : '<span class="tag tag-manual">👤 Ручная</span>';
            grid.insertAdjacentHTML('beforeend',
                '<a href="' + p.url + '" class="product-grid-card">' +
                    '<div class="product-grid-img">' + thumb + '</div>' +
                    '<div class="product-grid-body">' +
                        '<div class="product-grid-title">' + escHtml(p.title) + '</div>' +
                        '<div class="product-grid-tags">' + dTag + '</div>' +
                        '<div class="product-grid-price">' + priceHtml + '</div>' +
                    '</div>' +
                '</a>'
            );
        });
    });
}

function loadReviews(append) {
    var reviewsEl = document.getElementById('product-reviews');
    var grid      = document.getElementById('reviews-grid');
    if (!grid || !reviewsEl) return;

    var productId = reviewsEl.dataset.product;
    if (!append) grid.innerHTML = '<div class="loader-sm" style="grid-column:1/-1"></div>';

    mktRest('reviews/' + productId + '?page=' + reviewPage, 'GET', null, function (data) {
        if (!append) grid.innerHTML = '';
        reviewTotal = data.total || 0;

        if (!data.reviews || !data.reviews.length) {
            if (!append) grid.innerHTML = '<p style="color:var(--text-secondary);font-size:.875rem;padding:8px 0">Отзывов пока нет.</p>';
            return;
        }

        data.reviews.forEach(function (r) {
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                stars += '<span style="color:' + (i <= r.rating ? '#fbbf24' : '#d1d5db') + '">★</span>';
            }
            grid.insertAdjacentHTML('beforeend',
                '<div class="review-card">' +
                    '<div class="review-header">' +
                        '<img src="' + escHtml(r.avatar) + '" width="36" alt="" class="review-avatar">' +
                        '<div>' +
                            '<div class="review-name">' + escHtml(r.buyer) + '</div>' +
                            '<div class="review-stars">' + stars + '</div>' +
                        '</div>' +
                        '<div class="review-date">' + escHtml(r.date) + '</div>' +
                    '</div>' +
                    '<p class="review-text">' + escHtml(r.text) + '</p>' +
                '</div>'
            );
        });

        var moreWrap = document.getElementById('reviews-more-wrap');
        if (moreWrap) {
            moreWrap.style.display = (reviewPage * 4 < reviewTotal) ? '' : 'none';
        }
    });
}
