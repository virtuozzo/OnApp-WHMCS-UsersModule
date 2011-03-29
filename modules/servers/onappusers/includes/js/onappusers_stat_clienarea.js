$( document ).ready( function() {
	$( '#user-stat' ).parent().css( 'text-align', 'left' );

	var now = new Date();
	var end = now.getFullYear() + '-';
	end += ( ( now.getMonth() < 9 ) ? '0' + ( now.getMonth() + 1 ) : ( now.getMonth() + 1 ) ) + '-';
	end += ( now.getDate() < 10 ) ? '0' + now.getDate() : now.getDate();
//	now.setDate( now.getDate() - 7 );
	var start = now.getFullYear() + '-';
	start += ( ( now.getMonth() < 9 ) ? '0' + ( now.getMonth() + 1 ) : ( now.getMonth() + 1 ) ) + '-';
	start += ( now.getDate() < 10 ) ? '0' + now.getDate() : now.getDate();

	$( '#start' ).val( start );
	$( '#end' ).val( end );

	$( 'input#get-stat' ).bind( 'click', function() {
		$( 'span#loading' ).show();

		$.ajax( {
			url: URL,
			data: {
				getstat: 1,
				start: $( 'input#start' ).val(),
				end: $( 'input#end' ).val()
			},
			success: function( data ) {
				data = 'data = ' + data;
				eval( data );
				$( '#user-stat' ).next( 'table' ).find( 'tr#stat-labels' ).nextAll( 'tr' ).remove();
				processData( data );
				$( 'span#loading' ).hide();
			}
		} );
	} );
} );

function processData( data ) {
	var table = $( 'table.userstat' );

	if( data.length ) {
		var currency = getCurrencySign( data[ 0 ].vm_stats.currency_code );
		var total = 0;

		for( i in data ) {
			tmp = data[ i ].vm_stats;
			console.log( tmp );

			var html = '';
			html = '<tr>';
			html += '<td>' + tmp.stat_time/*.substr( 0, 10 )*/ + '</td>';

			html += '<td>' + tmp.billing_stats.virtual_machines[ 0].label  + '</td>';

			var tempdata = '';
			for( j in tmp.billing_stats.network_interfaces ) {
				var temp = tmp.billing_stats.network_interfaces[ j ];
				tempdata += '# ' + temp.id + '<br/>';
			}
			html += '<td># ' + tmp.virtual_machine_id + '</td>';

			var tempdata = '';
			for( j in tmp.billing_stats.network_interfaces ) {
				var temp = tmp.billing_stats.network_interfaces[ j ];
				tempdata += '# ' + temp.id + '<br/>';
			}
			html += '<td>' + tempdata + '</td>';

			var tempdata = '';
			for( j in tmp.billing_stats.disks ) {
				var temp = tmp.billing_stats.disks[ j ];
				tempdata += '# ' + temp.id + '<br/>';
			}
			html += '<td>' + tempdata + '</td>';

			html += '<td>' + currency + 123 + '</td>';
			html += '</tr>';
			table.append( html );
		}

		html = '<tr class="stat-labels"><td colspan="5">' + LANG.onappusersstattotalamount + ': '
				+ currency + 888 + '</td></tr>';
	}
	else {
		html = '<tr class="stat-labels123"><td colspan="5">' + LANG.onappusersstatnodata + '</td></tr>';
	}

	table.append( html );
}

function getCurrencySign( cur ) {
	var CURRENCY = {
		USD: '$',
		EUR: '€',
		GBP: '£'
	}

	return CURRENCY[ cur ] ? CURRENCY[ cur ] : cur;
}