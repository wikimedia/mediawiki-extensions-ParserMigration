<?php

namespace MediaWiki\ParserMigration;

use Wikimedia\ScopedCallback;

class Mechanism {
	/** @var array */
	public $tidiers;

	/**
	 * @param array $tidiers
	 */
	public function __construct( $tidiers ) {
		if ( !is_array( $tidiers ) || !isset( $tidiers[0] ) || !isset( $tidiers[1] ) ) {
			throw new \Exception( '$wgParserMigrationTidiers must have at least two elements' );
		}
		$this->tidiers = $tidiers;
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
		$options = clone $baseOptions;
		$scopedCallback = $options->setupFakeRevision( $title, $content, $user );
		$parserOutput = $content->getParserOutput( $title, null, $options );
		ScopedCallback::consume( $scopedCallback );

		$outputs = [];
		foreach ( $configIndexes as $i ) {
			$outputs[$i] = $this->tidyParserOutput( $parserOutput, $this->tidiers[$i] );
		}
		return $outputs;
	}

	/**
	 * @param \ParserOutput $parserOutput
	 * @param array $config
	 * @return \ParserOutput
	 */
	protected function tidyParserOutput( $parserOutput, $config ) {
		$newOutput = clone $parserOutput;

		// FIXME: MWTidy no longer exists to give a second version.
		// $tidier = \MWTidy::factory( $config );
		// $newOutput->setText( $tidier->tidy( $newOutput->getRawText() ) );

		return $newOutput;
	}
}
