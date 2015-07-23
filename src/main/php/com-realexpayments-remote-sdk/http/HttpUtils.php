<?php


namespace com\realexpayments\remote\sdk\http;


use com\realexpayments\remote\sdk\RealexException;
use Exception;
use Logger;



/**
 * HTTP Utils class for dealing with HTTP and actual message sending.
 *
 * @author vicpada
 */
class HttpUtils {


	const HTTPS_PROTOCOL = 'https';

	/**
	 * @var Logger Logger
	 */
	private static $logger;


	/**
	 * @param HttpConfiguration $httpConfiguration
	 *
	 * @return HttpClient httpclient
	 */
	public static function  getDefaultClient( HttpConfiguration $httpConfiguration ) {

		self::getLogger();

		// TODO: set Connect Timeout
		// TODO: set Socket Timeout

		self::$logger->debug( "Creating HttpClient with simple no pooling/no connection reuse default settings." );

		return new HttpClient();
	}

	/**
	 *
	 * Initialises logger if not initialised already
	 *
	 */
	private static function getLogger() {
		if ( ! self::$logger ) {
			self::$logger = Logger::getLogger( __CLASS__ );
		}
	}

	/**
	 *
	 * Perform the actual send of the message, according to the HttpConfiguration, and get the response.
	 * This will also check if only HTTPS is allowed, based on the {@link HttpConfiguration}, and will
	 * throw a {@link RealexException} if HTTP is used when only HTTPS is allowed. A {@link RealexException}
	 * is also thrown if the response from Realex is not success (ie. if it's not 200 status code).
	 *
	 * @param string $xml
	 * @param HttpClient $httpClient
	 * @param HttpConfiguration $httpConfiguration *
	 *
	 * @return string
	 */
	public static function sendMessage( $xml, HttpClient $httpClient, HttpConfiguration $httpConfiguration ) {

		self::getLogger();

		self::$logger->debug( "Setting endpoint of: " . $httpConfiguration->getEndpoint() );
		$httpPost = new HttpRequest( $httpConfiguration->getEndpoint(), HttpRequest::METH_POST );
		$response = null;

		// Confirm protocol is HTTPS (ie. secure) if such is configured
		if ( $httpConfiguration->isOnlyAllowHttps() ) {
			$scheme = parse_url( $httpPost->getUrl(), PHP_URL_SCHEME );
			if ( ! $scheme || strtolower( $scheme ) != strtolower( self::HTTPS_PROTOCOL ) ) {
				self::$logger->error( "Protocol must be " . self::HTTPS_PROTOCOL );
				throw new RealexException( "Protocol must be" . self::HTTPS_PROTOCOL );
			}

		} else {
			self::$logger->warn( "Allowed send message over HTTP. This should NEVER be allowed in a production environment." );
		}

		try {

			self::$logger->debug( "Setting entity in POST message." );
			$httpPost->setBody( $xml );

			self::$logger->debug( "Executing HTTP Post message to: " . $httpPost->getUrl() );
			$response = $httpClient->execute( $httpPost );

			self::$logger->debug( "Checking the HTTP response status code." );
			$statusCode = $response->getResponseCode();
			if ( $statusCode != 200 ) {
				throw new RealexException( "Unexpected http status code [" . $statusCode . "]" );
			}

			self::$logger->debug( "Converting HTTP entity (the xml response) back into a string." );
			$xmlResponse = $response->getBody();

			return $xmlResponse;

		} catch ( Exception $e ) {

			self::$logger->error( "Exception communicating with Realex.", $e->getMessage() );
			throw new RealexException( "Exception communicating with Realex", $e );
		}
	}


}