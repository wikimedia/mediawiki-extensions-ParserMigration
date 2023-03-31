The ParserMigration extension provides an interface for comparing
article rendering with a new non-default version of the MediaWiki
parser, thus serving as a parser migration tool.

It was deployed on the Wikimedia production cluster during 2018 to
compare Tidy-based output with a RemexHTML-based replacement.

In 2023 it was overhauled to compare legacy parser output with
Parsoid output.

Add:
```
wfLoadExtension( 'ParserMigration' );
```
to your `LocalSettings.php` to enable.

Running `npm test` and `composer test` will run automated code checks.

This extension adds `action=parser-migration` to the MediaWiki action
API, which parses a page with two different parser configurations and
returns one or both variants in the API response.

It also adds 'Enable parser migration tool' to the list of 'Developer
tools' options at the bottom of the 'Editing' options for a user.
When this is enabled, an "Edit with migration tool" link is added to
the Tools menu in the sidebar for article pages.  Clicking this on an
article page will bring up a variant of the "preview edit" page which
allows you to compare the legacy parser output against Parsoid output.
