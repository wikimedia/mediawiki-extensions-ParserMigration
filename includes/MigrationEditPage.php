<?php

namespace MediaWiki\ParserMigration;

class MigrationEditPage extends \EditPage {

	/**
	 * @param \IContextSource $context
	 * @param \Title $title
	 */
	public function __construct( \IContextSource $context, \Title $title ) {
		$article = \Article::newFromTitle( $title, $context );
		parent::__construct( $article );
		$this->setContextTitle( $title );
	}

	/**
	 * @param \Title $title
	 * @return string
	 */
	protected function getActionURL( \Title $title ) {
		return $title->getLocalURL( [ 'action' => 'parsermigration-edit' ] );
	}

	public function setHeaders() {
		parent::setHeaders();
		$out = $this->context->getOutput();
		$out->addModuleStyles( 'ext.parsermigration.edit' );
	}

	protected function previewOnOpen() {
		return true;
	}

	/**
	 * @param \Content $content
	 * @return array
	 */
	protected function doPreviewParse( \Content $content ) {
		$user = $this->context->getUser();
		$parserOptions = $this->getPreviewParserOptions();
		$pstContent = $content->preSaveTransform( $this->mTitle, $user, $parserOptions );
		$mechanism = new Mechanism(
			\RequestContext::getMain()->getConfig()->get( 'ParserMigrationTidiers' ) );
		$outputs = $mechanism->parse( $pstContent, $this->mTitle, $parserOptions,
			$user, [ 0, 1 ] );

		// no section edit links
		$poOptions = [ 'enableSectionEditLinks' => false ];

		$previewHTML = "<table class=\"mw-parsermigration-sxs\"><tbody><tr>\n" .
			"<th>" . $this->context->msg( 'parsermigration-current' )->parse() . "</th>\n" .
			"<th>" . $this->context->msg( 'parsermigration-new' )->parse() . "</th>\n" .
			"</tr><tr>\n" .
			"<td class=\"mw-parsermigration-left\">\n\n" .
			$outputs[0]->getText( $poOptions ) .
			"\n\n</td><td class=\"mw-parsermigration-right\">\n\n" .
			$outputs[1]->getText( $poOptions ) .
			"\n\n</td></tr></tbody></table>\n";

		return [
			'parserOutput' => $outputs[1],
			'html' => $previewHTML ];
	}
}
