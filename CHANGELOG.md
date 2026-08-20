# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- CI workflow now runs only for pushes to main (a tag push previously re-ran
  the whole matrix on an already-tested commit), actions/checkout is bumped
  to v5.1.0 (current node24 runtime), and Dependabot watches the pinned
  actions weekly so future runtime deprecations arrive as pull requests.

### Fixed

- Cross-plugin link in the README now uses an absolute URL so it works from
  GitHub release pages and the plugin directory, not just the repo root.

## [1.0.0-alpha.1] - 2026-08-18

### Added

- Initial pre-release of the availability condition: restricts activities
  and sections by the choice a user has made in a mod_pathway activity, and
  filters user lists (grading tables, reports) to the chosen branch.
- YUI form dialogue for picking the pathway and option, per core's
  M.core_availability.plugin contract.
- Fail-closed evaluation on missing or foreign targets, duplication-safe
  backup and restore matching core availability_completion, privacy
  provider, PHPUnit and Behat suites, and a moodle-plugin-ci GitHub Actions
  workflow.

[1.0.0-alpha.1]: https://github.com/motorpilotltd/moodle-availability_pathway/releases/tag/v1.0.0-alpha.1
