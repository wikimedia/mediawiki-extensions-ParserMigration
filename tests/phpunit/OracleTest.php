<?php

namespace MediaWiki\Extension\ParserMigration\Tests;

use MediaWiki\Extension\ParserMigration\Oracle;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\ParserMigration\Oracle
 */
class OracleTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * @dataProvider provideParsoidTitles
	 * @covers \MediaWiki\Extension\ParserMigration\Oracle::isParsoidDefaultFor()
	 */
	public function testIsParsoidDefaultFor( string $title, bool $useParsoid, int $percent = 100 ): void {
		$this->overrideConfigValues( [
			'ParserMigrationEnableQueryString' => true,
			'ParserMigrationEnableParsoidDiscussionTools' => true,
			'ParserMigrationEnableParsoidArticlePages' => true,
			'ParserMigrationEnableParsoidPercentage' => $percent,
			'ParserMigrationEnableParsoidMobileFrontend' => true,
			'ParserMigrationEnableParsoidMobileFrontendTalkPages' => true,
		] );
		$services = $this->getServiceContainer();
		$oracle = new Oracle(
			$services->getMainConfig(),
			$services->getUserOptionsManager(),
			/* no mobile context */
			null
		);
		$title = Title::newFromText( $title );
		$this->assertSame( $useParsoid, $oracle->isParsoidDefaultFor( $title ) );
	}

	public function provideParsoidTitles() {
		// 100% Parsoid percentage, all should return true
		yield 'Main Page' => [ 'Main Page', true ];
		yield 'Page 1' => [ 'Page 1', true ];
		yield 'Page 2' => [ 'Page 2', true ];
		yield 'Page 3' => [ 'Page 3', true ];
		// 50% Parsoid percentage, some of these result in true and some false.
		yield '50% Main Page' => [ 'Main Page', true, 50 ];
		yield '50% Page 1' => [ 'Page 1', false, 50 ];
		yield '50% Page 2' => [ 'Page 2', false, 50 ];
		yield '50% Page 3' => [ 'Page 3', true, 50 ];
	}
}
