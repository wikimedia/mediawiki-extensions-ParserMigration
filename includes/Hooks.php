<?php

namespace MediaWiki\ParserMigration;

class Hooks {
	static public function onGetPreferences( $user, &$defaultPreferences ) {
		$defaultPreferences['parsermigration'] = [
			'type' => 'toggle',
			'label-message' => 'parsermigration-pref-label',
			'help-message' => 'parsermigration-pref-help',
			'section' => 'editing/advancedediting'
		];
		return true;
	}

	static public function onBaseTemplateToolbox( &$template, &$toolbox ) {
		$skin = $template->getSkin();
		$out = $skin->getOutput();
		$title = $skin->getTitle();
		if ( $out->isArticleRelated() ) {
			$toolbox['parsermigration'] = [
				'href' => $title->getLocalUrl( [ 'action' => 'parsermigration-edit' ] ),
				'text' => $skin->msg( 'parsermigration-toolbox-label' )->text(),
			];
		}
	}
}
