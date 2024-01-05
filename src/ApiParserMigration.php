<?php

namespace MediaWiki\Extension\ParserMigration;

use ApiBase;
use Exception;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use ParserOptions;
use Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiParserMigration extends ApiBase {
	/** @var string[] */
	private static $configNames = [
		0 => 'old',
		1 => 'new',
	];

	public function execute() {
		$params = $this->extractRequestParams();

		$title = $this->getTitleOrPageId( $params )->getTitle();
		// Follow redirects by default. Redirect link output is not
		// interesting for rendering diff comparisons. Provide clients
		// the option to choose the redirect page via '&redirect=no'.
		if ( $title->isRedirect() && (
			!isset( $params['redirect'] ) || $params['redirect'] !== 'no'
		) ) {
			$redirectLookup = MediaWikiServices::getInstance()->getRedirectLookup();
			$redirect = $redirectLookup->getRedirectTarget( $title );
			$title = Title::castFromLinkTarget( $redirect ) ?? $title;
		}
		$revisionRecord = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title );
		if ( !$revisionRecord ) {
			$this->dieWithError( 'apierror-missingtitle' );
		}
		$content = $revisionRecord->getContent( SlotRecord::MAIN );
		if ( !$content ) {
			$this->dieWithError( [ 'apierror-missingcontent-pageid', $revisionRecord->getPageId() ] );
		}

		$configIndexesByName = array_flip( self::$configNames );
		$configIndexes = [];
		foreach ( $params['config'] as $configName ) {
			if ( !isset( $configIndexesByName[$configName] ) ) {
				throw new Exception( 'Invalid config name, should have already been validated' );
			}
			$configIndexes[] = $configIndexesByName[$configName];
		}

		$mechanism = new Mechanism();
		$user = $this->getUser();
		$options = ParserOptions::newFromContext( $this->getContext() );
		$outputs = $mechanism->parse( $content, $title, $options, $user, $configIndexes );

		$result = $this->getResult();
		foreach ( $configIndexes as $index ) {
			$result->addValue( null, self::$configNames[$index], $outputs[$index]->getText() );
		}
	}

	public function isInternal() {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'config' => [
				ParamValidator::PARAM_TYPE => self::$configNames,
				ParamValidator::PARAM_DEFAULT => 'old|new',
				ParamValidator::PARAM_ISMULTI => true,
			],
			'redirect' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}
}
