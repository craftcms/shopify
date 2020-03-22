# Shopify Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 1.1.0 - 2020-03-22

### Fixed

-   GitHub-issue #8
-   Translation-files were not used for templates of this plugin. Fixed usage of `|t`-Twig-filter. 

### Added

-   Allow limit and published_status to be configured in the settings menu.
-   Add filter-options
    - Add custom-text filter
    - Add filter to show only selected items
-   UI layout improvements to match Craft3 styles
-   Add .css plugin asset
-   Refactor JS and instance-scoped-html-selectors logic from PR#9
-   Add plugin-translations for german (de).

## 1.0.5 - 2019-10-10

### Fixed

-   GitHub-issue #7

### Added

-   data-normalization to use selected values as an array

## 1.0.4 - 2019-10-07

### Fixed

-   Fixed an issue with class-path for Craft3-Plugin class-name
-   removed -RC1 flag of craftcms dependency
-   use correct craft input-multiselect classes

## 1.0.0 - 2018-07-22

### Added

-   Initial release
