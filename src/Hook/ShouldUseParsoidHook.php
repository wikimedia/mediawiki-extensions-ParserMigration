<?php

namespace MediaWiki\Extension\ParserMigration\Hook;

use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * Hook interface to give other extensions dependent on ParserMigration
 * a chance to override the opt-in enabler state, such as to force both
 * sides of an A/B test to use the same state.
 *
 * @ingroup Hooks
 */
interface ShouldUseParsoidHook {

	/**
	 * Gives other extensions dependent on ParserMigration a chance to
	 * override the opt-in enabler state, such as to force both sides of
	 * an A/B test to use the same state.
	 *
	 * The value of $enable will be set based on global state, user prefs
	 * and any opt-in query parameters, and can be overridden by changing
	 * it in the hook.
	 *
	 * @param User $user
	 * @param WebRequest $request
	 * @param Title $title
	 * @param bool &$enable
	 * @return void
	 */
	public function onShouldUseParsoid( User $user, WebRequest $request, Title $title, bool &$enable ): void;
}
