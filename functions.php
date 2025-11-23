<?php
/**
 * Cotrim.dev Retro Terminal Theme Functions
 *
 * @package Cotrimdev_Retro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue parent and child theme styles
 */
function cotrimdev_retro_enqueue_styles() {
    // Enqueue parent theme stylesheet
    wp_enqueue_style(
        'twentytwentyfive-style',
        get_template_directory_uri() . '/style.css',
        array(),
        wp_get_theme()->parent()->get('Version')
    );

    // Enqueue child theme stylesheet
    wp_enqueue_style(
        'cotrimdev-retro-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( 'twentytwentyfive-style' ),
        wp_get_theme()->get('Version')
    );

    // Enqueue custom terminal CSS
    wp_enqueue_style(
        'cotrimdev-terminal-css',
        get_stylesheet_directory_uri() . '/assets/css/terminal.css',
        array( 'cotrimdev-retro-style' ),
        wp_get_theme()->get('Version')
    );

    // Enqueue theme toggle script
    wp_enqueue_script(
        'cotrimdev-theme-toggle',
        get_stylesheet_directory_uri() . '/assets/js/theme-toggle.js',
        array(),
        wp_get_theme()->get('Version'),
        true
    );
}
add_action( 'wp_enqueue_scripts', 'cotrimdev_retro_enqueue_styles' );

/**
 * Register custom patterns directory
 */
function cotrimdev_retro_register_patterns() {
    register_block_pattern_category(
        'cotrimdev',
        array( 'label' => __( 'Cotrim.dev', 'cotrimdev-retro' ) )
    );
}
add_action( 'init', 'cotrimdev_retro_register_patterns' );

/**
 * Add custom body class for theme-specific styling
 */
function cotrimdev_retro_body_class( $classes ) {
    $classes[] = 'cotrimdev-retro-theme';
    return $classes;
}
add_filter( 'body_class', 'cotrimdev_retro_body_class' );
