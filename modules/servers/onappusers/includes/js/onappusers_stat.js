$( document ).ready( function() {
	$( '#stat_data div' ).hide();

	$( '#stat-nav a' ).click( function() {
		$( '#stat-nav a' ).removeClass( 'stat-nav-bold' );
		$( this ).addClass( 'stat-nav-bold' );
		$( '#stat_data div' ).hide();
		$( '#stat_data div#stat-' + $( this ).attr( 'rel' ) ).show();
		return false;
	} );

	$( '#stat-pages select' ).live( 'change', function () {
		page = $( this ).val();
		$( 'input#get-stat' ).click();
	} );

	var now = new Date();
	var end = now.getFullYear() + '-';
	end += ( ( now.getMonth() < 9 ) ? '0' + ( now.getMonth() + 1 ) : ( now.getMonth() + 1 ) ) + '-';
	end += ( now.getDate() < 10 ) ? '0' + now.getDate() : now.getDate();
	now.setDate( now.getDate() - 7 );

	var hr = now.getHours();
	if( hr < 10 ) {
		hr = '0' + hr;
	}

	var start = now.getFullYear() + '-';
	start += ( ( now.getMonth() < 9 ) ? '0' + ( now.getMonth() + 1 ) : ( now.getMonth() + 1 ) ) + '-';
	start += ( now.getDate() < 10 ) ? '0' + now.getDate() : now.getDate();
	$( 'input#start' ).val( start );
	$( 'input#end' ).val( end );

	var PGN = false;
	$( 'input#get-stat' ).bind( 'click', function() {
		$( 'span#loading' ).css( 'visibility', 'visible' );
		if( PGN ) {
			PGN = false;
		}
		else {
			page = 1;
		}

		$.ajax( {
			url: document.location.href,
			data: {
				getstat: 1,
				modop: 'custom',
				ac: 'OutstandingDetails',
				start: $( 'input#start' ).val() + ' ' + $( 'select#start-time' ).val() + ':00:00',
				end: $( 'input#end' ).val() + ' ' + $( 'select#end-time' ).val() + ':00:00',
				page: page,
				id: PID
			},
			success: function( data ) {
				data = 'data = ' + data;
				eval( data );
				processData( data );
				processPGN( data );
				$( 'span#loading' ).css( 'visibility', 'hidden' );
			}
		} );
	} );

	$( '.sel_imul' ).live( 'click', function () {
		$( '.sel_imul' ).removeClass( 'act' );
		$( this ).addClass( 'act' );

		if( $( this ).children( '.sel_options' ).is( ':visible' ) ) {
			$( '.sel_options' ).hide();
		}
		else {
			$( '.sel_options' ).hide();
			$( this ).children( '.sel_options' ).show();
		}
	} );


	$( '.sel_option' ).live( 'click', function () {
		//меняем значение на выбранное
		var tektext = $( this ).html();
		$( this ).parent( '.sel_options' ).parent( '.sel_imul' ).children( '.sel_selected' ).children( '.selected-text' ).html( tektext );

		//активируем текущий
		$( this ).parent( '.sel_options' ).children( '.sel_option' ).removeClass( 'sel_ed' );
		$( this ).addClass( 'sel_ed' );

		//устанавливаем значение для селекта
		var tekval = $( this ).attr( 'value' );
		tekval = typeof(tekval) != 'undefined' ? tekval : tektext;
		var select = $( this ).parent( '.sel_options' ).parent( '.sel_imul' ).parent( '.sel_wrap' ).children( 'select' );
		select.children( 'option' ).removeAttr( 'selected' ).each( function () {
			if( $( this ).val() == tekval ) {
				$( this ).attr( 'selected', 'select' );
			}
		} );
		if( select.attr( 'rel' ) == 'pages' ) {
			if( tekval != page ) {
				PGN = true;
				$( '#stat-pages select' ).change();
			}
		}
	} );

	var selenter = false;
	$( '.sel_imul' ).live( 'mouseenter', function () {
		selenter = true;
	} );
	$( '.sel_imul' ).live( 'mouseleave', function () {
		selenter = false;
	} );
	$( document ).click( function () {
		if( !selenter ) {
			$( '.sel_options' ).hide();
			$( '.sel_imul' ).removeClass( 'act' );
		}
	} );

	$( "#end" ).datepicker( { dateFormat:'yy-mm-dd' } );
	$( "#start" ).datepicker( { dateFormat:'yy-mm-dd' } );

	reselect( '#start-time', 'sec overf' );
	reselect( '#end-time', 'sec overf' );
	var INT = setInterval( function () {
		if( $( 'div.sel_option' ).length = 48 ) {
			$( 'div.sel_option[value="' + hr + '"]' ).click();
			clearInterval( INT );
			$( 'input#get-stat' ).click();
		}
	}, 50 );
} );

