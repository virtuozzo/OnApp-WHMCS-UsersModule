$( document ).ready( function () {
    var loginDetails = $( "td:contains('" + injTarget + "'):last" );
    if( loginDetails.length ) {
        var tbody = loginDetails.parent().parent();
        tbody.parent().attr( 'id', 'LoginDetails' );
        tbody.append( tbody.html() );

        var server = LANG.onappuserslogintocpserver;
        $( 'table#LoginDetails tr:last td:first' ).html( server );

        server = '<div id="gotocpserver">' + SERVER + '</div>' + $( '#gotocp' ).html();
        $( 'table#LoginDetails tr:last td:last' ).html( server );
    }
    else {
        var target = $( 'ul.tabs li:last' );
        target.before( '<li><a href="#" id="gotocpspan">' + LANG.onappuserslogintocpbutton + '</a></li>' );

        $( '#gotocpspan' ).click( function() {
            $( '#gotocpform' ).submit();
            return false;
        } );
    }
} );