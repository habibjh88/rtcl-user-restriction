<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Classified User Restriction
 * Plugin URI:        https://radiustheme.com/demo/wordpress/rtcl-user-restriction
 * Description:       Enhance listing functionality and sell listing with WooCommerce.
 * Version:           1.0.0
 * Author:            RadiusTheme
 * Author URI:        https://radiustheme.com
 * Text Domain:       rtcl-user-restriction
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Rtcl\Models\Roles;

function check_post_type_redirect() {
	// Check if we're on the edit-tags page and the post_type is not 'rtcl_listing'
	if (wp_doing_ajax()) {
		return; // Exit the function if it's an AJAX request
	}
	$current_user = wp_get_current_user();
	$rtcl_tables = [
		'rtcl_listing',
		'rtcl_cfg',
		'rtcl_cf',
		'rtcl_payment',
		'rtcl_pricing',
		'store',
		'rtcl_agent'
	];


	$action = $_GET['action'] ?? '';
	// Check if the user ID matches.
	if ( $current_user->ID === 16 && 'edit' !== $action ) {
		if ( ! isset( $_GET['post_type'] ) || ! in_array( $_GET['post_type'], $rtcl_tables ) ) {
			wp_redirect( home_url( 'wp-admin/edit.php?post_type=rtcl_listing' ) );
			exit;
		}
	}
}

add_action( 'admin_init', 'check_post_type_redirect', 100 );


function add_custom_capabilities_to_user_16() {
	$user_id = 16;
	$user    = new WP_User( $user_id );

	if ( $user ) {
		// Define the capabilities to add
		$capabilities = Roles::get_core_caps();

		foreach ( $capabilities as $cap_group ) {
			foreach ( $cap_group as $cap ) {
				$user->add_cap( $cap );
			}
		}
	}

	if ( $user->has_cap( 'upload_files' ) ) {
		$user->remove_cap( 'upload_files' );
	}


}

// Hook to run on 'admin_init' or whenever needed
add_action( 'admin_init', 'add_custom_capabilities_to_user_16' );


/*function custom_limit_user_capabilities( $all_caps, $cap, $args, $user ) {
	// Restrict actions for the user with ID 16.
	if (  $user->ID === 16 ) {
		$all_caps['upload_files']                = false;
	}

	return $all_caps;
}*/

//add_filter( 'user_has_cap', 'custom_limit_user_capabilities', 10, 4 );


/*$GLOBALS['testsdf'] =1;
if(1 === $GLOBALS['testsdf']){
	error_log( print_r( $all_caps, true ) . "\n", 3, __DIR__ . '/log.txt' );
}
$GLOBALS['testsdf'] = 2;*/

function restrict_menu_access() {
	$current_user = wp_get_current_user();

	// Check if the user ID matches.
	if ( $current_user->ID === 16 ) {
		global $menu, $submenu;

		// Allowed menu slug and submenus.
		$allowed_menu = 'edit.php?post_type=rtcl_listing';

		foreach ( $menu as $key => $value ) {
			if ( $value[2] !== $allowed_menu ) {
				unset( $menu[ $key ] );
			}
		}

		foreach ( $submenu as $parent_slug => $submenus ) {
			if ( $parent_slug !== $allowed_menu ) {
				unset( $submenu[ $parent_slug ] );
			}
		}
	}
}

add_action( 'admin_menu', 'restrict_menu_access', 999 );

function prevent_user_actions( $actions, $post ) {
	$current_user = wp_get_current_user();

	// Prevent actions for the user.
	if ( $current_user->ID === 16 ) {
		// Clear the edit and delete actions.
		unset( $actions['edit'] );
		unset( $actions['trash'] );
	}

	return $actions;
}

add_filter( 'post_row_actions', 'prevent_user_actions', 10, 2 );
add_filter( 'page_row_actions', 'prevent_user_actions', 10, 2 );

function hide_upload_menu() {
	$current_user = wp_get_current_user();

	if ( $current_user->ID === 16 ) {
		remove_menu_page( 'upload.php' ); // Removes "Media" menu.
	}
}

add_action( 'admin_menu', 'hide_upload_menu', 999 );

function block_user_file_upload( $file ) {
	$current_user = wp_get_current_user();

	if ( $current_user->ID === 16 ) {
		wp_die( __( 'You are not allowed to upload files.' ) );
	}

	return $file;
}

add_filter( 'wp_handle_upload_prefilter', 'block_user_file_upload' );