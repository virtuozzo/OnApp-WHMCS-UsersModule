$( document ).ready( function() {
	// generate new password
	$( '#tab2 form' ).remove();
	$( '#gotocp button:last' ).on( 'click', function() {
		$( '#gotocp .alert' ).hide();
		var btn = $( this );
		btn.button( 'loading' );

		$.ajax( {
			url: document.location.href,
			data: {
				modop: 'custom',
				a: 'GeneratePassword'
			},
			error: function( ) {
				$( '#gotocp span' ).html( 'General Issue' );
				$( '#gotocp .alert-error' ).show( 'fast' );
				setTimeout( function() {
					$( '#gotocp .alert' ).hide( 'fast' );
				}, 2000 );
			},
			success: function( data ) {
				if( data === 'success' ) {
					$( '#gotocp .alert-success' ).show( 'fast' );
				}
				else {
					$( '#gotocp span' ).html( data );
					$( '#gotocp .alert-error' ).show( 'fast' );
				}
				setTimeout( function() {
					$( '#gotocp .alert' ).hide( 'fast' );
				}, 5000 );
			}
		} ).always( function() {
			btn.button( 'reset' );
		} );
	} );
} );

$( document ).ready( function() {
	// set datetime pickers
	$( '#datetimepicker1' ).datetimepicker( {
		language: 'en',
		pickSeconds: false
	} );
	$( '#datetimepicker2' ).datetimepicker( {
		language: 'en',
		pickSeconds: false
	} );
	$( '#datetimepicker2 input' ).val( moment().format( 'YYYY-MM-DD HH:mm' ) );
	$( '#datetimepicker1 input' ).val( moment().subtract( 'days', 2 ).format( 'YYYY-MM-DD HH:mm' ) );

	// ajax
	$( '#stat_data button' ).on( 'click', function() {
		$( 'tr#error' ).hide();
		$( '#stat_data tbody' ).fadeTo( 'fast', 0.1 );
		var btn = $( this );
		btn.button( 'loading' );

		$.ajax( {
			url: document.location.href,
			data: {
				getstat: 1,
				modop: 'custom',
				a: 'OutstandingDetails',
				start: $( '#datetimepicker1 input' ).val(),
				end: $( '#datetimepicker2 input' ).val(),
				tz_offset: function() {
					var myDate = new Date();
					offset = myDate.getTimezoneOffset();
					return offset;
				}
			},
			error: function() {
				$( '#stat_data tbody' ).hide();
				$( 'tr#error' ).show();
			},
			success: function( data ) {
				data = JSON.parse( data );
				processData( data );
			}
		} ).always( function() {
			btn.button( 'reset' );
		} );
	} );
	$( '#stat_data button' ).click();
} );

function processData( data ) {
	if( data ) {
		for( i in data ) {
			if( ( data.hideZeroEntries == 'on' ) && ( i != 'total_cost' ) ) {
				if( data[i] ) {
					val = accounting.formatMoney( data[i], {symbol: data.currency_code, format: "%v %s"} );
					$( '#' + i ).text( val );
					$( '#' + i ).parent().show();
				}
				else {
					$( '#' + i ).parent().hide();
				}
			}
			else {
				val = accounting.formatMoney( data[i], {symbol: data.currency_code, format: "%v %s"} );
				$( '#' + i ).text( val );
				$( '#' + i ).parent().show();
			}
		}
		$( '#stat_data tbody' ).fadeTo( 'fast', 1 );
	}
	else {
		$( '#stat_data tbody' ).hide();
		$( 'tr#error' ).show();
	}
}