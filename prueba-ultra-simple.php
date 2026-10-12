<?php
/**
 * Plugin Name: Prueba Ultra Simple
 * Description: Shortcode de prueba para diagnóstico
 */

// Shortcode ultra simple
add_shortcode('prueba_simple', function() {
    return '<div style="background: green; color: white; padding: 40px; text-align: center; font-size: 32px;">
            ✅ FUNCIONA - ' . date('H:i:s') . '
            </div>';
});

// Mostrar GET data
add_shortcode('ver_get', function() {
    return '<pre>' . print_r($_GET, true) . '</pre>';
});