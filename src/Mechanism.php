<?php

namespace MediaWiki\Extension\ParserMigration;

use MediaWiki\MediaWikiServices;

class Mechanism {

	/**
	 */
	public function __construct() {
	}

	/**
	 * @param \Content $content
	 * @param \Title $title
	 * @param \ParserOptions $baseOptions
	 * @param \User $user
	 * @param array $configIndexes
	 * @return array
	 */
	public function parse( \Content $content, \Title $title,
		\ParserOptions $baseOptions, \User $user, array $configIndexes
	) {
		$contentRenderer = MediaWikiServices::getInstance()->getContentRenderer();
		if ( $baseOptions->getUseParsoid() ) {
			$parsoid = $baseOptions;
			$legacy = clone $baseOptions;
			$legacy->setOption( 'useParsoid', false );
		} else {
			$legacy = $baseOptions;
			$parsoid = clone $baseOptions;
			$parsoid->setUseParsoid();
		}

		$outputs = [];
		foreach ( $configIndexes as $i ) {
			$outputs[$i] = $contentRenderer->getParserOutput( $content, $title, null, $i ? $parsoid : $legacy );
		}
		return $outputs;
	}
}
