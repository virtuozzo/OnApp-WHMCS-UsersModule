function buildFields( ServersData ) {
    // Clean up table & store titles
    var table = $( 'table' ).eq( 5 );
    if( $( '#titles-holder' ).length ) {
        var PlansTitle = $( '#titles-holder #plans' ).html();
        var RolesTitle = $( '#titles-holder #roles' ).html();
        var TZsTitle = $( '#titles-holder #tzs' ).html();
    }
    else {
        var PlansTitle = table.find( 'tr:first' ).find( 'td' ).eq( 0 ).html();
        var RolesTitle = table.find( 'tr:first' ).find( 'td' ).eq( 2 ).html();
        var TZsTitle = table.find( 'td' ).eq( 4 ).html();
        html = '<div id="titles-holder"><span id="plans">' + PlansTitle + '</span>';
        html += '<span id="roles">' + RolesTitle + '</span>';
        html += '<span id="tzs">' + TZsTitle + '</span></div>';
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

        // TZs row
        html = '<tr>';
        html += '<td class="fieldlabel">' + TZsTitle + '</td>';
        html += '<td class="fieldlabel" id="tz' + server_id + '" rel="' + server_id + '"></td></tr>';
        table.find( 'tr:last' ).after( html );

        // process biling plans
        if( typeof server.BillingPlans == 'object' ) {
            select = $( '<select name="bills_packageconfigoption' + ++cnt + '"></select>' );

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
                html += '<input type="checkbox" name="roles_packageconfigoption'
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

        // process TZs
        select = $( '<select name="tzs_packageconfigoption' + ++cnt + '"></select>' );
        select = select.html( OnAppUsersTZs );
        $( '#tz' + server_id ).html( select );
        // select selected TZ
        if( ServersData.Group == $( "select[name$='servergroup']" ).val() ) {
            $( select ).val( ServersData.SelectedTZs[ server_id ] );
        }
    }

    // inputs for storing selected values
    html = '<tr><td colspan="2" class="fieldlabel">';
    html += '<input type="text" name="packageconfigoption[1]" id="bp2s" value="" size="150" />';
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

    // align dropdown lists
    alignSelects();
}

var OnAppUsersData = {
    SelectedPlans: {},
    SelectedRoles: {},
    SelectedTZs: {}
};

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

function objectToString( o ) {
    return jQuery.toJSON( o );


    var parse = function( _o ) {
        var a = [], t;
        for( var p in _o ) {
            if( _o.hasOwnProperty( p ) ) {
                t = _o[p];
                if( t && typeof t == "object" ) {
                    if( t instanceof Array ) {
                        a[a.length] = p + ":[" + t.join( ", " ) + "]";
                    }
                    else {
                        a[a.length] = p + ":{" + arguments.callee( t ).join( ", " ) + "}";
                    }
                }
                else {
                    if( typeof t == "string" ) {
                        a[a.length] = [ p + ": \"" + t.toString() + "\"" ];
                    }
                    else {
                        a[a.length] = [ p + ": " + t.toString()];
                    }
                }
            }
        }
        return a;
    }

    return parse( o ).join( ", " );
    //return "{" + parse( o ).join( ", " ) + "}";
}