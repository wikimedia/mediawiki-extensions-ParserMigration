<?php

namespace MediaWiki\Extension\ParserMigration\Tests;

use MediaWiki\Extension\ParserMigration\Oracle;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\ParserMigration\Oracle
 * @group Database
 */
class OracleTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideParsoidTitles
	 * @covers \MediaWiki\Extension\ParserMigration\Oracle::isParsoidDefaultFor()
	 */
	public function testIsParsoidDefaultFor(
		string $title, bool $useParsoid, int $percent = 100,
		array $excludePageProp = [], ?array $enableParsoidConfig = null,
	): void {
		$this->overrideConfigValues( [
			'ParserMigrationEnableQueryString' => true,
			'ParserMigrationEnableParsoid' => $enableParsoidConfig,
			'ParserMigrationEnableParsoidDiscussionTools' => true,
			'ParserMigrationEnableParsoidArticlePages' => true,
			'ParserMigrationEnableParsoidPercentage' => $percent,
			'ParserMigrationEnableParsoidMobileFrontend' => true,
			'ParserMigrationEnableParsoidMobileFrontendTalkPages' => true,
			'ParserMigrationExcludePageProps' => $excludePageProp,
		] );
		if ( $excludePageProp ) {
			$this->editPage( $title, '__NOTOC__' );
		}
		$services = $this->getServiceContainer();
		$oracle = new Oracle(
			$services->getMainConfig(),
			$services->getUserOptionsManager(),
			$services->getHookContainer(),
			$services->getPageProps(),
			/* no mobile context */
			null
		);
		$title = Title::newFromText( $title );
		$this->assertSame( $useParsoid, $oracle->isParsoidDefaultFor( $title ) );
		if ( $excludePageProp ) {
			$this->deletePage( $title );
		}
	}

	public static function provideParsoidTitles() {
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
		// 100% Parsoid percentage, but with (non)excluded page property
		yield 'Excluded Page Property' => [ 'OracleTest1', false, 100, [ 'notoc' ] ];
		yield 'Nonexcluded Page Property' => [ 'OracleTest2', true, 100, [ 'nocontentconvert' ] ];

		// More tests, this time with the new-style unified configuration
		$nc = "[new config] ";
		$defaultArgs = [ 100, [] ];
		yield "$nc Main Page" => [
			'Main Page', true, ...$defaultArgs, [
				'mobile' => false,
				'default' => [ NS_MAIN => true ],
			],
		];
		yield "$nc Page 1" => [
			'Page 1', true, ...$defaultArgs, [
				'mobile' => true,
				'default' => [ NS_MAIN => 100 ],
			],
		];
		yield "$nc Page 2" => [
			'Page 2', false, ...$defaultArgs, [
				'mobile' => true,
				'default' => false,
			],
		];
		// 50% Parsoid percentage, some of these result in true and some false.
		$args = [ ...$defaultArgs, [
			'default' => 50,
		] ];
		yield "$nc 50% Main Page" => [ 'Main Page', true, ...$args ];
		yield "$nc 50% Page 1" => [ 'Page 1', false, ...$args ];
		yield "$nc 50% Page 2" => [ 'Page 2', false, ...$args ];
		yield "$nc 50% Page 3" => [ 'Page 3', true, ...$args ];
		yield "$nc Talk:Main Page" => [ 'Talk:Main Page', true, ...$args ];
		yield "$nc Talk:Page 1" => [ 'Talk:Page 1', false, ...$args ];
		yield "$nc Talk:Page 2" => [ 'Talk:Page 2', true, ...$args ];
		yield "$nc Talk:Page 3" => [ 'Talk:Page 3', true, ...$args ];
		$nc = "[new config 2] ";
		$args = [ ...$defaultArgs, [
			'default' => [ NS_MAIN => 50, 'default' => 0 ],
		] ];
		yield "$nc 50% Main Page" => [ 'Main Page', true, ...$args ];
		yield "$nc 50% Page 1" => [ 'Page 1', false, ...$args ];
		yield "$nc 50% Page 2" => [ 'Page 2', false, ...$args ];
		yield "$nc 50% Page 3" => [ 'Page 3', true, ...$args ];
		// Talk pages
		yield "$nc Talk:Main Page" => [ 'Talk:Main Page', false, ...$args ];
		yield "$nc Talk:Page 1" => [ 'Talk:Page 1', false, ...$args ];
		yield "$nc Talk:Page 2" => [ 'Talk:Page 2', false, ...$args ];
		yield "$nc Talk:Page 3" => [ 'Talk:Page 3', false, ...$args ];
		$nc = "[new config 3] ";
		$args = [ ...$defaultArgs, [
			'default' => [ NS_MAIN => 0, 'talk' => 50, 'default' => true ],
		] ];
		yield "$nc Main Page" => [ 'Main Page', false, ...$args ];
		yield "$nc Talk:Main Page" => [ 'Talk:Main Page', true, ...$args ];
		yield "$nc Talk:Page 1" => [ 'Talk:Page 1', false, ...$args ];
		yield "$nc Talk:Page 2" => [ 'Talk:Page 2', true, ...$args ];
		yield "$nc Talk:Page 3" => [ 'Talk:Page 3', true, ...$args ];
		yield "$nc MediaWiki:Main Page" => [ 'MediaWiki:Main Page', true, ...$args ];
	}
}
