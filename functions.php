<?php

namespace Theme\Children;
// directly acces denied
defined('ABSPATH') || exit;

final class WP_Theme_Child{

    // create evil
    private static $instance;

    /**
     * execute default method when activate the child theme
     */
    function __construct(){
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * enqueue theme style and scripts
     *
     * @return void
     */
    public function enqueue_scripts(){

        // get parent style
        wp_enqueue_style(
            'parent-style',
            get_template_directory_uri() . '/style.css'
        );

        // enqueue child style
        wp_enqueue_style(
            'child-style',
            get_stylesheet_directory_uri() . '/style.css',
            [ 'parent-style' ]
        );

    }

    /**
     * create singletone instance
     *
     * @return void
     */
    public static function init(){
        if( is_null( self::$instance ) )
            self::$instance = new self();

        return self::$instance;
    }
}

// create child theme object
function child_theme(){
    return WP_Theme_Child::init();
}

// execute the child theme
child_theme();

// load and boot the custom post types
require_once get_stylesheet_directory() . '/includes/post-types/class-services-post-type.php';
require_once get_stylesheet_directory() . '/includes/post-types/class-vaccines-post-type.php';

\Theme\Children\PostTypes\Services_Post_Type::init();
\Theme\Children\PostTypes\Vaccines_Post_Type::init();

// load and boot the services shortcode
require_once get_stylesheet_directory() . '/includes/Shortcodes/Bootstrap.php';

\Theme\Children\Shortcodes\Bootstrap::instance();

// load and boot the redirect management system
require_once get_stylesheet_directory() . '/includes/redirects/class-redirect-manager.php';

\AstraChild\Redirects\Redirect_Manager::instance();