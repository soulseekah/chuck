var Chuck = {
	fetch_one: function() {
		jQuery.post( ajaxurl, { 'action': 'chuck_fetch_one' },
			function( data ) {
				if ( data.success ) {
					jQuery( '#chucks-left' ).html( data.chucks );
					jQuery( '#last-result' ).html( data.data );
				} else {
					alert( 'Error fetch data: ' + data.error );
				}
			}
		);
	},
};
