<?php

class CURL {
	private $ch;
	private $data;
	private $customOptions = array( );

	private $defaultOptions = array(
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT => 'CURL',
		CURLOPT_HEADER => false,
		CURLOPT_NOBODY => false,
	);

	public function __construct( ) {
		$this->ch = curl_init( );
	}

	public function useCookies() {
		$cookiesFile = tempnam( '/tmp', 'OnApp_CURL_cookies' );
		$this->defaultOptions[ CURLOPT_COOKIEFILE ] = $cookiesFile;
		$this->defaultOptions[ CURLOPT_COOKIEJAR ]  = $cookiesFile;
	}

	public function addOption( $name, $value ) {
		$this->customOptions[ $name ] = $value;
	}

	public function setLog( ) {
		$log = fopen( dirname( __FILE__ ) . '/CURL.log', 'a' );
		if( $log ) {
			fwrite( $log, str_repeat( '=', 80 ) . PHP_EOL );
			$this->addOption( CURLOPT_STDERR, $log );
			$this->addOption( CURLOPT_VERBOSE, true );
		}
	}

	public function put( $url = null ) {
		return $this->send( 'PUT', $url );
	}

	public function get( $url = null ) {
		return $this->send( 'GET', $url );
	}

	public function post( $url = null ) {
		return $this->send( 'POST', $url );
	}

	public function head( $url = null ) {
		return $this->send( 'HEAD', $url );
	}

	public function getRequestInfo( $param = false ) {
		if( $param ) {
			return $this->getDataItem( 'info', $param );
		}
		else {
			return $this->data[ 'info' ];
		}
	}

	public function getHeadersData( $param = false ) {
		if( $param ) {
			return $this->getDataItem( 'data', $param );
		}
		return $this->data[ 'data' ];
	}

	private function send( $method, $url ) {
		if( $url === null ) {
			if( !isset( $this->customOptions[ CURLOPT_URL ] ) || empty( $this->customOptions[ CURLOPT_URL ] ) ) {
				exit( 'empty url' );
			}
		}
		$this->addOption( CURLOPT_CUSTOMREQUEST, $method );
		$this->addOption( CURLOPT_URL, $url );
		return $this->exec( );
	}

	private function setOptions( ) {
		if( isset( $this->customOptions[ CURLOPT_HEADER ] ) && $this->customOptions[ CURLOPT_HEADER ] ) {
			$this->addOption( CURLINFO_HEADER_OUT, true );
		}

		$options = $this->customOptions + $this->defaultOptions;
		curl_setopt_array( $this->ch, $options );
	}

	private function exec( ) {
		$this->setOptions( );
		$response = curl_exec( $this->ch );

		$this->data[ 'info' ] = curl_getinfo( $this->ch );
		if( isset( $this->customOptions[ CURLOPT_HEADER ] ) && $this->customOptions[ CURLOPT_HEADER ] ) {
			$this->data[ 'info' ][ 'request_header' ] = trim( $this->data[ 'info' ][ 'request_header' ] );
			$this->processHeaders( $response );
		}

		curl_close( $this->ch );

		return $response;
	}

	private function processHeaders( &$data ) {
		$tmp = explode( "\r\n\r\n", $data, 2 );

		$this->data[ 'info' ][ 'response_header' ] = $tmp[ 0 ];
		$this->data[ 'info' ][ 'response_body' ] = $data = trim( $tmp[ 1 ] );

		$tmp = explode( "\r\n", $this->data[ 'info' ][ 'response_header' ] );
		$this->data[ 'data' ][ 'Message' ] = $tmp[ 0 ];
		for( $i = 1, $size = count( $tmp ); $i < $size; ++$i ) {
			$string = explode( ': ', $tmp[ $i ], 2 );
			$this->data[ 'data' ][ $string[ 0 ] ] = $string[ 1 ];
		}
	}

	private function getDataItem( $what, $name ) {
		if( isset( $this->data[ $what ][ $name ] ) ) {
			return $this->data[ $what ][ $name ];
		}
		else {
			return null;
		}
	}
}