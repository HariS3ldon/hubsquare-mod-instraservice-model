<?php

namespace TeamHubcore\ModIntracom;

use Closure;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use ReflectionProperty;
use stdClass;
use TeamHubcore\ModIntracom\Query\Builder;
use TeamHubcore\ModIntracom\Query\Grammar\ApiGrammar;

class Connection implements ConnectionInterface
{

    private const DEFAULT_API_PROTOCOLL = 'http://';

    private string $name;

    public function __construct(
        private ApiGrammar $queryGrammar,
        private array $configurations,
    ) {
    }

    public function setName(string $name): self
    {
        $rp = new ReflectionProperty(self::class, 'name');
        if ($rp->isInitialized($this)) {
            throw new \Exception('Connection name already set');
        }
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        $rp = new ReflectionProperty(self::class, 'name');
        if (!$rp->isInitialized($this)) {
            throw new \Exception('Connection name not set');
        }
        return $this->name;
    }

    private function getApiVersion(): string
    {
        if (isset($this->configurations['api_version'])) {
            if (0 !== strpos($this->configurations['api_version'], 'v')) {
                $this->configurations['api_version'] = 'v' . $this->configurations['api_version'];
            }
            return $this->configurations['api_version'];
        }
        return 'v1';
    }

    private function getApiProtocol(): string
    {
        return $this->configurations['api_protocol'] ?? self::DEFAULT_API_PROTOCOLL;
    }

    private function getUrl(string $entityName, $path = null): string
    {
        if (!isset($this->configurations['api_base_url'])) {
            throw new \Exception('API base URL not set in database configuration');
        }
        $baseUrl = rtrim($this->configurations['api_base_url'], '/');
        $path = $path ? '/' . ltrim($path, '/') : '';
        return $this->getApiProtocol() . $baseUrl . '/api/' . $this->getApiVersion() .  '/' . $entityName . $path;
    }

    private function performHttpRequest(string $method, string $entityName, array $data, string $path = ''): array|stdClass
    {
        $method = strtolower($method);
        if (!in_array($method, ['get', 'post', 'put', 'delete'])) {
            throw new \Exception('Invalid HTTP method');
        }
        if (isset($data['id'])) {
            $path = $data['id'] . ($path ? '/' . rtrim($path, '/') : '');
            unset($data['id']);
        }
        $url = $this->getUrl($entityName, $path);
        $data = $this->preprocessData($data);
        $response = Http::withToken(request()->bearerToken())
            ->withHeaders([
                'Accept' => 'applilimitecation/json',
                'Content-Type' => 'application/json',
            ])->{$method}(
                $url,
                $data
            );
        // the json is decoded using standard objects to easily recognize the entity from the "collections"
        $output = json_decode($response->getBody()->getContents(), false);
        if ($response->successful()) {
            if ($output && property_exists($output, 'data')) {
                return $output->data;
            }
            return [];
        }
        throw new HttpClientException(
            'Intracom HTTP client error: ' . $output->message ?? 'Unknown error',
            $response->status(),
        );
    }

    public function preprocessData(array $data): array
    {
        if (isset($data['limit'])) {
            $data['per_page'] = $data['limit'];
            unset($data['limit']);
        }
        return $data;
    }

    /**
     * This exists for compatibility with eloquent
     *
     * @return void
     */
    public function getQueryGrammar()
    {
        return $this->queryGrammar;
    }

    public function query()
    {
        return new Builder($this);
    }

    public function table($table, $as = null)
    {
        if ($as) {
            throw new \Exception('Table aliases are not supported by the Intracom driver.');
        }
        $this->query()->from($table);
        return $this;
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return array
     */
    public function insert($entityName, $bindings = [])
    {
        try {
            $this->performInsert($entityName, $bindings);
            return true;
        } catch (HttpClientException $e) {
            return false;
        }
    }

    private function performInsert($entityName, $bindings = [])
    {
        return $this->performHttpRequest('post', strtolower($entityName), $bindings);
    }

    public function insertGet($entityName, $bindings = []): stdClass
    {
        return $this->performInsert($entityName, $bindings);
    }

    /**
     * Get a new raw query expression.
     *
     * @param  mixed  $value
     * @return \Illuminate\Contracts\Database\Query\Expression
     */
    public function raw($value)
    {
        throw new Exception('Method not supported by the Intracom driver.');
    }

    /**
     * Run a select statement and return a single result.
     *
     * @param  string  $entityName
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return mixed
     */
    public function selectOne($entityName, $bindings = [], $useReadPdo = true)
    {
        $path = 'search';
        if (isset($bindings['id']) && is_numeric($bindings['id'])) {
            $path = $bindings['id'];
            $bindings = [];
        }
        return $this->performHttpRequest('get', strtolower($entityName), $bindings, $path);
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($entityName, $bindings = [], $useReadPdo = true)
    {
        $path = 'search';
        return $this->performHttpRequest('get', strtolower($entityName), $bindings, $path);
    }

    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        //@todo: cursor
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $entityName
     * @param  array  $bindings
     * @return int
     */
    public function update($entityName, $bindings = [])
    {
        if (isset($bindings['id']) && is_numeric($bindings['id'])) {
            $path = $bindings['id'];
            unset($bindings['id']);
        }
        $this->performHttpRequest('put', $entityName, $bindings, $path);
        return 1; //we affect always 1 row
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $entityName
     * @param  array  $bindings
     * @return int
     */
    public function delete($entityName, $bindings = [])
    {
        if (1 < count($bindings) || !isset($bindings['id']) || !is_numeric($bindings['id'])) {
            throw new \Exception('Intracom driver only supports delete by single id.');
        }
        $this->performHttpRequest('delete', $entityName, [], $bindings['id']);
        return 1;
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        //@todo statement
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        //@todo affectingStatement
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
        //@todo unprepared
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        //@todo prepareBindings
    }

    /**
     * Execute a Closure within a transaction.
     *
     * @param  \Closure  $callback
     * @param  int  $attempts
     * @return mixed
     *
     * @throws \Throwable
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        //@todo transaction
    }

    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function beginTransaction()
    {
    }

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit()
    {
    }

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack()
    {
        //@todo rollBack
    }

    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transactionLevel()
    {
        //@todo transactionLevel
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param  \Closure  $callback
     * @return array
     */
    public function pretend(Closure $callback)
    {
        //@todo pretend
    }

    /**
     * Get the name of the connected database.
     *
     * @return string
     */
    public function getDatabaseName()
    {
        //@todo getDatabaseName
    }
}
