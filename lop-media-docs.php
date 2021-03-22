<?php
/*
Plugin Name: LOP Media Documents
Plugin URI: http://dev.cmszp.cz
Description: Categories and Tags for document attachements.
Version: 2.1
Author: Lop
Author URI: http://lop.cz
License: GPL-2.0+
Text Domain: lop-mediadocs
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Main plugin class
require_once( plugin_dir_path( __FILE__ ) . 'classes/class-lop-mediadocs.php' );
add_action( 'plugins_loaded', array( 'LOP_MediaDocs', 'get_instance' ) );


// Template tags
// -------------

/**
 * Retrieve the amount of attachments a post has.
 */
function lop_get_attachments_number( $post_id = 0 ) {
	$lop_mediadocs = LOP_MediaDocs::get_instance();
	return $lop_mediadocs->get_attachments_number( $post_id );
}
