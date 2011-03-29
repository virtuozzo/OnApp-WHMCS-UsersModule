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





	<vm_stat>
		<created_at type="datetime">2011-03-29T11:00:06+03:00</created_at>
		<cost type="float">0.0</cost>
		<updated_at type="datetime">2011-03-29T11:00:06+03:00</updated_at>
		<stat_time type="datetime">2011-03-29T11:00:00+03:00</stat_time>
		<id type="integer">10606</id>

		<user_id type="integer">1</user_id>
		<vm_billing_stat_id type="integer">5904</vm_billing_stat_id>
		<currency_code>EUR</currency_code>
		<virtual_machine_id type="integer">265</virtual_machine_id>
		<billing_stats>
		  <virtual_machines type="array">
			<virtual_machine>

			  <costs type="array">
				<cost>
				  <resource_name>cpus</resource_name>
				  <value type="integer">1</value>
				  <cost type="float">1.5</cost>
				</cost>
			  </costs>

			  <label>zhvzv</label>
			  <id type="integer">265</id>
			</virtual_machine>
		  </virtual_machines>
		  <network_interfaces type="array">
			<network_interface>
			  <costs type="array">
				<cost>

				  <resource_name>ip_addresses</resource_name>
				  <value type="integer">1</value>
				  <cost type="float">1.019999999553</cost>
				</cost>
			  </costs>
			  <label>eth0</label>
			  <id type="integer">276</id>

			</network_interface>
		  </network_interfaces>
		</billing_stats>
	</vm_stat>