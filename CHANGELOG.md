# Shopify Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.0.0 - 2020-04-14

### Added

-   Reduce payload significantly by only requesting required fields (#18).

#### Migration:

If you need more (in your response-payload of all products) than the fields which are loaded per default now (id, title, variants) you need to pass them as `String[]` to your `getProducts()` call. 

### Fixed

-   GitHub-issue #14
    
    
## 1.2.1 - 2020-03-28

### Added

-   Added support for Shopify Collections (both smart and custom)
    - Implies adding a new fieldtype: Shopify Collection

## 1.1.1 - 2020-03-26

### Fixed

-   GitHub-issue #15
-   GitHub-issue #16
-   GitHub-issue #11

### Added

-   Graphql Support

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
