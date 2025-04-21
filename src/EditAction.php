<?php

namespace MediaWiki\Extension\ParserMigration;

use MediaWiki\Actions\FormlessAction;

class EditAction extends FormlessAction {
	/**
	 * @return string
	 */
	public function getName() {
		return 'parsermigration-edit';
	}

	/**
	 * @return string
	 */
	protected function getDescription() {
		return $this->msg( 'parsermigration-edit-subtitle' )->escaped();
	}

	/**
	 * @return null
	 */
	public function onView() {
		$page = new MigrationEditPage( $this->getContext(), $this->getTitle() );
		$page->edit();
		return null;
	}
}
