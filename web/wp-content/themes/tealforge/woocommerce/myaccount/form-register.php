<?php
/** Dedicated registration form using WooCommerce's native submission handler. */
declare(strict_types=1);
defined('ABSPATH') || exit;

$email = isset($_POST['email']) && is_string($_POST['email']) ? wp_unslash($_POST['email']) : '';
$username = isset($_POST['username']) && is_string($_POST['username']) ? wp_unslash($_POST['username']) : '';
?>
<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action('woocommerce_register_form_tag'); ?>>
    <?php do_action('woocommerce_register_form_start'); ?>
    <?php if ('no' === get_option('woocommerce_registration_generate_username')) : ?>
        <p class="woocommerce-form-row form-row form-row-wide">
            <label for="reg_username">Identifiant <span aria-hidden="true">*</span></label>
            <input type="text" class="input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo esc_attr($username); ?>" required>
        </p>
    <?php endif; ?>
    <p class="woocommerce-form-row form-row form-row-wide">
        <label for="reg_email">Adresse e-mail <span aria-hidden="true">*</span></label>
        <input type="email" class="input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo esc_attr($email); ?>" required>
    </p>
    <?php if ('no' === get_option('woocommerce_registration_generate_password')) : ?>
        <p class="woocommerce-form-row form-row form-row-wide">
            <label for="reg_password">Mot de passe <span aria-hidden="true">*</span></label>
            <input type="password" class="input-text" name="password" id="reg_password" autocomplete="new-password" required>
        </p>
    <?php else : ?>
        <p class="tf-account-orders__intro">Vous recevrez un e-mail pour définir votre mot de passe.</p>
    <?php endif; ?>
    <?php do_action('woocommerce_register_form'); ?>
    <p class="woocommerce-form-row form-row">
        <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
        <input type="hidden" name="redirect" value="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
        <button type="submit" class="woocommerce-Button button woocommerce-form-register__submit" name="register" value="1">Créer mon compte</button>
    </p>
    <?php do_action('woocommerce_register_form_end'); ?>
</form>
