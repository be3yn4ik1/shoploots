<?php
/*
Template Name: Dashboard
*/
defined('ABSPATH') || exit;
if (!is_user_logged_in()) { wp_redirect(home_url('/auth/')); exit; }

$user_id   = get_current_user_id();
$user      = wp_get_current_user();
$role      = mkt_get_role($user_id);
$balance   = mkt_get_balance($user_id);
$hold      = mkt_get_hold($user_id);
$ref_code  = get_field('ref_code', "user_{$user_id}") ?: '';
$avatar    = mkt_get_avatar_url($user_id);
$card          = get_field('withdrawal_card', "user_{$user_id}") ?: '';
$seller_rating = mkt_get_seller_rating($user_id);

get_header();
?>
<div class="dash-layout">
    <aside class="dash-sidebar">
        <div class="dash-profile">
            <div class="dash-avatar-wrap">
                <img src="<?= esc_url($avatar) ?>" alt="Аватар" class="dash-avatar" id="avatar-preview">
            </div>
            <div class="dash-user-info">
                <strong><?= esc_html($user->display_name) ?></strong>
                <span class="role-badge role-<?= esc_attr($role) ?>"><?= esc_html(mkt_role_label($user_id)) ?></span>
                <?php if ($seller_rating['count'] > 0): ?>
                <div style="font-size:.82rem;margin-top:2px"><?= mkt_stars_html($seller_rating['avg'], $seller_rating['count']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dash-balance">
            <div class="balance-item">
                <span class="balance-label">Баланс</span>
                <span class="balance-val" id="dash-balance"><?= esc_html(mkt_format_price($balance)) ?></span>
            </div>
            <?php if ($hold > 0): ?>
            <div class="balance-item hold">
                <span class="balance-label">Ожидается</span>
                <span class="balance-val"><?= esc_html(mkt_format_price($hold)) ?></span>
            </div>
            <?php endif; ?>
            <button class="btn-primary btn-sm" data-modal="modal-deposit">Пополнить</button>
        </div>

        <nav class="dash-nav">
            <a href="#" class="dash-nav-item active" data-section="overview">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Обзор
            </a>
            <a href="#" class="dash-nav-item" data-section="products">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                Мои товары
            </a>
            <a href="#" class="dash-nav-item" data-section="orders">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Покупки
            </a>
            <a href="#" class="dash-nav-item" data-section="sales">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                Продажи
            </a>
            <a href="#" class="dash-nav-item" data-section="balance">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                История баланса
            </a>
            <a href="#" class="dash-nav-item" data-section="referrals">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Рефералы
            </a>
            <a href="#" class="dash-nav-item" data-section="payout">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                Вывод средств
            </a>
            <a href="#" class="dash-nav-item" data-section="profile">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Профиль
            </a>
            <a href="#" class="dash-nav-item dash-nav-logout" data-action="logout" style="margin-top:auto;color:var(--red)">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Выйти
            </a>
        </nav>
    </aside>

    <main class="dash-main">
        <section class="dash-section active" id="section-overview">
            <h2 class="section-title">Обзор</h2>
            <div class="stats-grid" id="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-val"><?= esc_html(mkt_format_price($balance)) ?></div>
                        <div class="stat-label">Баланс</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-val" data-stat="total_purchases">—</div>
                        <div class="stat-label">Покупок</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-val" data-stat="total_sales">—</div>
                        <div class="stat-label">Продаж</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-val" data-stat="total_earned">—</div>
                        <div class="stat-label">Заработано</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-val"><?= esc_html($ref_code) ?></div>
                        <div class="stat-label">Ваш промокод</div>
                    </div>
                    <button class="btn-icon copy-btn" data-copy="<?= esc_attr($ref_code) ?>" title="Скопировать">
                        <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                </div>
            </div>

            <div class="recent-orders-wrap">
                <h3>Последние покупки</h3>
                <div id="recent-orders-list"><div class="loader-sm"></div></div>
            </div>
        </section>

        <section class="dash-section" id="section-products">
            <div class="section-header">
                <h2 class="section-title">Мои товары</h2>
                <button class="btn-primary" data-modal="modal-create-product">+ Создать товар</button>
            </div>
            <div id="my-products-list"><div class="loader-sm"></div></div>
        </section>

        <section class="dash-section" id="section-orders">
            <h2 class="section-title">История покупок</h2>
            <div id="orders-list"><div class="loader-sm"></div></div>
        </section>

        <section class="dash-section" id="section-sales">
            <h2 class="section-title">История продаж</h2>
            <div id="sales-list"><div class="loader-sm"></div></div>
        </section>

        <section class="dash-section" id="section-balance">
            <h2 class="section-title">История баланса</h2>
            <div id="balance-log-list"><div class="loader-sm"></div></div>
            <div id="balance-log-pagination" style="display:none;margin-top:16px;display:flex;gap:8px;flex-wrap:wrap"></div>
        </section>

        <section class="dash-section" id="section-referrals">
            <h2 class="section-title">Реферальная программа</h2>
            <div class="ref-card card">
                <div class="ref-promo-block">
                    <div class="ref-promo-label">Ваш инвайт-код</div>
                    <div class="ref-promo-val"><?= esc_html($ref_code) ?></div>
                    <div class="ref-promo-link" id="ref-link-text"><?= esc_url(home_url('/auth/?ref=' . $ref_code)) ?></div>
                    <button class="btn-primary btn-sm copy-btn" data-copy="<?= esc_url(home_url('/auth/?ref=' . $ref_code)) ?>">Скопировать ссылку</button>
                    <div class="ref-stat-row" style="margin-top:12px;font-size:.9rem">Приглашено рефералов: <strong id="ref-count-val">—</strong></div>
                </div>
                <div class="ref-levels">
                    <h4>Условия программы</h4>
                    <?php
                    $levels = [
                        ['label' => 'Продавец — 1 уровень', 'pct' => 5],
                        ['label' => 'Продавец — 2 уровень', 'pct' => 3],
                        ['label' => 'Продавец — 3 уровень', 'pct' => 2],
                        ['label' => 'Покупатель — 1 уровень', 'pct' => 1],
                    ];
                    foreach ($levels as $lvl):
                    ?>
                    <div class="ref-level-row">
                        <span class="ref-level-num"><?= esc_html($lvl['label']) ?></span>
                        <div class="ref-level-bar"><div style="width:<?= min(100, $lvl['pct'] * 10) ?>%"></div></div>
                        <span class="ref-level-pct"><?= $lvl['pct'] ?>%</span>
                    </div>
                    <?php endforeach; ?>
                    <p class="ref-note">Со стороны продавца: 5%+3%+2% по цепочке. Со стороны покупателя: 1% тому, кто его пригласил.</p>
                </div>
            </div>
        </section>

        <section class="dash-section" id="section-payout">
            <h2 class="section-title">Вывод средств</h2>
            <div class="card payout-card">
                <div class="form-group">
                    <label>Реквизиты карты / кошелька</label>
                    <div class="card-save-row">
                        <input type="text" id="payout-card" placeholder="Номер карты или кошелька" value="<?= esc_attr($card) ?>">
                        <button class="btn-secondary" id="save-card-btn">Сохранить</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Сумма вывода (доступно: <span id="avail-balance"><?= esc_html(mkt_format_price($balance)) ?></span>)</label>
                    <input type="number" id="payout-amount" min="<?= esc_attr(mkt_get_system_option('min_withdrawal', 100)) ?>" step="1" placeholder="<?= esc_attr(mkt_get_system_option('min_withdrawal', 100)) ?>">
                </div>
                <div class="payout-notice">
                    <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Средства поступают на реквизиты в течение 48 часов
                </div>
                <button class="btn-primary" id="payout-btn">Вывести средства</button>
            </div>
            <div class="payout-history-wrap" style="margin-top:24px">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:12px">История заявок</h3>
                <div id="payout-history-list"><div class="loader-sm"></div></div>
            </div>
        </section>

        <section class="dash-section" id="section-profile">
            <h2 class="section-title">Профиль</h2>
            <div class="card profile-form-card">
                <form id="profile-form" enctype="multipart/form-data">
                    <div class="avatar-upload">
                        <img src="<?= esc_url($avatar) ?>" alt="Аватар" class="avatar-preview" id="avatar-preview-profile">
                        <label class="avatar-upload-btn">
                            <input type="file" name="avatar" id="avatar-file" accept="image/*" hidden>
                            Изменить фото
                        </label>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Имя</label>
                            <input type="text" name="name" value="<?= esc_attr($user->display_name) ?>">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= esc_attr($user->user_email) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Telegram</label>
                        <input type="text" name="telegram" value="<?= esc_attr(get_field('telegram', "user_{$user_id}") ?: '') ?>" placeholder="@username">
                    </div>
                    <div class="form-group">
                        <label>Новый пароль (оставьте пустым чтобы не менять)</label>
                        <input type="password" name="password" placeholder="••••••••">
                    </div>
                    <div class="form-error" id="profile-error"></div>
                    <button type="submit" class="btn-primary">Сохранить изменения</button>
                </form>
            </div>
        </section>
    </main>
</div>

<div class="modal-overlay" id="modal-deposit">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Пополнение баланса</h3>
            <button class="modal-close" data-close="modal-deposit">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Сумма пополнения (₽)</label>
                <input type="number" id="deposit-amount" min="<?= esc_attr(mkt_get_system_option('min_deposit', 50)) ?>" step="50" placeholder="500">
            </div>
            <div class="deposit-presets">
                <?php foreach ([100, 500, 1000, 2000, 5000] as $preset): ?>
                <button class="preset-btn" data-amount="<?= $preset ?>"><?= $preset ?> ₽</button>
                <?php endforeach; ?>
            </div>
            <button class="btn-primary btn-full" id="deposit-btn">Перейти к оплате</button>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                <label style="display:block;font-size:.82rem;font-weight:600;margin-bottom:6px;color:var(--text-secondary)">Промокод</label>
                <div style="display:flex;gap:8px">
                    <input type="text" id="promo-code-input" placeholder="ABCDE12345" style="text-transform:uppercase">
                    <button class="btn-secondary" id="apply-promo-btn" style="white-space:nowrap">Применить</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-create-product">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h3>Создать товар</h3>
            <button class="modal-close" data-close="modal-create-product">×</button>
        </div>
        <div class="modal-body">
            <form id="create-product-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Изображение товара <span class="required">*</span></label>
                    <div class="img-upload-area" id="product-img-area">
                        <input type="file" name="product_image" id="product-image" accept=".png,.webp,.jpg,.jpeg" required>
                        <div class="img-upload-placeholder" id="product-img-placeholder">
                            <svg viewBox="0 0 24 24" width="28" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span>Нажмите или перетащите фото (PNG, JPG, WEBP)</span>
                        </div>
                        <img id="product-img-preview" src="" alt="" style="display:none;width:100%;height:140px;object-fit:cover;border-radius:8px">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Название товара <span class="required">*</span></label>
                        <input type="text" name="title" required placeholder="Например: Аккаунт Steam">
                    </div>
                    <div class="form-group">
                        <label>Тип выдачи <span class="required">*</span></label>
                        <select name="delivery" id="delivery-type" required>
                            <option value="auto">Автовыдача</option>
                            <option value="manual">Ручная выдача</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Цена (₽) <span class="required">*</span></label>
                        <input type="number" name="price" min="1" step="0.01" required placeholder="100">
                    </div>
                    <div class="form-group">
                        <label>Цена по акции (₽, 0 = нет)</label>
                        <input type="number" name="price_sale" min="0" step="0.01" placeholder="0" value="0">
                    </div>
                </div>
                <div class="form-group" id="keys-group">
                    <label>Ключи / данные (каждый с новой строки) <span class="required">*</span></label>
                    <textarea name="keys" rows="5" placeholder="key1:value1&#10;key2:value2&#10;..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Категория (игра) <span class="required">*</span></label>
                        <select name="category" id="product-category" required>
                            <option value="">Выберите категорию</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Тип товара <span class="required">*</span></label>
                        <select name="type" id="product-type" required>
                            <option value="">Выберите тип</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Описание <span class="required">*</span></label>
                    <textarea name="description" rows="3" required placeholder="Описание товара..."></textarea>
                </div>
                <div class="form-group">
                    <label>Способ получения <span class="required">*</span></label>
                    <textarea name="how_to" rows="2" required placeholder="Инструкция для покупателя..."></textarea>
                </div>
                <div class="form-error" id="create-product-error"></div>
                <button type="submit" class="btn-primary btn-full">Создать товар</button>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-edit-product">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h3>Редактировать товар</h3>
            <button class="modal-close" data-close="modal-edit-product">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-product-id">
            <div class="form-group">
                <label>Изображение товара (оставьте пустым, чтобы не менять)</label>
                <div class="img-upload-area" id="edit-product-img-area">
                    <input type="file" id="edit-product-image" accept=".png,.webp,.jpg,.jpeg">
                    <div class="img-upload-placeholder" id="edit-product-img-placeholder">
                        <svg viewBox="0 0 24 24" width="28" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span>Нажмите для замены фото (PNG, JPG, WEBP)</span>
                    </div>
                    <img id="edit-product-img-preview" src="" alt="" style="display:none;width:100%;height:130px;object-fit:cover;border-radius:8px">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Название товара <span class="required">*</span></label>
                    <input type="text" id="edit-product-title" placeholder="Название">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Цена (₽) <span class="required">*</span></label>
                    <input type="number" id="edit-product-price" min="1" step="0.01">
                </div>
                <div class="form-group">
                    <label>Цена по акции (₽, 0 = нет)</label>
                    <input type="number" id="edit-product-price-sale" min="0" step="0.01" value="0">
                </div>
            </div>
            <div class="form-group">
                <label>Описание</label>
                <textarea id="edit-product-desc" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Способ получения</label>
                <textarea id="edit-product-howto" rows="2"></textarea>
            </div>
            <div class="form-group" id="edit-keys-group">
                <label>Добавить ключи (каждый с новой строки)</label>
                <textarea id="edit-product-keys" rows="4" placeholder="Новые ключи добавятся к существующим..."></textarea>
            </div>
            <div class="form-error" id="edit-product-error"></div>
            <div class="modal-actions">
                <button class="btn-secondary" data-close="modal-edit-product">Отмена</button>
                <button class="btn-primary" id="save-product-btn">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-auto-key">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Покупка завершена!</h3>
            <button class="modal-close" data-close="modal-auto-key">×</button>
        </div>
        <div class="modal-body">
            <p>Ваш ключ / данные товара:</p>
            <div class="key-box" id="auto-key-value"></div>
            <button class="btn-primary btn-sm copy-btn" id="copy-key-btn">Скопировать</button>
        </div>
    </div>
</div>

<?php get_footer(); ?>
