<?php

namespace MediaWiki\Extension\ParserMigration\Hook;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class HookRunner extends \MediaWiki\HookContainer\HookRunner implements ShouldUseParsoidHook {
	/** @var HookContainer */
	private HookContainer $container;

	public function __construct( HookContainer $container ) {
		parent::__construct( $container );
		$this->container = $container;
	}

	/**
	 * @inheritDoc
	 */
	public function onShouldUseParsoid( User $user, WebRequest $request, Title $title, bool &$enable ): void {
		$this->container->run(
			'ShouldUseParsoid',
			[ $user, $request, $title, &$enable ]
		);
	}

}
