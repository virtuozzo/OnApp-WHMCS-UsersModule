function buildFields( ServersData ) {
	// Clean up table & store titles
	var table = $( 'table' ).eq( 5 );
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
		html = '<tr><td colspan="2" class="fieldarea"><b>' + server.Name + '</b></td></tr>';
		table.append( html );

		// if no servers in group
		if( ServersData[ server_id ].NoAddress ) {
			html = '<tr><td colspan="2" class="fieldlabel">' + ServersData[ server_id ].NoAddress + '</td></tr>';
			table.append( html );
			continue;
		}

		// billing plans row
		html = '<tr>';
		html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersbindingplanstitle + '</td>';
		html += '<td class="fieldarea" id="plan' + server_id + '"></td></tr>';
		table.find( 'tr:last' ).after( html );

		// roles row
		html = '<tr>';
		html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersbindingrolestitle + '</td>';
		html += '<td class="fieldarea" id="role' + server_id + '" rel="' + server_id + '"></td></tr>';
		table.find( 'tr:last' ).after( html );

		// TZs row
		html = '<tr>';
		html += '<td class="fieldlabel">' + ONAPP_LANG.onappuserstimezonetitle + '</td>';
		html += '<td class="fieldarea" id="tz' + server_id + '" rel="' + server_id + '"></td></tr>';
		table.find( 'tr:last' ).after( html );

		// user groups row
		html = '<tr>';
		html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersusergroupstitle + '</td>';
		html += '<td class="fieldarea" id="usergroups' + server_id + '"></td></tr>';
		table.find( 'tr:last' ).after( html );

		// locale row
		html = '<tr>';
		html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersbindinglocaletitle + '</td>';
		html += '<td class="fieldarea" id="locale' + server_id + '"></td></tr>';
		table.find( 'tr:last' ).after( html );

		// stat row
		html = '<tr>';
		html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersshowstatistic + '</td>';
		html += '<td class="fieldarea" id="showstat' + server_id + '"></td></tr>';
		table.find( 'tr:last' ).after( html );

		// pass taxes row
		html = '<tr>';
		html += '<td class="fieldlabel">' + ONAPP_LANG.onappuserspassthrutaxes + '</td>';
		html += '<td class="fieldarea" id="passtaxes' + server_id + '"></td></tr>';
		table.find( 'tr:last' ).after( html );

		// show control panel row
		html = '<tr>';
		html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersshowcontrolpanel + '</td>';
		html += '<td class="fieldarea" id="controlpanel' + server_id + '"></td></tr>';
		table.find( 'tr:last' ).after( html );

		// process biling plans
		if( typeof server.BillingPlans == 'object' ) {
			select = $( '<select name="bills_packageconfigoption' + ++cnt + '"></select>' );

			for( plan_id in server.BillingPlans ) {
				plan = server.BillingPlans[ plan_id ];

				$( select ).append( $( '<option>', { value : server_id + ':' + plan_id } ).text( plan ) );
			}

			// select selected plans
			if( ServersData.SelectedPlans ) {
				if( ServersData.Group == $( "select[name$='servergroup']" ).val() ) {
					$( select ).val( server_id + ':' + ServersData.SelectedPlans[ server_id ] );
				}
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
				html += '<input type="checkbox" name="roles_packageconfigoption'
						+ ++cnt + '" value="' + role_id + '"/> ' + server.Roles[ role_id ] + '<br/>';
			}
			$( '#role' + server_id ).append( html );

			// select selected roles
			if( ServersData.SelectedRoles ) {
				if( ServersData.Group == $( "select[name$='servergroup']" ).val() ) {
					chk = ServersData.SelectedRoles[ server_id ];
					for( i in chk ) {
						$( "#role" + server_id + " input[value='" + chk[ i ] + "']" ).attr( 'checked', true );
					}
				}
			}
		}
		else {
			$( '#role' + server_id ).append( server.Roles );
		}

		// process TZs
		select = $( '<select name="tzs_packageconfigoption' + ++cnt + '"></select>' );
		select = select.html( OnAppUsersTZs );
		$( '#tz' + server_id ).html( select );
		// select selected TZ
		if( ServersData.SelectedTZs ) {
			if( ServersData.Group == $( "select[name$='servergroup']" ).val() ) {
				$( select ).val( ServersData.SelectedTZs[ server_id ] );
			}
		}

		// process user groups
		if( typeof server.UserGroups == 'object' ) {
			select = $( '<select name="usergroups_packageconfigoption' + ++cnt + '"></select>' );

			for( group_id in server.UserGroups ) {
				plan = server.UserGroups[ group_id ];

				$( select ).append( $( '<option>', { value : server_id + ':' + group_id } ).text( plan ) );
			}

			// select selected plans
			if( ServersData.SelectedUserGroups ) {
				if( ServersData.Group == $( "select[name$='servergroup']" ).val() ) {
					$( select ).val( server_id + ':' + ServersData.SelectedUserGroups[ server_id ] );
				}
			}
		}
		else {
			select = server.UserGroups;
		}
		$( '#usergroups' + server_id ).html( select );

		// process locale
		if( typeof server.Locales == 'object' && Object.size( server.Locales ) ) {
			select = $( '<select name="locale_packageconfigoption' + ++ cnt + '"></select>' );

			for( code in server.Locales ) {
				locale = server.Locales[ code ];

				$( select ).append( $( '<option>', { value: server_id + ':' + code } ).text( locale ) );
			}

			// select selected locale
			if( ServersData.SelectedLocales ) {
				if( ServersData.Group == $( "select[name$='servergroup']" ).val() ) {
					$( select ).val( server_id + ':' + ServersData.SelectedLocales[ server_id ] );
				}
			}
		}
        else {
            select = $( '<input name="locale_packageconfigoption' + ++ cnt + '" rel="' + server_id + '" />' );
            // select selected locale
            if( ServersData.SelectedLocales && ServersData.SelectedLocales[ server_id ] ) {
                $( select ).val( ServersData.SelectedLocales[ server_id ] );
            }
            else {
                $( select ).val( 'en' );
            }
        }
		$( '#locale' + server_id ).html( select );

		// process show statistic
		input = $( '<input name="stat_packageconfigoption' + ++cnt + '" rel="' + server_id + '" type="checkbox" />' );
		// checkbox state
		if( ServersData.ShowStat ) {
			if( ServersData.ShowStat[ server_id ] ) {
				$( input ).attr( 'checked', true );
			}
		}
		$( '#showstat' + server_id ).html( input );

		// process passthru taxes to OnApp
		input = $( '<input name="passtaxes_packageconfigoption' + ++cnt + '" rel="' + server_id + '" type="checkbox" />' );
		// checkbox state
		if( ServersData.PassTaxes ) {
			if( ServersData.PassTaxes[ server_id ] ) {
				$( input ).attr( 'checked', true );
			}
		}
		$( '#passtaxes' + server_id ).html( input );

		// process show control panel
		input = $( '<input name="controlpanel_packageconfigoption' + ++cnt + '" rel="' + server_id + '" type="checkbox" />' );
		// checkbox state
		if( ServersData.ShowControlPanel ) {
			if( ServersData.ShowControlPanel[ server_id ] ) {
				$( input ).attr( 'checked', true );
			}
		}
		$( '#controlpanel' + server_id ).html( input );
	}

	// input for storing selected values
	html = '<tr><td colspan="2" class="fieldlabel">';
	html += '<input type="text" name="packageconfigoption[1]" id="bp2s" value="" size="200" />';
	html += '</td></tr>';

	table.append( $( html ) );
	table.find( 'tr:last' ).hide();
	table.find( 'tr' ).eq( 1 ).find( 'td' ).eq( 0 ).css( 'width', 150 );

	// handle storing selected values
	storeSelectedPlans();
	$( "select[name^='bills_packageconfigoption']" ).bind( 'change', function() {
		storeSelectedPlans();
	} );
	storeSelectedRoles();
	$( "input[name^='roles_packageconfigoption']" ).bind( 'change', function() {
		storeSelectedRoles();
	} );
	storeSelectedTZs();
	$( "select[name^='tzs_packageconfigoption']" ).bind( 'change', function() {
		storeSelectedTZs();
	} );
	storeSelectedUserGroups();
	$( "select[name^='usergroups_packageconfigoption']" ).bind( 'change', function() {
		storeSelectedUserGroups();
	} );
	storeSelectedLocales();
	$( "select[name^='locale_packageconfigoption']" ).bind( 'change', function() {
		storeSelectedLocales();
	} );
	storeShowStat();
	$( "input[name^='stat_packageconfigoption']" ).bind( 'change', function() {
		storeShowStat();
	} );
	storePassTaxes();
	$( "input[name^='passtaxes_packageconfigoption']" ).bind( 'change', function() {
		storePassTaxes();
	} );
	storeShowControlPanel();
	$( "input[name^='controlpanel_packageconfigoption']" ).bind( 'change', function() {
		storeShowControlPanel();
	} );

	// align dropdown lists
	alignSelects();
}

