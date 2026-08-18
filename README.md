# Pathway availability condition for Moodle (availability_pathway)

An availability restriction that pairs with the
[mod_pathway](../moodle-mod_pathway) activity module. It restricts access to
course activities and sections based on the selection a user has made in a
pathway activity, either any option or one particular option.

## How it works

- A teacher adds a "Pathway" restriction to an activity or section, picks the
  pathway activity it depends on, and optionally narrows it to one option.
- With no option specified the condition matches any recorded choice, which is
  equivalent to the pathway's own completion rule. Naming an option is the part
  completion cannot express: it opens content to one branch only.
- A recorded choice is treated as branch membership, so user lists for a
  restricted activity (grading tables, reports, messaging) are filtered to the
  matching branch. Users who can ignore availability restrictions, such as
  teachers, are never filtered out.
- If the referenced activity or option no longer exists, the condition fails
  closed: nobody meets it (everybody, where the restriction is negated).

## Backup and restore

Duplicating a restricted activity within a course keeps the restriction
pointing at the existing pathway. On a full course restore the activity and
option references are remapped to the restored copies. Where a reference
cannot be resolved, the condition is cleared and a warning is written to the
restore log.

## Requirements

- Moodle 4.1 or later (tested against 4.1 to 5.2, matching mod_pathway).
- mod_pathway 2026081700 or later, installed first.

## Installation

1. Copy this directory to `availability/condition/pathway` in your Moodle root.
2. Visit Site administration, or run `php admin/cli/upgrade.php`.

## Status

Alpha. A PHPUnit suite and Behat feature are included, along with a
moodle-plugin-ci GitHub Actions workflow. Still, please test in a staging
environment before using this anywhere that matters.

## Licence

Copyright © 2026 Jon Bolton, Simon Lewis

GPL v3 or later.
