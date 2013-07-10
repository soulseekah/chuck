<?php

	/** This script contains utility functions to make WordPress do naughty things */
	/** Using wp-cli from the test runner might be a better idea overall */
	
	$action = isset( $_GET['passthrough'] ) ? $_GET['passthrough'] : null;

	define( 'CHUCK_TESTING', true );

	switch ( $action ):
		/** Create the necessary data */
		case 'init':
			add_action( 'init', function() {
				require ABSPATH . 'wp-admin/includes/user.php';
				require ABSPATH . 'wp-admin/includes/plugin.php';

				if ( !class_exists( 'Chuck' ) )
					activate_plugin( 'chuck/chuck.php' );

				$user = get_user_by( 'login', 'walker' );
				if ( $user ) /* No concurrency because of this */
					wp_delete_user( $user->ID );

				$user = wp_create_user( 'walker', 'walker', 'walker@localhost' );
				$user = get_user_by( 'id', $user );
				$user->set_role( 'ranger' );

				if ( isset( $_GET['live'] ) && $_GET['live'] === 'false' ) {
					update_user_meta( $user->ID, '_tests_live', false );
				}

				header( 'Content-Type: application/json' );
				printf( json_encode( $user->ID ) );
				exit;
			} );
			break;
		/** Get login cookies */
		case 'login':
			add_action( 'init', function() {
				wp_set_auth_cookie( $_GET['id'], true );
				header( 'Content-Type: application/json' );
				exit;
			} );
			break;
		/** Set chucks */
		case 'set_chucks':
			add_action( 'init', function() {
				update_user_meta( $_GET['id'], 'chucks_left', $_GET['amount'] );
				header( 'Content-Type: application/json' );
				exit;
			} );
			break;
	endswitch;
?>