var OnAppUsersData = {
	SelectedPlans: {},
	SelectedRoles: {},
	SelectedTZs: {},
	SelectedUserGroups: {},
	SelectedLocales: {},
	ShowStat: {},
    PassTaxes: {},
	ShowControlPanel: {}
};

function storeShowControlPanel() {
	$( "input[name^='controlpanel_packageconfigoption']" ).each( function( i, val ) {
		var index = $( val ).attr( 'rel' );
		OnAppUsersData.ShowControlPanel[ index ] = $( val ).attr( 'checked' ) ? 1 : 0;
	} );

	$( "input[name^='packageconfigoption[1]']" ).val( objectToString( OnAppUsersData ) );
}

function storePassTaxes() {
	$( "input[name^='passtaxes_packageconfigoption']" ).each( function( i, val ) {
		var index = $( val ).attr( 'rel' );
		OnAppUsersData.PassTaxes[ index ] = $( val ).attr( 'checked' ) ? 1 : 0;
	} );

	$( "input[name^='packageconfigoption[1]']" ).val( objectToString( OnAppUsersData ) );
}

function storeShowStat() {
	$( "input[name^='stat_packageconfigoption']" ).each( function( i, val ) {
		var index = $( val ).attr( 'rel' );
		OnAppUsersData.ShowStat[ index ] = $( val ).attr( 'checked' ) ? 1 : 0;
	} );

	$( "input[name^='packageconfigoption[1]']" ).val( objectToString( OnAppUsersData ) );
}

