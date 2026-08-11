<?php




get_header();

wp_enqueue_style('vaccine', get_stylesheet_directory_uri() . '/assets/css/vaccine.css', array(), '1.0.0', 'all');


echo "hello vaccines";


get_footer();
?>