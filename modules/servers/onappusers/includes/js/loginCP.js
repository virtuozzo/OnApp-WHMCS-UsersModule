$( document ).ready( function () {
    // generate new password
    $( '#gotocp button:last' ).on( 'click', function() {
        var url = document.location.href;
        url += '&modop=custom&a=GeneratePassword';
        document.location.href = url;
    } );

    // reload page after password generation
    if( document.location.href.indexOf( '&a=GeneratePassword' ) > 0 ) {
        var url = document.location.href;
        url = url.replace( '&modop=custom', '' );
        url = url.replace( '&a=GeneratePassword', '' );
        setTimeout( function() {
            document.location.href = url;
        }, 2000 );
    }
} );