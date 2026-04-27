<?php

namespace MediaWiki\Extension\ParserMigration;

use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in ServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	'ParserMigration.Oracle' => static function ( MediaWikiServices $services ): Oracle {
		return $services->getObjectFactory()->createObject( [
			'class' => Oracle::class,
			'services' => [
				'MainConfig',
				'UserOptionsManager',
				'HookContainer',
				'PageProps',
			],
			'optional_services' => [
				'MobileFrontend.Context',
			],
		] );
	},
];

// @codeCoverageIgnoreEnd
