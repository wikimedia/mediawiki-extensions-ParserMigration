<?php

namespace MediaWiki\Extension\ParserMigration\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Extension\ConfirmEdit\Hooks\ConfirmEditTriggersCaptchaHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;

class ConfirmEditHandler implements
	ConfirmEditTriggersCaptchaHook
{

	public function __construct(
		private readonly Config $config,
		private readonly TitleParser $titleParser,
	) {
	}

	/**
	 * Suppress captcha when posting to the "Report Visual Bug" feedback
	 * page.
	 * @param string $action Action user is performing, one of sendmail,
	 *  createaccount, badlogin, edit, create, addurl.
	 * @param PageIdentity|null $page
	 * @param bool &$result
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onConfirmEditTriggersCaptcha(
		string $action,
		?PageIdentity $page,
		bool &$result
	) {
		// If we don't have a target page, bail.
		if ( $page === null ) {
			return true;
		}
		// We're only going to intervene if this is an edit or create
		// ('create' since this could be the first report posted)
		// 'addurl' because our subject lines link to the page which is
		// the subject of the report.
		if ( !( $action === 'edit' || $action === 'create' || $action === 'addurl' ) ) {
			return true;
		}
		// If the Report Visual Bug tool is not enabled, bail.
		if ( !$this->config->get( 'ParserMigrationEnableReportVisualBug' ) ) {
			return true;
		}
		$apiUrl = $this->config->get( 'ParserMigrationFeedbackAPIURL' );
		// If a foreign API URL is set, we can't do anything to help.
		if ( $apiUrl ) {
			return true;
		}
		$title = $this->config->get( 'ParserMigrationFeedbackTitle' ) ?:
			wfMessage( 'parsermigration-reportbug-feedback-title' )->plain();
		$titleValue = $this->titleParser->parseTitle( $title );
		// If the page doesn't match our feedback page, do nothing.
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		if ( !$titleValue->isSameLinkAs( TitleValue::castPageToLinkTarget( $page ) ) ) {
			return true;
		}
		// Ok, this is our page!  Suppress the captcha.
		$result = false;
		return false;
	}
}
