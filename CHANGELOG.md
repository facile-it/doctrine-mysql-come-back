# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [1.9.0] - TBD
### Added
 * Added compatibility with doctrine/dbal 2.11
 * Added Github Actions for CI
### Changed
 * Bumped minimum PHP version to 7.3
 * Updated dependencies
 * Added functional tests
### Fixed
 * Fixed compatiblity with doctrine/dbal 2.11

## [1.8] - 2019-09-05
### Fixed
 * Fixed issue about loss of state for Statement class on retry (#34)
 * Added DBAL's MasterSlaveConnection support (#33)

## [1.7] - 2019-02-05
### Fixed
 * Compatibility with doctrine/dbal up to version 2.9 (#32)

## [1.6.5] - 2018-04-20
### Changed
 * Changed the version constraint of `doctrine/dbal` to `^2.3` to allow v2.7 (#26)

## [1.6.4] - 2017-09-01
### Added
 * Compatibility with doctrine/dbal up to version 2.6 (#22)

### Changed
 * Bumped minimum PHP version to 5.6 (#21)
 * Updated various dev dependecies
 * Simplified the Travis CI build matrix

## [1.6.3] - 2016-09-30
### Added
 * Added a test suite

### Fixed
 * Fix issue for not retrying a query that has a new line after the `SELECT` keyword (#19)
 * Avoid retrying `UPDATE` queries to avoid issues (#17)
 
## [1.6.2] - 2016-06-03
### Added
 * Added a test suite (#16)
 * Added a configuration example for ZendFramework (#13)

### Fixed
 * Fix issue with `Driver::connect()` (#14) 
 
## [1.6.1] - 2016-05-16
### Added
 * Add support for MySQLi (#9)

### Changed
 * Refactor and eliminate duplication using a trait

### Fixed
 * Explicit the requirement for PHP >= 5.4
 
## [1.6] - 2015-11-26
### Added
 * Create the `ServerGoneAwayExceptionsAwareInterface`

### Fixed
 * Handle correctly the retrying of `beginTransaction()`
