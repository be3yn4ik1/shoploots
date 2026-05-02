<?php
defined('ABSPATH') || exit;
$term = get_queried_object();
get_header();
?>
<div class="catalog-layout">
    <aside class="catalog-sidebar">
        <div class="card filter-card">
            <h3>Фильтры</h3>
            <div class="form-group">
                <label>Поиск</label>
                <div class="search-input-wrap">
                    <input type="text" id="catalog-search" placeholder="Название товара...">
                    <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
            </div>
            <div class="form-group">
                <label>Игра / Категория</label>
                <select id="filter-category">
                    <option value="">Все категории</option>
                </select>
            </div>
            <div class="form-group">
                <label>Тип товара</label>
                <select id="filter-type">
                    <option value="">Все типы</option>
                </select>
            </div>
            <div class="form-group">
                <label>Тип выдачи</label>
                <div class="check-group">
                    <label class="check-label">
                        <input type="checkbox" name="delivery" value="auto" checked>
                        <span>⚡ Автовыдача</span>
                    </label>
                    <label class="check-label">
                        <input type="checkbox" name="delivery" value="manual" checked>
                        <span>👤 Ручная выдача</span>
                    </label>
                </div>
            </div>
            <button class="btn-primary btn-full" id="apply-filters">Применить</button>
            <button class="btn-ghost btn-full" id="reset-filters">Сбросить</button>
        </div>
    </aside>

    <div class="catalog-main">
        <div class="catalog-header">
            <h1 class="catalog-title"><?= esc_html($term->name) ?></h1>
            <div class="catalog-sort">
                <select id="catalog-sort">
                    <option value="date">Новые</option>
                    <option value="price_asc">Дешевле</option>
                    <option value="price_desc">Дороже</option>
                </select>
                <span class="catalog-count" id="catalog-count"></span>
            </div>
        </div>
        <div class="products-grid" id="products-grid">
            <div class="loader-sm"></div>
        </div>
        <div class="pagination" id="catalog-pagination"></div>
    </div>
</div>
<script>
window.MKT_TERM_TYPE = 'category';
window.MKT_TERM_SLUG = <?= wp_json_encode($term->slug) ?>;
</script>
<?php get_footer(); ?>
