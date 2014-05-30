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

	// open CP
	$( '#gotocp button' ).on( 'click', function() {
		var win = window.open( '', 'OnAppCP', 'width=980,height=850,resizeable,scrollbars,location=no' );
		$( '#gotocp form' ).attr( 'target', 'OnAppCP' );
		$( '#gotocp form' ).submit();
		win.focus();
	} );

	// ajax
	$( '#stat_data button' ).on( 'click', function() {
		$( 'tr#error' ).hide();
		$( '#stat_data tbody' ).hide();
		var btn = $( this );
		btn.button( 'loading' );

		$.ajax( {
			url: document.location.href,
			data: {
				getstat: 1,
				modop: 'custom',
				ac: 'OutstandingDetails',
				start: $( '#datetimepicker1 input' ).val(),
				end: $( '#datetimepicker2 input' ).val(),
				tz_offset: function() {
					var myDate = new Date();
					offset = myDate.getTimezoneOffset();
					return offset;
				}
			},
			error: function() {
				$( 'tr#error' ).show();
			},
			success: function( data ) {
				data = JSON.parse( data );
				processData( data );
			}
		} ).always( function() {
			btn.button( 'reset' )
		} );
	} );
	$( '#stat_data button' ).click();
} );

function processData( data ) {
	if( data ) {
		for( i in data ) {
			val = accounting.formatMoney( data[ i ], {symbol: data.currency_code, format: "%v %s"} );
			$( '#' + i ).text( val );
		}
		$( '#stat_data tbody' ).show();
	}
	else {
		$( 'tr#error' ).show();
	}
}