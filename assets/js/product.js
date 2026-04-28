document.addEventListener('DOMContentLoaded', function() {
    var reviewsList = document.getElementById('reviews-list');
    if (reviewsList) {
        var productId = document.getElementById('product-reviews').dataset.product;
        var args = {
            'post_type'      : 'orders',
            'post_status'    : 'publish',
            'meta_query[0][key]'    : 'offer_id',
            'meta_query[0][value]'  : productId,
            'meta_query[1][key]'    : 'order_status',
            'meta_query[1][value]'  : 'completed',
        };
        mktRest('orders', 'GET', null, function(data) {
            if (!data.items || !data.items.length) {
                reviewsList.innerHTML = '<p style="color:var(--text-secondary);font-size:.875rem">Отзывов пока нет.</p>';
                return;
            }
            reviewsList.innerHTML = '';
        });
    }
});
