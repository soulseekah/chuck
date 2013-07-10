<?php
	class Tests_Chuck_Main extends WP_UnitTestCase {
		
		private $chuck;
		private $user_id;

		/** Activate the plugin, mock all the things */
		public function setUp() {
			parent::setUp();

			$mocked = array( 'ipn_is_valid' );

			$this->chuck = $this->getMock( 'Chuck', $mocked );

			$this->chuck->activate();

			$user = wp_create_user( 'walker', 'walker', 'random@localhost' );
			$user = get_user_by( 'id', $user );
			$user->set_role( 'ranger' );

			$this->user_id = $user->ID;
		}

		/** Tests plugin activation, makes sure the roles are enabled */
		public function test_activation_state() {
			$this->assertInstanceOf( 'WP_Role', get_role( 'ranger' ) );
		}

		/** Test an IPN arriving and increasing the user's chucks */
		public function test_do_ipn() {
			$this->chuck->expects( $this->any() )
				->method( 'ipn_is_valid' )
				->will( $this->returnValue( true ) );

			$_POST['mc_custom'] = $this->user_id;
			$_POST['mc_gross'] = '19.99';

			$this->chuck->do_ipn();
			$this->assertEquals( get_user_meta( $this->user_id, 'chucks_left', true ), 30 );
		}

		/** Test a fact being fetched, and user's chucks being decreased */
		public function test_fetch_one() {
			update_user_meta( $this->user_id, 'chucks_left', 5 );
			$fact = array( 'type' => 'success', 'value' => array( 'id' => 0, 'joke' => 'Joke' ) );

			$getter = $this->getMock( 'stdClass', array( 'wp_remote_get' ) );
			$getter->expects( $this->any() )
				->method( 'wp_remote_get' )
				->will( $this->returnValue( array( 'body' => json_encode( $fact ) ) ) );
			$this->chuck->remote_get_func = array( $getter, 'wp_remote_get' );

			wp_set_current_user( $this->user_id );
			$response = $this->chuck->fetch_one();

			$this->assertTrue( $response['success'] );
			$this->assertEquals( $response['chucks'], 4 );
			$this->assertEquals( $response['data'], $fact['value']['joke'] );
			$this->assertEquals( get_user_meta( $this->user_id, 'chucks_left', true ), 4 );
		}

		/** Test exhausting the chucks */
		public function test_exhaust_fetch() {
			update_user_meta( $this->user_id, 'chucks_left', 0 );
			$fact = array( 'type' => 'success', 'value' => array( 'id' => 0, 'joke' => 'Joke' ) );

			$getter = $this->getMock( 'stdClass', array( 'wp_remote_get' ) );
			$getter->expects( $this->any() )
				->method( 'wp_remote_get' )
				->will( $this->returnValue( array( 'body' => json_encode( $fact ) ) ) );
			$this->chuck->remote_get_func = array( $getter, 'wp_remote_get' );

			wp_set_current_user( $this->user_id );
			$response = $this->chuck->fetch_one();

			$this->assertFalse( $response['success'] );
			$this->assertEquals( $response['error'], 'NCL' );
			$this->assertEquals( get_user_meta( $this->user_id, 'chucks_left', true ), 0 );
		}

		/** Test edge case as the chucks */
		/** @group invalid */
		public function test_invalid_chucks() {
			$fact = array( 'type' => 'success', 'value' => array( 'id' => 0, 'joke' => 'Joke' ) );

			$getter = $this->getMock( 'stdClass', array( 'wp_remote_get' ) );
			$getter->expects( $this->any() )
				->method( 'wp_remote_get' )
				->will( $this->returnValue( array( 'body' => json_encode( $fact ) ) ) );
			$this->chuck->remote_get_func = array( $getter, 'wp_remote_get' );

			/** Non-logged in */
			$response = $this->chuck->fetch_one();
			$this->assertFalse( $response['success'] );
			$this->assertEquals( $response['error'], 'NCL' );

			wp_set_current_user( $this->user_id );

			/** Negative */
			update_user_meta( $this->user_id, 'chucks_left', -1 );
			$response = $this->chuck->fetch_one();
			$this->assertFalse( $response['success'] );
			$this->assertEquals( $response['error'], 'NCL' );

			/** Non-int */
			update_user_meta( $this->user_id, 'chucks_left', 'undefined' );
			$response = $this->chuck->fetch_one();
			$this->assertFalse( $response['success'] );
			$this->assertEquals( $response['error'], 'NCL' );
			
			/* Not exists */
			delete_user_meta( $this->user_id, 'chucks_left' );
			$response = $this->chuck->fetch_one();
			$this->assertFalse( $response['success'] );
			$this->assertEquals( $response['error'], 'NCL' );
		}
	}
?>
