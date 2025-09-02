import Page from 'wdio-mediawiki/Page.js';

export class ParsoidPage extends Page {
	async open( title, useparsoid = 1 ) {
		return super.openTitle( title, { useparsoid } );
	}

	async usesParsoid() {
		const elem = await $( '[rel="mw:WikiLink"]' );
		return await elem.isExisting();
	}
}
