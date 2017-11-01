<?php

namespace MediaWiki\ParserMigration;

class ApiParserMigration extends \ApiBase {
	static private $configNames = [
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
			$title = \WikiPage::factory( $title )->getRedirectTarget();
		}
		$revision = \Revision::newFromTitle( $title );
		if ( !$revision ) {
			$this->dieWithError( 'apierror-missingtitle' );
		}
		$content = $revision->getContent();
		if ( !$content ) {
			$this->dieWithError( [ 'apierror-missingcontent-pageid', $revision->getPage() ] );
		}

		$configIndexesByName = array_flip( self::$configNames );
		$configIndexes = [];
		foreach ( $params['config'] as $configName ) {
			if ( !isset( $configIndexesByName[$configName] ) ) {
				throw new \Exception( 'Invalid config name, should have already been validated' );
			}
			$configIndexes[] = $configIndexesByName[$configName];
		}

		$mechanism = new Mechanism( $this->getConfig()->get( 'ParserMigrationTidiers' ) );
		$user = $this->getUser();
		$options = \ParserOptions::newCanonical();
		$outputs = $mechanism->parse( $content, $title, $options, $user, $configIndexes );

		$result = $this->getResult();
		foreach ( $configIndexes as $index ) {
			$result->addValue( null, self::$configNames[$index], $outputs[$index]->getText() );
		}
	}

	public function isInternal() {
		return true;
	}

	public function getAllowedParams() {
		return [
			'title' => [
				\ApiBase::PARAM_TYPE => 'string',
				\ApiBase::PARAM_REQUIRED => true,
			],
			'config' => [
				\ApiBase::PARAM_TYPE => self::$configNames,
				\ApiBase::PARAM_DFLT => 'old|new',
				\ApiBase::PARAM_ISMULTI => true,
			],
			'redirect' => [
				\ApiBase::PARAM_TYPE => 'string',
				\ApiBase::PARAM_REQUIRED => false,
			],
		];
	}
}
