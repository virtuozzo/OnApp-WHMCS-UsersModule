function buildFields( ServersData ) {
	// Clean up table & store titles
	var table = $( 'table' ).eq( 5 );
	if( $( '#titles-holder' ).length ) {
		var PlansTitle = $( '#titles-holder #plans' ).html();
		var RolesTitle = $( '#titles-holder #roles' ).html();
	}
	else {
		var PlansTitle = table.find( 'tr:first' ).find( 'td' ).eq( 0 ).html();
		var RolesTitle = table.find( 'tr:first' ).find( 'td' ).eq( 2 ).html();
		html = '<div id="titles-holder"><span id="plans">' + PlansTitle;
		html += '</span><span id="roles">' + RolesTitle + '</span></div>';
		table.before( html );
		$( '#titles-holder' ).hide();
	}
	table.find( 'tr' ).remove();

	// if no servers in group
	if( ServersData.NoServers ) {
		html = '<tr><td colspan="2" class="fieldlabel">' + ServersData.NoServers + '</td></tr>';
		table.append( html );
		return;
	}

	// Proceed data and create select lists
	var cnt = 0;
	for( server_id in ServersData ) {
		if( server_id != parseInt( server_id ) ) {
			continue;
		}

		server = ServersData[ server_id ];

		// server name
		html = '<tr><td colspan="2" class="fieldlabel"><b>' + server.Name + '</b></td></tr>';
		table.append( html );

		// billing plans row
		html = '<tr>';
		html += '<td class="fieldlabel">' + PlansTitle + '</td>';
		html += '<td class="fieldlabel" id="plan' + server_id + '"></td></tr>';
		table.find( 'tr:last' ).after( html );

		// roles row
		html = '<tr>';
		html += '<td class="fieldlabel">' + RolesTitle + '</td>';
		html += '<td class="fieldlabel" id="role' + server_id + '" rel="' + server_id + '"></td></tr>';
		table.find( 'tr:last' ).after( html );

		// process biling plans
		if( typeof server.BillingPlans == 'object' ) {
			select = $( '<select rel="1" name="tmp_packageconfigoption' + ++cnt + '"></select>' );

			for( plan_id in server.BillingPlans ) {
				plan = server.BillingPlans[ plan_id ];

				option = new Option( plan, server_id + ':' + plan_id );
				$( select ).append( option );
			}

			// select selected plans
			if( ServersData.Group == $( "select[name$='servergroup']" ).val() ) {
				$( select ).val( server_id + ':' + ServersData.SelectedPlans[ server_id ] );
			}
		}
		else {
			select = server.BillingPlans;
		}
		$( '#plan' + server_id ).html( select );

		// process roles
		if( typeof server.Roles == 'object' ) {
			html = '';
			for( role_id in server.Roles ) {
				html += '<input type="checkbox" rel="2" name="tmp_packageconfigoption'
						+ ++cnt + '" value="' + role_id + '"/> ' + server.Roles[ role_id ] + '<br/>';
			}
			$( '#role' + server_id ).append( html );

			// select selected roles
			if( ServersData.Group == $( "select[name$='servergroup']" ).val() ) {
				chk = ServersData.SelectedRoles[ server_id ];
				for( i in chk ) {
					$( "#role" + server_id + " input[value='" + chk[ i ] + "']" ).attr( 'checked', true );
				}
			}
		}
		else {
			$( '#role' + server_id ).append( server.Roles );
		}
	}

	// inputs for storing selected values
	html = '<tr><td colspan="2" class="fieldlabel">';
	html += '<input type="text" name="packageconfigoption[1]" id="bp2s" value="" />';
	html += '<input type="text" name="packageconfigoption[2]" id="rls2s" value="" />';
	html += '</td></tr>';

	table.append( $( html ) );
	table.find( 'tr:last' ).hide();
	table.find( 'tr' ).eq( 1 ).find( 'td' ).eq( 0 ).css( 'width', 150 );

	// handle storing selected values
	storeSelectedPlans();
	$( "select[name^='tmp_packageconfigoption']" ).bind( 'change', function() {
		storeSelectedPlans();
	} );
	storeSelectedRoles();
	$( "input[name^='tmp_packageconfigoption']" ).bind( 'change', function() {
		storeSelectedRoles();
	} );

	// align dropdown lists
	alignSelects();
}

function storeSelectedPlans() {
	var tmp = {};
	$( "select[name^='tmp_packageconfigoption']" ).each( function( i, val ) {
		if( typeof tmp[ $( val ).attr( 'rel' ) ] == 'undefined' ) {
			tmp[ $( val ).attr( 'rel' ) ] = val.value + ',';
		}
		else {
			tmp[ $( val ).attr( 'rel' ) ] += val.value + ',';
		}
	} );

	for( i in tmp ) {
		values = tmp[ i ].substring( 0, tmp[ i ].length - 1 );
		$( "input[name^='packageconfigoption[" + i + "]']" ).val( values );
	}
}

function storeSelectedRoles() {
	var tmp = {};
	$( "input[name^='tmp_packageconfigoption']" ).each( function( i, val ) {
		if( !$( val ).attr( 'checked' ) ) {
			return;
		}
		index = $( val ).parents( 'td' ).attr( 'rel' );
		if( typeof tmp[ index ] == 'undefined' ) {
			tmp[ index ] = '';
		}

		tmp[ index ] += val.value + ',';
	} );

	var val = '';
	for( i in tmp ) {
		val += i + ':[' + tmp[ i ].substring( 0, tmp[ i ].length - 1 ) + '],';
	}
	$( "input[name^='packageconfigoption[" + 2 + "]']" ).val( val.substring( 0, val.length - 1 ) );
}

function alignSelects() {
	if( !$( "select[name='servertype']:visible" ).length ) {
		return;
	}

	var max = 0;
	$( "select[name^='tmp_packageconfigoption']" ).each( function( i, val ) {
		width = $( val ).width();
		if( width > max ) {
			max = width + 30;
		}
	} );
	$( 'div#tab2box select' ).css( 'min-width', max );
}

$( document ).ready( function() {
	// Refresh data if server group was changed
	$( "select[name$='servergroup']" ).bind( 'change', function () {
		$( 'table' ).eq( 5 ).find( 'tr:first td' ).html( LANG_LOADING );
		$.ajax( {
			url: document.location.href,
			data: 'servergroup=' + this.value,
			success: function( data ) {
				data = 'data = ' + data;
				eval( data );
				buildFields( data );
			}
		} );
	} );

	// fill the table with datas
	buildFields( ServersData );

	$( 'li#tab2' ).bind( 'click', function() {
		alignSelects();
	} );
} );