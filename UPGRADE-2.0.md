# UPGRADE FROM 1.x to 2.0
## Configuration changes
If you were using this library without extending the code in it, the upgrade path is pretty smooth; you just have to change the following connection options: 
 * change `driverClass` option: it's no longer required to be set to `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\*` classes (which no longer exists); you can fall back to the normal DBAL corresponding classes (which are not `final` and cannot be extended anyway)
 * if you use a primary/replica connection:
   * replace usages of `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\MasterSlaveConnection` with `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\PrimaryReadReplicaConnection`: this follows the same rename happened in DBAL v2 vs v3, and you have to follow [the DBAL upgrade instructions](https:\\github.com\doctrine\dbal\blob\3.3.x\UPGRADE.md#deprecated-masterslaveconnection-use-primaryreadreplicaconnection), since some driver options were renamed too.
   * move `driverOptions` under the `primary` key: having it in the root of `$params` was a bug that got fixed in DoctrineBundle 2.6.3, see [doctrine/DoctrineBundle#1541](https://github.com/doctrine/DoctrineBundle/issues/1541)

## Internal changes
If you were instead extending the code inside this library, you should proceed with caution, because you can expect multiple breaking changes; here's a summary:

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
