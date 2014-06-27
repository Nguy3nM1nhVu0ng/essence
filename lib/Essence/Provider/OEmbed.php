<?php

/**
 *	@author Félix Girault <felix.girault@gmail.com>
 *	@license FreeBSD License (http://opensource.org/licenses/BSD-2-Clause)
 */

namespace Essence\Provider;

use Essence\Exception;
use Essence\Media;
use Essence\Media\Preparator;
use Essence\Provider;
use Essence\Dom\Parser as DomParser;
use Essence\Http\Client as HttpClient;
use Essence\Utility\Hash;
use Essence\Utility\Json;
use Essence\Utility\Xml;



/**
 *	Base class for an OEmbed provider.
 *	This kind of provider extracts embed informations through the OEmbed protocol.
 */

class OEmbed extends Provider {

	/**
	 *	JSON response format.
	 *
	 *	@var string
	 */

	const json = 'json';



	/**
	 *	XML response format.
	 *
	 *	@var string
	 */

	const xml = 'xml';



	/**
	 *	Internal HTTP client.
	 *
	 *	@var Essence\Http\Client
	 */

	protected $_Http = null;



	/**
	 *	Internal DOM parser.
	 *
	 *	@var Essence\Dom\Parser
	 */

	protected $_Dom = null;



	/**
	 *	### Options
	 *
	 *	- 'endpoint' string The OEmbed endpoint.
	 *	- 'format' string The expected response format.
	 */

	protected $_properties = [
		'endpoint' => '',
		'format' => self::json
	];



	/**
	 *	Constructor.
	 *
	 *	@param Essence\Http\Client $Http HTTP client.
	 *	@param Essence\Dom\Parser $Dom DOM parser.
	 *	@param array $presenters Presenters.
	 */

	public function __construct(
		HttpClient $Http,
		DomParser $Dom,
		array $presenters = [ ]
	) {
		$this->_Http = $Http;
		$this->_Dom = $Dom;

		parent::__construct( $presenters );
	}



	/**
	 *	{@inheritDoc}
	 *
	 *	@note If no endpoint was specified in the configuration, the page at
	 *		the given URL will be parsed to find one.
	 *	@throws Essence\Exception If the parsed page doesn't provide any endpoint.
	 */

	protected function _embed( $url, array $options ) {

		if ( $this->endpoint ) {
			$endpoint = sprintf( $this->endpoint, urlencode( $url ));
			$format = $this->format;
		} else if ( !$this->_extractEndpoint( $url, $endpoint, $format )) {
			throw new Exception(
				"Unable to extract any endpoint from '$url'."
			);
		}

		if ( $options ) {
			$this->_completeEndpoint( $endpoint, $options );
		}

		return $this->_embedEndpoint( $endpoint, $format );
	}



	/**
	 *	Extracts an oEmbed endpoint from the given URL.
	 *
	 *	@param string $url URL from which to extract an endpoint.
	 *	@param string $endpoint The extracted endpoint.
	 *	@param string $format The extracted format.
	 *	@return boolean If an endpoint was extracted.
	 */

	protected function _extractEndpoint( $url, &$endpoint, &$format ) {

		$attributes = $this->_Dom->extractAttributes( $this->_Http->get( $url ), [
			'link' => [
				'rel' => '#alternate#i',
				'type',
				'href'
			]
		]);

		foreach ( $attributes['link'] as $link ) {
			if ( preg_match( '#(?<format>json|xml)#i', $link['type'], $matches )) {
				$endpoint = $link['href'];
				$format = $matches['format'];
				return true;
			}
		}

		return false;
	}



	/**
	 *	Appends a set of options as parameters to the given endpoint URL.
	 *
	 *	@param string $endpoint Endpoint URL.
	 *	@param array $options Options to append.
	 */

	protected function _completeEndpoint( &$endpoint, $options ) {

		$params = array_intersect_key( $options, [
			'maxwidth' => '',
			'maxheight' => ''
		]);

		if ( $params ) {
			$endpoint .= ( strrpos( $endpoint, '?' ) === false ) ? '?' : '&';
			$endpoint .= http_build_query( $params );
		}
	}



	/**
	 *	Fetches embed information from the given endpoint.
	 *
	 *	@param string $endpoint Endpoint to fetch informations from.
	 *	@param string $format Response format.
	 *	@return Media Embed informations.
	 */

	protected function _embedEndpoint( $endpoint, $format ) {

		$response = $this->_Http->get( $endpoint );

		switch ( $format ) {
			case self::json:
				$data = Json::parse( $response );
				break;

			case self::xml:
				$data = Xml::parse( $response );
				break;

			default:
				throw new Exception( 'Unsupported response format.' );
		}

		return new Media(
			Hash::reindex( $data, [
				'author_name' => 'authorName',
				'author_url' => 'authorUrl',
				'provider_name' => 'providerName',
				'provider_url' => 'providerUrl',
				'cache_age' => 'cacheAge',
				'thumbnail_url' => 'thumbnailUrl',
				'thumbnail_width' => 'thumbnailWidth',
				'thumbnail_height' => 'thumbnailHeight'
			])
		);
	}
}