function storeSelectedLocales() {
    if( $( "select[name^='locale_packageconfigoption']" ).length ) {
        $( "select[name^='locale_packageconfigoption']" ).each( function ( i, val ) {
            var tmp = val.value.split( ':' );
            OnAppUsersData.SelectedLocales[ tmp[ 0 ] ] = tmp[ 1 ];
        } );
    }
    else {
        $( "input[name^='locale_packageconfigoption']" ).each( function ( i, val ) {
            var index = $( val ).attr( 'rel' );
            OnAppUsersData.SelectedLocales[ index ] = val.value;
        } );
    }

	$( "input[name^='packageconfigoption[1]']" ).val( objectToString( OnAppUsersData ) );
}

function storeSelectedPlans() {
	$( "select[name^='bills_packageconfigoption']" ).each( function( i, val ) {
		var tmp = val.value.split( ':' );
		OnAppUsersData.SelectedPlans[ tmp[ 0 ] ] = tmp[ 1 ];
	} );

	$( "input[name^='packageconfigoption[1]']" ).val( objectToString( OnAppUsersData ) );
}

function storeSelectedRoles() {
	OnAppUsersData.SelectedRoles = {};
	$( "input[name^='roles_packageconfigoption']" ).each( function( i, val ) {
		if( !$( val ).attr( 'checked' ) ) {
			return;
		}
		var index = $( val ).parents( 'td' ).attr( 'rel' );

		if( typeof OnAppUsersData.SelectedRoles[ index ] == 'undefined' ) {
			OnAppUsersData.SelectedRoles[ index ] = [];
		}

		if( jQuery.inArray( val.value, OnAppUsersData.SelectedRoles[ index ] ) == -1 ) {
			OnAppUsersData.SelectedRoles[ index ].push( val.value );
		}
	} );

	$( "input[name^='packageconfigoption[1]']" ).val( objectToString( OnAppUsersData ) );
}

function storeSelectedTZs() {
	$( "select[name^='tzs_packageconfigoption']" ).each( function( i, val ) {
		var index = $( val ).parents( 'td' ).attr( 'rel' );
		OnAppUsersData.SelectedTZs[ index ] = val.value;
	} );

	$( "input[name^='packageconfigoption[1]']" ).val( objectToString( OnAppUsersData ) );
}

function storeSelectedUserGroups() {
	$( "select[name^='usergroups_packageconfigoption']" ).each( function( i, val ) {
		var tmp = val.value.split( ':' );
		OnAppUsersData.SelectedUserGroups[ tmp[ 0 ] ] = tmp[ 1 ];
	} );

	$( "input[name^='packageconfigoption[1]']" ).val( objectToString( OnAppUsersData ) );
}

function alignSelects() {
	if( !$( "select[name='servertype']:visible" ).length ) {
		return;
	}

	var max = 0;
	$( 'div#tab2box select' ).each( function( i, val ) {
		width = $( val ).width();
		if( width > max ) {
			max = width + 30;
		}
	} );
	$( 'div#tab2box select' ).css( 'min-width', max );
	$( "div#tab2box input[name^='locale_packageconfigoption']" ).css( 'min-width', max - 5 );
}

$( document ).ready( function() {
	// Refresh data if server group was changed
	$( "select[name$='servergroup']" ).bind( 'change', function () {
		$( 'table' ).eq( 5 ).find( 'tr:first td' ).html( ONAPP_LANG.onappusersjsloadingdata );
		$.ajax( {
			url: document.location.href,
			data: 'servergroup=' + this.value,
			success: function( data ) {
				buildFields( jQuery.evalJSON( data ) );
			}
		} );
	} );

	// fill the table with datas
	buildFields( ServersData );

	$( 'li#tab2' ).bind( 'click', function() {
		alignSelects();
	} );
} );

function objectToString( o ) {
	return jQuery.toJSON( o );
}

Object.size = function ( obj ) {
	var size = 0, key;
	for( key in obj ) {
		if( obj.hasOwnProperty( key ) ) {
			size ++;
		}
	}
	return size;
};