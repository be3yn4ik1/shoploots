<?php
/*
Template Name: Auth Page
*/
defined('ABSPATH') || exit;
if (is_user_logged_in()) { wp_redirect(home_url('/dashboard/')); exit; }
get_header();
$rc_key = mkt_get_system_option('recaptcha_site_key', '');
?>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <?php if ($logo = get_custom_logo()): echo $logo; else: ?>
                <span class="auth-logo-text"><?= esc_html(get_bloginfo('name')) ?></span>
            <?php endif; ?>
        </div>

        <div class="auth-tabs">
            <button class="auth-tab active" data-tab="login">Вход</button>
            <button class="auth-tab" data-tab="register">Регистрация</button>
        </div>

        <form class="auth-form active" id="login-form" data-action="mkt_login">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="you@example.com" required autocomplete="email">
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <div class="input-eye">
                    <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="eye-btn" data-target="password">
                        <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-error" id="login-error"></div>
            <button type="submit" class="btn-primary btn-full">Войти</button>
        </form>

        <form class="auth-form" id="register-form" data-action="mkt_register">
            <div class="form-group">
                <label>Имя</label>
                <input type="text" name="name" placeholder="Ваше имя" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="you@example.com" required autocomplete="email">
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <div class="input-eye">
                    <input type="password" name="password" placeholder="Минимум 6 символов" required autocomplete="new-password">
                    <button type="button" class="eye-btn" data-target="password">
                        <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label>Инвайт-код <span class="required">*</span></label>
                <div class="invite-wrap">
                    <input type="text" name="invite" id="invite-code" placeholder="XXXXXXXXXX" maxlength="10" style="text-transform:uppercase" required>
                    <span class="invite-status" id="invite-status"></span>
                </div>
            </div>
            <div class="form-group">
                <label>Роль</label>
                <div class="role-switcher">
                    <label class="role-option">
                        <input type="radio" name="role" value="buyer" checked>
                        <span class="role-btn">
                            <svg viewBox="0 0 24 24" width="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            Покупатель
                        </span>
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="seller">
                        <span class="role-btn">
                            <svg viewBox="0 0 24 24" width="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                            Продавец
                        </span>
                    </label>
                </div>
            </div>
            <?php if ($rc_key): ?>
            <div class="form-group">
                <div class="g-recaptcha" data-sitekey="<?= esc_attr($rc_key) ?>"></div>
            </div>
            <?php endif; ?>
            <div class="form-error" id="register-error"></div>
            <button type="submit" class="btn-primary btn-full">Создать аккаунт</button>
        </form>
    </div>
</div>
<?php get_footer(); ?>
