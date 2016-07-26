<?php

namespace MediaWiki\ParserMigration;

class MigrationEditPage extends \EditPage {
	protected $pmContext;

	public function __construct( \IContextSource $context, \Title $title ) {
		$article = new \Article( $title );
		parent::__construct( $article );
		$this->setContextTitle( $title );
		$this->pmContext = $context;
	}

	protected function getActionURL( \Title $title ) {
		return $title->getLocalUrl( [ 'action' => 'parsermigration-edit' ] );
	}

	public function setHeaders() {
		parent::setHeaders();
		$out = $this->pmContext->getOutput();
		$out->addModules( 'ext.parsermigration.edit' );
	}

	protected function previewOnOpen() {
		return true;
	}

	protected function doPreviewParse( \Content $content ) {
		$user = $this->pmContext->getUser();
		$parserOptions = $this->getPreviewParserOptions();
		$pstContent = $content->preSaveTransform( $this->mTitle, $user, $parserOptions );
		$scopedCallback = $parserOptions->setupFakeRevision(
			$this->mTitle, $pstContent, $user );
		$parserOutput = $pstContent->getParserOutput( $this->mTitle, null, $parserOptions );
		\ScopedCallback::consume( $scopedCallback );

		$parserOutput->setEditSectionTokens( false ); // no section edit links

		$tidiers = \RequestContext::getMain()->getConfig()->get( 'ParserMigrationTidiers' );
		if ( !is_array( $tidiers ) || !isset( $tidiers[0] ) || !isset( $tidiers[1] ) ) {
			throw new \Exception( '$wgParserMigrationTidiers must have at least two elements' );
		}
		$leftOutput = $this->tidyParserOutput( $parserOutput, $tidiers[0] );
		$rightOutput = $this->tidyParserOutput( $parserOutput, $tidiers[1] );
		$previewHTML = "<table class=\"mw-parsermigration-sxs\"><tbody><tr>\n" .
			"<th>" . wfMessage( 'parsermigration-current' )->parse() . "</th>\n" .
			"<th>" . wfMessage( 'parsermigration-new' )->parse() . "</th>\n" .
			"</tr><tr>\n" .
			"<td class=\"mw-parsermigration-left\">\n\n" .
			$leftOutput->getText() .
			"\n\n</td><td class=\"mw-parsermigration-right\">\n\n" .
			$rightOutput->getText() .
			"\n\n</td></tr></tbody></table>\n";

		return [
			'parserOutput' => $rightOutput,
			'html' => $previewHTML ];
	}

	protected function tidyParserOutput( $parserOutput, $config ) {
		$tidier = \MWTidy::factory( $config );
		$newOutput = clone $parserOutput;
		$newOutput->setText( $tidier->tidy( $newOutput->getRawText() ) );
		return $newOutput;
	}
}
