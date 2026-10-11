<?php

namespace MediaWiki\Extension\ParserMigration;

use MediaWiki\Content\Content;
use MediaWiki\Content\Renderer\ContentRenderer;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class Mechanism {

	public function __construct(
		private readonly ContentRenderer $contentRenderer,
	) {
	}

	/**
	 * @param Content $content
	 * @param Title $title
	 * @param ParserOptions $baseOptions
	 * @param User $user
	 * @param array $configIndexes
	 * @return array
	 */
	public function parse( Content $content, Title $title,
		ParserOptions $baseOptions, User $user, array $configIndexes
	) {
		if ( $baseOptions->getUseParsoid() ) {
			$parsoid = $baseOptions;
			$legacy = clone $baseOptions;
			$legacy->setUseParsoid( false );
		} else {
			$legacy = $baseOptions;
			$parsoid = clone $baseOptions;
			$parsoid->setUseParsoid( true );
		}

		$outputs = [];
		foreach ( $configIndexes as $i ) {
			$outputs[$i] = $this->contentRenderer->getParserOutput(
				$content, $title, null, $i ? $parsoid : $legacy
			);
		}
		return $outputs;
	}
}
