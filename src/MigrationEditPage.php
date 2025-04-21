<?php

namespace MediaWiki\Extension\ParserMigration;

use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\EditPage\EditPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Article;
use MediaWiki\Title\Title;

class MigrationEditPage extends EditPage {

	/**
	 * @param IContextSource $context
	 * @param Title $title
	 */
	public function __construct( IContextSource $context, Title $title ) {
		$article = Article::newFromTitle( $title, $context );
		parent::__construct( $article );
		$this->setContextTitle( $title );
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	protected function getActionURL( Title $title ) {
		return $title->getLocalURL( [ 'action' => 'parsermigration-edit' ] );
	}

	public function setHeaders() {
		parent::setHeaders();
		$out = $this->getContext()->getOutput();
		$out->addModuleStyles( 'ext.parsermigration.edit' );
	}

	/** @inheritDoc */
	protected function previewOnOpen() {
		return true;
	}

	/**
	 * @param Content $content
	 * @return array
	 */
	protected function doPreviewParse( Content $content ) {
		$context = $this->getContext();
		$user = $context->getUser();
		$out = $context->getOutput();
		$parserOptions = $this->getPreviewParserOptions();
		$contentTransformer = MediaWikiServices::getInstance()->getService( 'ContentTransformer' );
		$pstContent = $contentTransformer->preSaveTransform( $content, $this->getTitle(), $user, $parserOptions );
		$mechanism = new Mechanism();
		$outputs = $mechanism->parse(
			$pstContent,
			$this->getTitle(),
			$parserOptions,
			$user,
			[ 0, 1 ]
		);

		$skinOptions = $out->getSkin()->getOptions();
		$poOptions = [
			'injectTOC' => $skinOptions['toc'],
			'enableSectionEditLinks' => false,
		];

		$previewHTML = "<table class=\"mw-parsermigration-sxs\"><tbody><tr>\n" .
			"<th>" . $context->msg( 'parsermigration-current' )->parse() . "</th>\n" .
			"<th>" . $context->msg( 'parsermigration-new' )->parse() . "</th>\n" .
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
