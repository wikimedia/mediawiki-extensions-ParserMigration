<?php

namespace MediaWiki\Extension\ParserMigration;

use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in ServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	'ParserMigration.Oracle' => static function ( MediaWikiServices $services ): Oracle {
		return new Oracle(
			$services->getMainConfig(),
			$services->getUserOptionsManager()
		);
	},
];

// @codeCoverageIgnoreEnd
