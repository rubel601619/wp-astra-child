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
        add_action( 'init', [ $this, 'register_travel_clinic_post_type' ] );
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
     * Register the Travel Clinic custom post type
     *
     * @return void
     */
    public function register_travel_clinic_post_type() {
        $labels = array(
            'name'                  => _x( 'Travel Clinics', 'Post type general name', 'astra-child' ),
            'singular_name'         => _x( 'Travel Clinic', 'Post type singular name', 'astra-child' ),
            'menu_name'             => _x( 'Travel Clinics', 'Admin Menu text', 'astra-child' ),
            'name_admin_bar'        => _x( 'Travel Clinic', 'Add New on Toolbar', 'astra-child' ),
            'add_new'               => __( 'Add New', 'astra-child' ),
            'add_new_item'          => __( 'Add New Travel Clinic', 'astra-child' ),
            'new_item'              => __( 'New Travel Clinic', 'astra-child' ),
            'edit_item'             => __( 'Edit Travel Clinic', 'astra-child' ),
            'view_item'             => __( 'View Travel Clinic', 'astra-child' ),
            'all_items'             => __( 'All Travel Clinics', 'astra-child' ),
            'search_items'          => __( 'Search Travel Clinics', 'astra-child' ),
            'parent_item_colon'     => __( 'Parent Travel Clinics:', 'astra-child' ),
            'not_found'             => __( 'No travel clinics found.', 'astra-child' ),
            'not_found_in_trash'    => __( 'No travel clinics found in Trash.', 'astra-child' ),
            'featured_image'        => _x( 'Travel Clinic Image', 'Overrides the "Featured Image" phrase for this post type', 'astra-child' ),
            'set_featured_image'    => _x( 'Set travel clinic image', 'Overrides the "Set featured image" phrase for this post type', 'astra-child' ),
            'remove_featured_image' => _x( 'Remove travel clinic image', 'Overrides the "Remove featured image" phrase for this post type', 'astra-child' ),
            'use_featured_image'    => _x( 'Use as travel clinic image', 'Overrides the "Use as featured image" phrase for this post type', 'astra-child' ),
            'archives'              => _x( 'Travel Clinic archives', 'The post type archive label used in nav menus', 'astra-child' ),
            'insert_into_item'      => _x( 'Insert into travel clinic', 'Overrides the "Insert into post" phrase for this post type', 'astra-child' ),
            'uploaded_to_this_item' => _x( 'Uploaded to this travel clinic', 'Overrides the "Uploaded to this post" phrase for this post type', 'astra-child' ),
            'filter_items_list'     => _x( 'Filter travel clinics list', 'Screen reader text for the filter links heading on the post type listing screen', 'astra-child' ),
            'items_list_navigation' => _x( 'Travel clinics list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'astra-child' ),
            'items_list'            => _x( 'Travel clinics list', 'Screen reader text for the items list heading on the post type listing screen', 'astra-child' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'travel-clinic' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-location-alt',
            'supports'           => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'author',
                'revisions',
                'custom-fields',
                'comments',
                'trackbacks',
                'page-attributes',
                'post-formats',
            ),
            'taxonomies'         => array( 'category', 'post_tag' ),
            'show_in_rest'       => true,
            'rest_base'          => 'travel-clinic',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );

        register_post_type( 'travel-clinic', $args );
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