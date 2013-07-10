// Configuration
var BASE_URL = 'http://testpress.lo/';
var USER_ID = null;
var USER_COOKIES = {};

var dump = require( 'utils' ).dump;

casper.test.comment( 'Test all the things related to Chuck, Chuck, Chuck...' );

// Initialize entity
casper.start( BASE_URL + '?passthrough=init&live=false', function( response ) {
	USER_ID = JSON.parse( this.getPageContent() );
} );

// Login
casper.then( function() {
	casper.thenOpen( BASE_URL + '?passthrough=login&id=' + USER_ID );
} );

// Menu
casper.then( function() {
	casper.thenOpen( BASE_URL + 'wp-admin/', function( response ) {
		this.test.assertHttpStatus( 200, 'logged in successfully' );
		this.test.assertEquals( this.evaluate( function() {
			return jQuery( '.wp-menu-name' ).length;
		} ), 3, 'three items in the admin menu' );
		this.test.assertSelectorExists( 'a.toplevel_page_chuck', 'chuck menu exists' );
		this.click( 'a.toplevel_page_chuck' );
	} );
} );

// Check main things
casper.then( function() {
	this.test.assertEquals( this.evaluate( function() {
		return jQuery( '#chucks-left' ).text();
	} ), '0', 'no chucks available' );

	this.test.assertEquals( this.evaluate( function() {
		return jQuery( '#last-result' ).text();
	} ), 'none', 'no last result yet' );
} );

// Test IPN
casper.then( function() {
	casper.thenOpen( BASE_URL + '?chuck-ipn', {
		method: 'post',
		data: { 'mc_custom': USER_ID, 'mc_gross': 19.99 },
	}, function( response ) {
		this.test.assertHttpStatus( 200, 'ipn submitted successfully' );
	
		casper.thenOpen( BASE_URL + 'wp-admin/?page=chuck', function( response ) {
			this.test.assertEquals( this.evaluate( function() {
				return jQuery( '#chucks-left' ).text();
			} ), '30', '30 chucks acquired' );

			this.waitForResource( BASE_URL + 'wp-admin/admin-ajax.php' );
			this.click( 'button' );
		} );
	} );
} );

casper.then( function() {
	this.test.assertEquals( this.evaluate( function() {
		return jQuery( '#chucks-left' ).text();
	} ), '29', '29 chucks left' );

	this.test.assertEquals( this.evaluate( function() {
		return jQuery( '#last-result' ).text();
	} ), 'Joke', 'joke data displayed' );
} );

casper.run( function() {
	this.test.done();
} );
