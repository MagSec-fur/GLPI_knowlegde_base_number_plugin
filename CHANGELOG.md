# Changelog

## 1.1.0

### Added

- GLPI 11.0 support (GLPI 10.0 is still supported).
- Number shown above the article content, not only in the edit form.
- "Knowledge Item Number" search option (search engine and REST API).
- Console command `plugins:knowledgeautonumber:fill-missing` (with `--dry-run`) to number articles
  that have no number yet.
- Translations use GLPI's gettext system (`locales/xx_XX.po` / `.mo`), now with 14 languages.

### Fixed

- Displaying a form no longer renumbers every article from `KI-0001`. Existing numbers never
  change, and the sequence is never reset (#6).
- New articles get their number when they are created. The `post_item_add` hook used before is not
  a GLPI hook, so new articles only got a number from the next full renumbering (#6).
- No more `Executing direct queries is not allowed!` errors on GLPI 11: the plugin installs (#4)
  and numbers new articles (#5).
- Numbers are allocated with an atomic, transactional increment, and a unique index guarantees that
  two articles never share a number.
- Articles are no longer limited to the first 1000 when numbering existing articles.
- The plugin no longer creates a foreign key on the GLPI core table `glpi_knowbaseitems` (it is
  removed during the update). Numbers of deleted articles are removed by the `item_purge` hook.
- Errors are logged in `files/_log/knowledgeautonumber.log` instead of only PHP's `error_log`, and
  normal page views no longer write to that log.

### Removed

- Unused `knowledgeautonumber.css` stylesheet and the hard-coded translation array.
