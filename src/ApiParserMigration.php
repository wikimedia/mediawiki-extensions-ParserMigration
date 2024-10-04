<?php

namespace MediaWiki\Extension\ParserMigration;

use ApiBase;
use ApiMain;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use ParserOptions;
use RuntimeException;
use Wikimedia\ParamValidator\ParamValidator;

class ApiParserMigration extends ApiBase {
	/** @var string[] */
	private static $configNames = [
		0 => 'old',
		1 => 'new',
	];

	private RedirectLookup $redirectLookup;
	private RevisionLookup $revisionLookup;

	public function __construct(
		ApiMain $main,
		string $action,
		RedirectLookup $redirectLookup,
		RevisionLookup $revisionLookup
	) {
		parent::__construct( $main, $action );
		$this->redirectLookup = $redirectLookup;
		$this->revisionLookup = $revisionLookup;
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$title = $this->getTitleOrPageId( $params )->getTitle();
		// Follow redirects by default. Redirect link output is not
		// interesting for rendering diff comparisons. Provide clients
		// the option to choose the redirect page via '&redirect=no'.
		if ( $title->isRedirect() && (
			!isset( $params['redirect'] ) || $params['redirect'] !== 'no'
		) ) {
			$redirect = $this->redirectLookup->getRedirectTarget( $title );
			$title = Title::castFromLinkTarget( $redirect ) ?? $title;
		}
		$revisionRecord = $this->revisionLookup->getRevisionByTitle( $title );
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
				throw new RuntimeException( 'Invalid config name, should have already been validated' );
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

	/** @inheritDoc */
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
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'redirect' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}
}
