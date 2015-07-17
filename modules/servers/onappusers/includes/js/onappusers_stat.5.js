$( document ).ready( function() {
    $( '#stat_data tbody' ).css( {opacity: 0.1} );

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
            val = accounting.formatMoney( data[ i ], {symbol: data.currency_code, format: "%v %s"} );
            $( '#' + i ).text( val );
        }
        $( '#stat_data tbody' ).fadeTo( 'fast', 1 );
    }
    else {
        $( '#stat_data tbody' ).hide();
        $( 'tr#error' ).show();
    }
}