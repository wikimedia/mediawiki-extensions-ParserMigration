import { ParsoidPage } from '../pageobjects/parsoid.page.js';
import { mwbot } from 'wdio-mediawiki/Api';

describe( 'ParserMigration', () => {
	let bot, pageName;
	before( async () => {
		bot = await mwbot();
		pageName = Math.random().toString();
		await bot.edit( pageName, 'Link to [[Main_Page]]' );
	} );

	it( 'should use parsoid with useparsoid=1 param', async () => {
		const useParsoid = 1;
		const page = new ParsoidPage();
		await page.open( pageName, useParsoid );
		expect( await page.usesParsoid() );
	} );

	it( 'should use legacy parser with useparsoid=0 param', async () => {
		const useParsoid = 0;
		const page = new ParsoidPage();
		await page.open( pageName, useParsoid );
		expect( !await page.usesParsoid() );
	} );
} );