function processData( data ) {
	total = number_format( data.total_amount, 2, '.', ' ' );

	data = data.stat;
	$( 'div[id^="stat-"]' ).hide();
	$( '#stat-warning' ).remove();
	if( data.length == 0 ) {
		var html = '<tr id="stat-warning"><td style="text-align: center; font-weight: bold;">' + LANG.onappusersstatnodata + '</td></tr>';
		$( '#stat-nav' ).hide();
		$( '#stat_data' ).hide();
		$( 'table.userstat tr:first' ).after( html );
		return;
	}


	var CUR = LANG.onappusersstatcurrency;
	var table_vms = $( 'div#stat-vms tr:first' );
	var table_disks = $( 'div#stat-disks tr:first' );
	var table_nets = $( 'div#stat-nets tr:first' );
	table_vms.nextAll().remove();
	table_disks.nextAll().remove();
	table_nets.nextAll().remove();

	//var total = 0;
	var html_vms = '';
	var html_disks = '';
	var html_nets = '';
	var cost_vm = cost_disk = cost_net = 0;
	for( i in data ) {
		tmp = data[ i ];
		var date = tmp.date.substr( 0, 16 );

		// process vm
		cst = parseFloat( tmp.cpus_cost ) + parseFloat( tmp.cpu_shares_cost ) + parseFloat( tmp.cpu_usage_cost ) + parseFloat( tmp.memory_cost ) + parseFloat( tmp.template_cost );
		cost_vm += cst;

		html_vms += '<tr>';
		html_vms += '<td>' + date + '</td>';
		html_vms += '<td>' + tmp.label + '</td>';
		html_vms += '<td>' + CUR[ tmp.currency ] + number_format( tmp.usage_cost, 2, '.', ' ' ) + '</td>';
		html_vms += '<td>' + CUR[ tmp.currency ] + number_format( tmp.vm_resources_cost, 2, '.', ' ' ) + '</td>';
		html_vms += '<td>' + CUR[ tmp.currency ] + number_format( tmp.total_cost, 2, '.', ' ' ) + '</td>';
		html_vms += '<td>' + tmp.cpus + ' ' + CUR[ tmp.currency ] + number_format( tmp.cpus_cost, 2, '.', ' ' ) + '</td>';
		html_vms += '<td>' + tmp.cpu_shares + '% ' + CUR[ tmp.currency ] + number_format( tmp.cpu_shares_cost, 2, '.', ' ' ) + '</td>';
		html_vms += '<td>' + tmp.cpu_usage + ' ' + CUR[ tmp.currency ] + number_format( tmp.cpu_usage_cost, 2, '.', ' ' ) + '</td>';
		html_vms += '<td>' + tmp.memory + 'MB ' + CUR[ tmp.currency ] + number_format( tmp.memory_cost, 2, '.', ' ' ) + '</td>';
		html_vms += '<td>' + tmp.template + ' ' + CUR[ tmp.currency ] + number_format( tmp.template_cost, 2, '.', ' ' ) + '</td>';
		html_vms += '<td>' + CUR[ tmp.currency ] + cst.toFixed( 2 ) + '</td>';
		html_vms += '</tr>';

		// process disks
		html_disks += '<tr>';
		html_disks += '<td>' + date + '</td>';
		html_disks += '<td>' + tmp.label + '</td>';

		var size = dr = dw = rc = wc = cst_td = '';
		for( j in tmp.stat.disks ) {
			if( j > 0 ) {
				size += '<br/>';
				dr += '<br/>';
				dw += '<br/>';
				rc += '<br/>';
				wc += '<br/>';
				cst_td += '<br/>';
			}

			var d = tmp.stat.disks[ j ];
			var cst = parseFloat( d.disk_size_cost ) + parseFloat( d.data_read_cost ) + parseFloat( d.data_written_cost ) + parseFloat( d.reads_completed_cost ) + parseFloat( d.writes_completed_cost );
			size += d.label + ' ' + d.disk_size + 'GB ' + CUR[ tmp.currency ] + number_format( d.disk_size_cost, 2, '.', ' ' );
			dr += d.data_read + ' ' + CUR[ tmp.currency ] + number_format( d.data_read_cost, 2, '.', ' ' );
			dw += d.data_written + ' ' + CUR[ tmp.currency ] + number_format( d.data_written_cost, 2, '.', ' ' );
			rc += d.reads_completed + ' ' + CUR[ tmp.currency ] + number_format( d.reads_completed_cost, 2, '.', ' ' );
			wc += d.writes_completed + ' ' + CUR[ tmp.currency ] + number_format( d.writes_completed_cost, 2, '.', ' ' );
			cost_disk += cst;
			cst_td += CUR[ tmp.currency ] + number_format( cst, 2, '.', ' ' );
		}
		html_disks += '<td>' + size + '</td>';
		html_disks += '<td>' + dr + '</td>';
		html_disks += '<td>' + dw + '</td>';
		html_disks += '<td>' + rc + '</td>';
		html_disks += '<td>' + wc + '</td>';
		html_disks += '<td>' + cst_td + '</td>';
		html_disks += '</tr>';

		// process nets
		html_nets += '<tr>';
		html_nets += '<td>' + date + '</td>';
		html_nets += '<td>' + tmp.label + '</td>';

		var int = dr = ds = rate = cst_td = '';
		for( j in tmp.stat.nets ) {
			if( j > 0 ) {
				int += '<br/>';
				dr += '<br/>';
				ds += '<br/>';
				rate += '<br/>';
				cst_td += '<br/>';
			}

			var d = tmp.stat.nets[ j ];
			var cst = parseFloat( d.ip_addresses_cost ) + parseFloat( d.data_received_cost ) + parseFloat( d.data_sent_cost ) + parseFloat( d.rate_cost );
			int += d.label + ' ' + d.ip_addresses + ' ' + LANG.onappusersstatnet_ips + ' ' + CUR[ tmp.currency ] + number_format( d.ip_addresses_cost, 2, '.', ' ' );
			dr += d.data_received + ' ' + CUR[ tmp.currency ] + number_format( d.data_received_cost, 2, '.', ' ' );
			ds += d.data_sent + ' ' + CUR[ tmp.currency ] + number_format( d.data_sent_cost, 2, '.', ' ' );
			rate += d.rate + ' ' + CUR[ tmp.currency ] + number_format( d.rate_cost, 2, '.', ' ' );
			cost_net += cst;
			cst_td += CUR[ tmp.currency ] + number_format( cst, 2, '.', ' ' );
		}
		html_nets += '<td>' + int + '</td>';
		html_nets += '<td>' + rate + '</td>';
		html_nets += '<td>' + dr + '</td>';
		html_nets += '<td>' + ds + '</td>';
		html_nets += '<td>' + cst_td + '</td>';
		html_nets += '</tr>';

		tmp_cur = tmp.currency;
	}

	//total = cost_vm + cost_disk + cost_net;
	table_vms.after( html_vms );
	table_disks.after( html_disks );
	table_nets.after( html_nets );

	$( 'tr#stat_total' ).remove();

	// add VMs total
	html = '<tr class="stat-labels" id="stat_total"><td colspan="11">' + LANG.onappusersstattotalamount + CUR[ tmp_cur ] + total + '</td></tr>';
	$( 'div#stat-vms tr:last' ).after( html );

	// add disks total
	html = '<tr class="stat-labels" id="stat_total"><td colspan="8">' + LANG.onappusersstattotalamount + CUR[ tmp_cur ] + total + '</td></tr>';
	$( 'div#stat-disks tr:last' ).after( html );

	// add nets total
	html = '<tr class="stat-labels" id="stat_total"><td colspan="7">' + LANG.onappusersstattotalamount + CUR[ tmp_cur ] + CUR[ tmp_cur ] + total + '</td></tr>';
	$( 'div#stat-nets tr:last' ).after( html );

	$( '#stat-nav' ).show();
	$( 'div#stat-' + $( '#stat-nav a.stat-nav-bold' ).attr( 'rel' ) ).show();
	$( '#stat_data div' ).width( $( 'tr.stat-labels:first' ).width() - 1 );

	$( '#stat_data' ).show();
}

