<?php
declare(strict_types=1);

namespace Hyperf\DB;

use Hyperf\Contract\ConnectionInterface;
use Hyperf\Pool\Connection as BaseConnection;
use Hyperf\Pool\Exception\ConnectionException;
use Hyperf\Pool\Pool;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine\MySQL;

class SwooleMysqlConnection extends BaseConnection implements ConnectionInterface
{

    /**
     * @var PDO
     */
    protected $connection;

    /**
     * @var array
     */
    protected $config = [
        'driver' => 'swoole_mysql',
        'host' => 'localhost',
        'database' => 'test',
        'username' => 'root',
        'password' => 'root',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => '60',
        ],
    ];

    /**
     * Current redis database.
     * @var null|int
     */
    protected $database;

    public function __construct(ContainerInterface $container, Pool $pool, array $config)
    {
        parent::__construct($container, $pool);
        $this->config = array_replace($this->config, $config);

        $this->reconnect();
    }

    public function __call($name, $arguments)
    {
        return $this->connection->{$name}(...$arguments);
    }

    public function getActiveConnection()
    {
        if ($this->check()) {
            return $this;
        }

        if (!$this->reconnect()) {
            throw new ConnectionException('Connection reconnect failed.');
        }

        return $this;
    }

    /**
     * Reconnect the connection.
     */
    public function reconnect(): bool
    {
        $connection = new MySQL();
        $connection->connect([
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'user' => $this->config['username'],
            'password' => $this->config['password'],
            'database' => $this->config['database'],
            'timeout' => $this->config['pool']['connect_timeout'],
            'charset' => $this->config['charset'],
            'strict_type' => false,
        ]);

        $this->connection = $connection;
        $this->lastUseTime = microtime(true);

        return true;
    }

    /**
     * Close the connection.
     */
    public function close(): bool
    {
        unset($this->connection);

        return true;
    }
}