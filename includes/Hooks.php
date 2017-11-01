<?php

namespace MediaWiki\ParserMigration;

class Hooks {
	/**
	 * @param \User $user
	 * @param array &$defaultPreferences
	 * @return bool
	 */
	public static function onGetPreferences( $user, &$defaultPreferences ) {
		$defaultPreferences['parsermigration'] = [
			'type' => 'toggle',
			'label-message' => 'parsermigration-pref-label',
			'help-message' => 'parsermigration-pref-help',
			'section' => 'editing/advancedediting'
		];
		return true;
	}

	/**
	 * @param \BaseTemplate &$template
	 * @param array &$toolbox
	 */
	public static function onBaseTemplateToolbox( &$template, &$toolbox ) {
		$skin = $template->getSkin();
		$out = $skin->getOutput();
		$title = $skin->getTitle();
		$user = $skin->getUser();
		if ( $out->isArticleRelated() && $user->getOption( 'parsermigration' ) ) {
			$toolbox['parsermigration'] = [
				'href' => $title->getLocalURL( [ 'action' => 'parsermigration-edit' ] ),
				'text' => $skin->msg( 'parsermigration-toolbox-label' )->text(),
			];
		}
	}
}
