# UPGRADE FROM 1.x to 2.0
If you were using this library without extending the code in it, the upgrade path is pretty smooth; you just have to change the following connection options: 
 * `driverClass` is no longer required to be set to `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\*` classes (which no longer exists); you can fall back to the normal DBAL corresponding classes
 * in if you were using `MasterSlaveConnection` or `PrimaryReadReplicaConnection`, you can fall back to DBAL classes too, but you have to follow [the DBAL upgrade instructions](https:\\github.com\doctrine\dbal\blob\3.3.x\UPGRADE.md#deprecated-masterslaveconnection-use-primaryreadreplicaconnection), since `MasterSlaveConnection` was deprecated and removed, and driver options were renamed.

If you were instead extending the code inside this library, you should proceed with caution, because you can expect multiple breaking changes; here's a summary:

### Added
* Support DBAL v3
* Add `GoneAwayDetector` interface and `MySQLGoneAwayDetector` class implementation

### Changed
* Changed `Connection` method signatures to follow [DBAL v3 changes](https://github.com/doctrine/dbal/blob/3.3.x/UPGRADE.md#upgrade-to-30):

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
* Changed `Statement` constructor and method signatures to follow [DBAL v3 changes](https://github.com/doctrine/dbal/blob/3.3.x/UPGRADE.md#upgrade-to-30):
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

### Removed
* Drop support for DBAL v2
* Removed `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\ConnectionTrait` trait, now everything is inside `Connection`
* Removed `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\MasterSlaveConnection` class
* Removed `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\PrimaryReadReplicaConnection` class
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
