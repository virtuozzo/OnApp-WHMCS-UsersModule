$( document ).ready( function () {
	var tbody = $( "td:contains('" + injTarget + "'):last" ).parent().parent();
	tbody.parent().attr( 'id', 'LoginDetails' );
	tbody.append( tbody.html() );

	var server = LANG.onappuserslogintocpserver;
	$( 'table#LoginDetails tr:last td:first' ).html( server );

	server = '<div id="gotocpserver">' + SERVER + '</div>' + $( '#gotocp' ).html();
	$( 'table#LoginDetails tr:last td:last' ).html( server );
} );
