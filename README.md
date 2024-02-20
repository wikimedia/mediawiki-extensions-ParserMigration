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

== Migration to Parsoid read views ==

This extension also adds a dropdown under 'Developer tools' at the
bottom of the 'Editing' options for a user, which allows the user
to opt-in, opt-out, or follow the wiki defaults for the use of
the new Parsoid wikitext parser to render article pages.

"Follow the wiki default" uses Parsoid based on two configuration
options:
* `$wgParserMigrationEnableParsoidDiscussionTools` if set to true will
  use Parsoid for all pages in the talk namespace, but not for other
  pages.  This is intended for use with the
  [DiscussionTools](https://www.mediawiki.org/wiki/Extension:DiscussionTools)
  extension, which is already powered by Parsoid and enabled by
  default on some wikis.
* `$wgParserMigrationEnableParsoidArticlePages` if set to true will
  use Parsoid for all pages in the main article namespace, but not for other
  pages.

The first time the user views a page rendered with the new Parsoid
parser, either because they have opted-in to use it always or because
the wiki default is to use Parsoid for the particular page they are
viewing, a notification will appear.  This notification can be
controlled with the following configuration options:

* `$wgParserMigrationUserNoticeVersion`: This value can be incremented
whenever the notice text has materially changed, in order to display
it again to users who have already dismissed it once.
* `$wgParserMigrationUserNoticeDays`:  After the notice has been manually
dismissed by the user, it will not be shown again until this number
of days have passed.  Setting it to `0` means the notice will never
be shown again once dismissed unless the notice version increases.

The message shown is set by two localizable messages, which can also
be locally overridden on-wiki:
* `parsermigration-notice-title`
(Override at `[[MediaWiki:Parsermigration-notice-title]]`)
sets the boldfaced title at the top of the notification; setting it
to the empty string suppresses the title.
* `parsermigration-notice`
(Override at `[[MediaWiki:ParserMigration-notice]]`)
sets the body content for the notification.  It is recommended that
this notice provide a link to
https://www.mediawiki.org/wiki/Parsoid/Parser_Unification/Migration
for more information.
