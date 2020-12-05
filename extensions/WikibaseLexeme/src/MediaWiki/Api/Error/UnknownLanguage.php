<?php

namespace Wikibase\Lexeme\MediaWiki\Api\Error;

use Message;

/**
 * @license GPL-2.0-or-later
 */
class UnknownLanguage implements ApiError {
	/**
	 * @var string
	 */
	private $given;

	/**
	 * @param string $given
	 */
	public function __construct( $given ) {
		$this->given = $given;
	}

	public function asApiMessage( $parameterName, array $path ) {
		return new \ApiMessage( new Message(
			'apierror-wikibaselexeme-unknown-language',
			[ $parameterName, implode( '/', $path ), $this->given ]
		), 'not-recognized-language' );
	}

}