function processPGN( data ) {
	var pages = Math.ceil( data.total / data.limit );

	if( pages <= 1 ) {
		$( '#stat-pages' ).hide();
		return;
	}

	var nav = '';
	for( i = 1; i <= pages; ++i ) {
		nav += '<option value="' + i + '">' + i + '</option>';
	}

	var select = $( '#stat-pages div:first select' );
	$( '#stat-pages div:first' ).remove();
	$( '#stat-pages' ).append( select );

	$( '#stat-pages select' ).html( nav );
	$( '#stat-pages' ).show();
	$( '#stat-pages select' ).val( data.page );

	reselect( '#stat-pages select', 'sec overf' );
	var INT = setInterval( function () {
		if( $( '#stat-pages div.sel_option' ).length ) {
			$( '#stat-pages div.sel_option[value="' + $( '#stat-pages select' ).val() + '"]' ).click();
			clearInterval( INT );
		}
	}, 50 );
}

function number_format( number, decimals, dec_point, thousands_sep ) {
	// Strip all characters but numerical ones.
	number = (number + '').replace( /[^0-9+\-Ee.]/g, '' );
	var n = !isFinite( +number ) ? 0 : +number,
			prec = !isFinite( +decimals ) ? 0 : Math.abs( decimals ),
			sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
			dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
			s = '',
			toFixedFix = function ( n, prec ) {
				var k = Math.pow( 10, prec );
				return '' + Math.round( n * k ) / k;
			};
	// Fix for IE parseFloat(0.55).toFixed(0) = 0;
	s = (prec ? toFixedFix( n, prec ) : '' + Math.round( n )).split( '.' );
	if( s[0].length > 3 ) {
		s[0] = s[0].replace( /\B(?=(?:\d{3})+(?!\d))/g, sep );
	}
	if( (s[1] || '').length < prec ) {
		s[1] = s[1] || '';
		s[1] += new Array( prec - s[1].length + 1 ).join( '0' );
	}
	return s.join( dec );
}

function reselect( select, addclass ) {
	addclass = typeof(addclass) != 'undefined' ? addclass : '';

	$( select ).wrap( '<div class="sel_wrap ' + addclass + '"/>' );

	var sel_options = '';
	$( select ).children( 'option' ).each( function () {
		sel_options = sel_options + '<div class="sel_option" value="' + $( this ).val() + '">' + $( this ).html() + '</div>';

	} );

	var sel_imul = '<div class="sel_imul">\
                <div class="sel_selected">\
                    <div class="selected-text">' + $( select ).children( 'option' ).first().html() + '</div>\
                    <div class="sel_arraw"></div>\
                </div>\
                <div class="sel_options">' + sel_options + '</div>\
            </div>';

	$( select ).before( sel_imul );
}