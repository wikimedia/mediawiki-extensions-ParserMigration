<?php

namespace MediaWiki\Extension\ParserMigration;

use MediaWiki\Actions\FormlessAction;
use MediaWiki\Content\Renderer\ContentRenderer;
use MediaWiki\Content\Transform\ContentTransformer;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\Article;

class EditAction extends FormlessAction {
	public function __construct(
		Article $page,
		IContextSource $context,
		private readonly ContentRenderer $contentRenderer,
		private readonly ContentTransformer $contentTransformer,
	) {
		parent::__construct( $page, $context );
	}

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
		$page = new MigrationEditPage(
			$this->getContext(),
			$this->getTitle(),
			$this->contentRenderer,
			$this->contentTransformer
		);
		$page->edit();
		return null;
	}
}
