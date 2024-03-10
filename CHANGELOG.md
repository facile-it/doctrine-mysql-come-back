# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]
* ...

## [3.0.0] - 2024-03-10
This new major version is identical to the previous one; the only changes are the ones needed to maintain compatibility with the new DBAL major, so mainly signature changes.

### Added
* Support DBAL 4

### Removed
* Drop support for DBAL 3

## [2.0.0] - 2023-06-08
This is the final, stable release of the new version of this library, supporting DBAL 3.6+; unfortunately, DBAL 3.0 to 3.5 is unsupported (but upgrading to 3.6 should not be an issue).

If you're upgrading from a 1.x version, please refer to the [UPGRADE-2.0.md](./UPGRADE-2.0.md) document.

The version has no changes from 2.0.0-BETA4; the following is the detailed changelog from the 1.x series:

### Added
* Support DBAL v3.6+
* Add `GoneAwayDetector` interface and `MySQLGoneAwayDetector` class implementation
* Add `setGoneAwayDetector` method to the connections
* Add handling of AWS MySQL RDS connection loss
* Add validation to `x_reconnect_attempts`
* Add mutation testing with Infection

### Changed
* Change `Connection` method signatures to follow [DBAL v3 changes](https://github.com/doctrine/dbal/blob/3.3.x/UPGRADE.md#upgrade-to-30):

```diff
namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection as DBALConnection;
+use Doctrine\DBAL\Result;

class Connection extends DBALConnection
{
// ...
-    public function prepare($sql)
+    public function prepare(string $sql): DBALStatement
// ...
-    public function executeQuery($query, array $params = array(), $types = array(), QueryCacheProfile $qcp = null)
+    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
// ...
}
```
* Change `Statement` constructor and method signatures to follow [DBAL v3 changes](https://github.com/doctrine/dbal/blob/3.3.x/UPGRADE.md#upgrade-to-30):
```diff
namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection as DBALConnection;
+use Doctrine\DBAL\Result;

class Statement extends \Doctrine\DBAL\Statement
{
// ...
-    public function __construct($sql, ConnectionInterface $conn)
+    public function __construct(Connection $retriableConnection, Driver\Statement $statement, string $sql)
// ...
-    public function executeQuery($query, array $params = array(), $types = array(), QueryCacheProfile $qcp = null)
+    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
// ...
}
```

### Fixed
* In `PrimaryReadReplicaConnection`, fetch `driverOptions` from under the `primary` key

### Removed
* Drop support for DBAL v2
* Drop support for PHP 7.3
* Removed `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\MasterSlaveConnection` class
* Removed `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareInterface` interface
* Removed `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareTrait` trait
* Removed `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\Mysqli\Driver` class
* Removed `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySQL\Driver` class
* Removed `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDO\MySQL\Driver` class
* Removed `Connection::query()` method (due to drop in DBAL v3)
* Removed `Connection::refresh()` method (due to drop in DBAL v3)
* Removed `Connection::isUpdateQuery()` method (logic is now behind the `GoneAwayDetector` interface)
* Removed `Statement::bindValue()` method
* Removed `Statement::bindParam()` method
* Removed `Statement::execute()` method
* Removed `Statement::setFetchMode()` method

## [2.0.0-BETA4] - 2023-04-05
### Added
* Centralize reconnect attempts counter
* Add `Statement::fromDBALStatement` for simpler creation
* Add mutation testing with Infection
### Fixed
* Avoid reconnection attempts if a transaction was opened (and not closed) before the "gone away" error
* Avoid retrying `SAVEPOINT` statements
* Handle `Statement::bindParam` and `Statement::bindValue` correctly on reconnection  
### Changed
* Refactor `Connection` retry logic into a single method
* Make `Statement::__construct` private

## [2.0.0-BETA3] - 2023-04-02
### Added
* Add validation to `x_reconnect_attempts`
### Fixed
* In `PrimaryReadReplicaConnection`, fetch `driverOptions` from under the `primary` key

## [2.0.0-BETA2] - 2023-04-01
### Added
* Added `PrimaryReadReplicaConnection` and `ConnectionTrait` back 
* Added handling of AWS MySQL RDS connection loss
### Fixed
* Fixed `beginTransaction` function to have reconnection support
### Removed
* Drop support for DBAL < 3.6
* Drop support for PHP 7.3

## [2.0.0-BETA1] - 2022-06-23
### Added
* Support DBAL v3
* Add `GoneAwayDetector` interface and `MySQLGoneAwayDetector` class implementation

### Changed
* Changed `Connection` constructor and method signatures to follow DBAL v3 changes

### Removed
* Drop support for DBAL v2
* Removed Driver classes
* Removed `ConnectionTrait`, now everything is inside `Connection`
* Removed `Connection::refresh()` method (due to drop in DBAL v3)
* Removed `Connection::isUpdateQuery()` method (logic is now behind the `GoneAwayDetector` interface)
* Removed specialized `MasterSlaveConnection` and `PrimaryReadReplicaConnection` (due to drop of corresponding classes in DBAL v3)

## [1.10.0] - 2021-03-25
### Added
* Added PHP 8 support
* Added compatibility with doctrine/dbal > 2.11 Statement
* Added ability to reconnect when creating `Mysqli` statement
### Changed
* `Statement` now extends the original one, so all methods are implemented now
* `Connection::refresh()` is deprecated, you should use the original `Connection::ping()`

## [1.9.0] - 2020-11-02
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
