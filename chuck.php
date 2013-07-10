<?php
	/* Plugin Name: Chuck, Chuck, Chuck */

	class Chuck {
		
		public $remote_get_func;

		/** Initialization */
		public function __construct() {
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'wp_ajax_chuck_fetch_one', array( $this, 'fetch_one_ajax' ) );
			// add_filter( 'get_user_metadata', array( __CLASS__, 'sanitize_chucks_left' ), null, 4 );
			register_activation_hook( __FILE__, array( $this, 'activate' ) );
			register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

			/* Alternatively these could be filters */
			$this->remote_get_func = 'wp_remote_get';
		}

		/** Setup */
		public function activate() {
			add_role( 'ranger', 'Ranger', array( 'use_api' => true, 'read' => true ) );
		}

		/** Cleanup */
		public function deactivate() {
			remove_role( 'ranger' );
		}

		/** First steps */
		public function init() {	

			if ( isset( $_GET['chuck-ipn'] ) )
				exit( $this->do_ipn() );
		}

		/** Ah, those scripts... */
		public function enqueue_scripts() {
			wp_enqueue_script( 'chuck', plugins_url( 'chuck.js', __FILE__ ), array( 'jquery' ) );
		}

		/** Add menu page */
		public function admin_menu() {
			add_menu_page(
				'Chuck', 'Chuck', 'use_api', 'chuck',
				array( $this, 'admin_menu_screen' ),
				plugins_url( 'icon.png', __FILE__ ), 3 );
		}

		/** Display the Chuck Admin screen */
		public function admin_menu_screen() {
			$chucks_left = get_user_meta( wp_get_current_user()->ID, 'chucks_left', true );
			if ( !$chucks_left ) $chucks_left = 0;
			$last_result = get_user_meta( wp_get_current_user()->ID, 'last_result', true );
			if ( !$last_result ) $last_result = 'none';

			?>
				<h1>Chuck, chuck, chuck...</h2>
				<p>Chuck Norris welcomes you to your data access screen. Here you can top up your plan, and, more importantly, query the dataset.</p>

				<div style="float: left;">
					<h2>Top up</h2>
					<p>Chucks left: <span id="chucks-left"><?php echo $chucks_left; ?></span></p>

					<form name="_xclick" action="https://www.paypal.com/cgi-bin/webscr" method="post">
						<input type="hidden" name="cmd" value="_xclick">
						<input type="hidden" name="business" value="admin@chuck.lo">
						<input type="hidden" name="currency_code" value="USD">
						<input type="hidden" name="item_name" value="15 Chucks">
						<input type="hidden" name="amount" value="12.99">
						<input type="hidden" name="return_url" value="http://chuck.lo/?chuck-ipn">
						<input type="hidden" name="custom" value="<?php echo wp_get_current_user()->ID; ?>">
						<input type="submit" value="Buy 15 Chucks for $12.99">
					</form>

					<form name="_xclick" action="https://www.paypal.com/cgi-bin/webscr" method="post">
						<input type="hidden" name="cmd" value="_xclick">
						<input type="hidden" name="business" value="admin@chuck.lo">
						<input type="hidden" name="currency_code" value="USD">
						<input type="hidden" name="item_name" value="30 Chucks">
						<input type="hidden" name="amount" value="19.99">
						<input type="hidden" name="return_url" value="http://chuck.lo/?chuck-ipn">
						<input type="hidden" name="custom" value="<?php echo wp_get_current_user()->ID; ?>">
						<input type="submit" value="Buy 30 Chucks for $19.99">
					</form>
				</div>

				<div style="text-align: center; float: left;">
					<img style="height: 127px;" src="<?php echo plugins_url( 'chuck.jpg', __FILE__ ); ?>">
				</div>

				<div style="text-align: center; float: left;">
					<h2>Query dataset</h2>
					<p style="font-size: 120%; background: #eee; padding: 15px; width: 300px;" id="last-result"><?php echo esc_html( $last_result ); ?></p>
					
					<button onclick="Chuck.fetch_one();">Get another fact</button> (costs a chuck)
				</div>

				<div style="clear: both;"></div>
			<?php
		}

		/** Process IPN */
		public function do_ipn() {

			if ( !$this->ipn_is_valid() )
				return;

			$chucks = get_user_meta( $_POST['mc_custom'], 'chucks_left', true );
			if ( !$chucks ) $chucks = 0;
	
			switch( $_POST['mc_gross'] ):
				case '19.99':
					$chucks = $chucks + 30;
					break;
				case '12.99':
					$chucks = $chucks + 15;
					break;
			endswitch;

			update_user_meta( $_POST['mc_custom'], 'chucks_left', $chucks );
		}

		/** Validate the IPN */
		public function ipn_is_valid() {
			if ( $this->is_test_not_live( $_POST['mc_custom'] ) )
				return true;

			return false;
		}

		/** Fetch a Chuck Norris fact via AJAX */
		public function fetch_one_ajax() {

			header( 'Content-Type: application/json' );
			exit( json_encode( $this->fetch_one() ) );
		}

		/** Fetch a Chuck Norris fact via the ICNDB API */
		public function fetch_one() {
			$chucks_left = get_user_meta( wp_get_current_user()->ID, 'chucks_left', true );
			if ( !$chucks_left )
				return array( 'success' => false, 'error' => 'NCL' );

			if ( $this->is_test_not_live( wp_get_current_user()->ID ) ) {
				$response = array( 'body' =>
					json_encode( array( 'type' => 'success', 'value' => array( 'id' => 0, 'joke' => 'Joke' ) ) ) );
			} else {
				$response = call_user_func( $this->remote_get_func, 'http://api.icndb.com/jokes/random' );
			}
			$response = json_decode( $response['body'] );

			if ( $response->type !== 'success' )
				return array( 'success' => false, 'error' => 'NA' );

			update_user_meta( wp_get_current_user()->ID, 'last_result', $response->value->joke );
			update_user_meta( wp_get_current_user()->ID, 'chucks_left', $chucks_left - 1 );

			return array(
				'success' => true,
				'data' => $response->value->joke,
				'chucks' => $chucks_left - 1 );
		}

		/** Sanitize the chucks_left meta values */
		public static function sanitize_chucks_left( $null, $object_id, $meta_key, $single ) {
			if ( $meta_key != 'chucks_left' )
				return $null;

			remove_filter( 'get_user_metadata', array( __CLASS__, 'sanitize_chucks_left' ), null, 4 );
			$meta_value = intval( get_user_meta( $object_id, $meta_key, $single ) );
			add_filter( 'get_user_metadata', array( __CLASS__, 'sanitize_chucks_left' ), null, 4 );

			return $meta_value > 0 ? $meta_value : 0;
		}

		/** Check whether live third-party calls should be made */
		private function is_test_not_live( $user_id ) {
			if ( !defined( 'CHUCK_TESTING' ) )
				return false;
			return !( get_user_meta( $user_id, '_tests_live' ) === array( false ) );
		}
	}

	if ( !defined( 'WP_PLUGIN_DIR' ) )
		exit( 'Who are you? What do you want?' );

	if ( !defined( 'CHUCK_DOING_TESTS' ) )
		new Chuck();
?>
