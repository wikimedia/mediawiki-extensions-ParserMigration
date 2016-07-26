<?php

namespace MediaWiki\ParserMigration;

class EditAction extends \FormlessAction {
	public function getName() {
		return 'parsermigration-edit';
	}

	protected function getDescription() {
		return $this->msg( 'parsermigration-edit-subtitle' );
	}

	public function onView() {
		$page = new MigrationEditPage( $this->getContext(), $this->getTitle() );
		$page->edit();
		return null;
	}
}
