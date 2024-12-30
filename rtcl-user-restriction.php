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


/**
 * Class RtclUserRestriction
 */
class RtclUserRestriction {
	/**
	 * User ID
	 * @var int
	 */
	public $userId;

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'init', [ $this, '_init' ] );
		add_action( 'admin_init', [ $this, 'check_post_type_redirect' ], 100 );
		add_action( 'admin_init', [ $this, 'add_custom_capabilities_to_user' ] );
		add_action( 'admin_menu', [ $this, 'restrict_menu_access' ], 999 );
		add_action( 'admin_menu', [ $this, 'hide_upload_menu' ], 999 );
		add_filter( 'post_row_actions', [ $this, 'prevent_user_actions' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'prevent_user_actions' ], 10, 2 );
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'block_user_file_upload' ] );
		add_action( 'admin_footer', [ $this, 'disable_media_uploader_for_user' ] );
		add_action( 'admin_init', [ $this, 'register_user_select_setting' ] );
		add_filter( 'rtcl_account_default_menu_items', [ $this, 'my_account_endpoint_modify' ] );
		add_filter( 'rtcl_my_account_endpoint', [ $this, 'my_account_endpoint_modify' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'my_account_endpoint_modify' ] );
		add_action( 'wp', [ $this, 'woo_redirect_forbidden_access' ] );
		add_action( 'admin_bar_menu', [ $this, 'customize_my_wp_admin_bar' ], 9999 );

	}

	public function _init() {
		$this->userId = get_option( 'rtcl__selected_user' ) ?? 0;
	}

	public function check_if_wp_admin_is_last( $url ) {
		// Check if the URL ends with '/wp-admin'
		return ! preg_match( '#/wp-admin/?$#', $url );
	}

	public function check_post_type_redirect() {
		if ( wp_doing_ajax() ) {
			return;
		}


		$current_user    = wp_get_current_user();
		$rtcl_post_types = [
			'rtcl_listing',
			'rtcl_cfg',
			'rtcl_cf',
			'rtcl_payment',
			'rtcl_pricing',
			'store',
			'rtcl_agent'
		];

		$serverRequest = $_SERVER['REQUEST_URI'] ?? '';
		$action        = $_GET['action'] ?? '';
		$postType      = ! empty( $_GET['post'] ) ? get_post_type( $_GET['post'] ) : '';


		if ( $action == 'edit' && ! in_array( $postType, $rtcl_post_types ) ) {
			wp_redirect( home_url() );
			exit;
		}

		if ( strpos( $serverRequest, 'wp-admin' ) === false ) {
			return;
		}

		// Check if the user ID matches.
		if ( $current_user->ID == $this->userId && 'edit' !== $action && $this->check_if_wp_admin_is_last( $serverRequest ) ) {
			if ( ! isset( $_GET['post_type'] ) || ! in_array( $_GET['post_type'], $rtcl_post_types ) ) {
				wp_redirect( home_url() );
				exit;
			}
		}
	}


	public function add_custom_capabilities_to_user() {
		$user = new WP_User( $this->userId );

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

	public function restrict_menu_access() {
		$current_user = wp_get_current_user();

		// Check if the user ID matches.
		if ( $current_user->ID == $this->userId ) {
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


	public function prevent_user_actions( $actions, $post ) {
		$current_user = wp_get_current_user();

		// Prevent actions for the user.
		if ( $current_user->ID == $this->userId ) {
			// Clear the edit and delete actions.
			unset( $actions['edit'] );
			unset( $actions['trash'] );
		}

		return $actions;
	}


	public function hide_upload_menu() {
		$current_user = wp_get_current_user();

		if ( $current_user->ID == $this->userId ) {
			remove_menu_page( 'upload.php' );
		}
	}


	public function block_user_file_upload( $file ) {
		$current_user = wp_get_current_user();

		if ( $current_user->ID == $this->userId ) {
			wp_die( __( 'You are not allowed to upload files.' ) );
		}

		return $file;
	}

	public function prevent_programmatic_uploads_for_user( $upload_dir ) {
		// Get the current logged-in user
		$current_user = wp_get_current_user();

		error_log( print_r( $upload_dir, true ) . "\n", 3, __DIR__ . '/log.txt' );

		// Check if the current user's ID is 16
		if ( $current_user->ID == $this->userId ) {
			// Block the upload by setting an error
			$upload_dir['error'] = 'You are not allowed to upload files.';
		}

		return $upload_dir;
	}

	function disable_media_uploader_for_user() {
		// Get the current logged-in user
		$current_user = wp_get_current_user();

		// Check if the current user's ID is 16
		if ( $current_user->ID == $this->userId ) {
			// Disable the media uploader via JavaScript
			echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                // Disable the Media Uploader buttons
                $(".media-button").prop("disabled", true);
                $(".media-frame").on("open", function() {
                    return false; // Prevent Media Uploader from opening
                });
            });
        </script>';
		}
	}

	function register_user_select_setting() {
		// Register the setting
		register_setting(
			'general',
			'rtcl__selected_user',
			'sanitize_text_field'
		);

		// Add the setting section and field to the General Settings page
		add_settings_section(
			'user_select_section',
			'Select a User',
			null,
			'general'
		);

		add_settings_field(
			'rtcl__selected_user_field',
			'Choose a User for Restriction',
			[ $this, 'user_select_dropdown' ],
			'general',
			'user_select_section'
		);
	}

	function user_select_dropdown() {
		// Get the list of users
		$users = get_users();

		// Get the currently selected user from options
		$selected_user = get_option( 'rtcl__selected_user' );

		// Start the select dropdown
		echo '<select name="rtcl__selected_user" id="rtcl__selected_user">';
		echo '<option value="">Select a user</option>';

		// Loop through users and create an option for each one
		foreach ( $users as $user ) {
			echo '<option value="' . esc_attr( $user->ID ) . '" ' . selected( $selected_user, $user->ID, false ) . '>';
			echo esc_html( $user->display_name ) . ' - ' . $user->ID;
			echo '</option>';
		}

		echo '</select>';
	}

	public function my_account_endpoint_modify( $endpoints ) {
		unset( $endpoints['edit-account'] );

		return $endpoints;
	}

	public function woo_redirect_forbidden_access() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		$current_endpoint = WC()->query->get_current_endpoint();
		if ( 'edit-account' == $current_endpoint ) {
			wp_redirect( wc_get_account_endpoint_url( 'dashboard' ) );
		}
	}

	public function customize_my_wp_admin_bar( $wp_admin_bar ) {
		$current_user = wp_get_current_user();
		// Check if the user ID matches.
		if ( $current_user->ID == $this->userId ) {
			// Check if the current user has permission to edit pages
			$wp_admin_bar->remove_node( 'edit' );
			$wp_admin_bar->remove_node( 'new-content' );
			$wp_admin_bar->remove_node( 'elementor_edit_page' );
			$wp_admin_bar->remove_node( 'customize' );
		}

	}
}


new RtclUserRestriction();