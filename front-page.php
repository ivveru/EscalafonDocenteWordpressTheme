<?php
/**
 * The front page template file
 *
 * If the user has selected a static page for their homepage, this is what will
 * appear.
 * Learn more: https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package WordPress
 * @subpackage Bellas_Artes
 * @since Bellas Artes 1.0
 * @version 1.0
 */
get_header();
?>
<div id="login">
    <h1>
        Sistema de Gestión de Escalafón
        <br/>
        <small>Bellas Artes Institución Universitaria del Valle</small>
    </h1>
    
    <?php
    // Display error messages
    $error = isset($_GET['error']) ? $_GET['error'] : '';
    $error_message = '';
    
    switch($error) {
        case 'empty_fields':
            $error_message = 'Por favor complete todos los campos.';
            break;
        case 'invalid_credentials':
            $error_message = 'Usuario o contraseña incorrectos.';
            break;
        case 'attempts_2':
            $error_message = 'Credenciales incorrectas. Quedan 2 intentos.';
            break;
        case 'attempts_1':
            $error_message = 'Credenciales incorrectas. Queda 1 intento.';
            break;
        case 'account_locked':
            $error_message = 'Cuenta bloqueada. Contacte al administrador.';
            break;
        case 'too_many_attempts':
            $error_message = 'Demasiados intentos. Intente nuevamente en 15 minutos.';
            break;
        case 'security_check_failed':
            $error_message = 'Error de seguridad. Intente nuevamente.';
            break;
    }
    
    if ($error_message) {
        echo '<div class="error-message">' . esc_html($error_message) . '</div>';
    }
    ?>
    
    <form action="<?php echo home_url('/custom-login'); ?>" method="POST" id="loginform">
        <?php wp_nonce_field('custom_login_action', 'login_nonce'); ?>
        <div class="input">
            <label for="log">Email</label>
            <input type="text" name="log" id="log" required autocomplete="username">
        </div>
        <div class="input">
            <label for="pwd">Password</label>
            <input type="password" name="pwd" id="pwd" required autocomplete="current-password">
        </div>
        <div class="input">
            <button type="submit">Iniciar Sesión</button>
        </div>
    </form>
</div>
<?php
get_footer();
