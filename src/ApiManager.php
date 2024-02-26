<?php

namespace TeamHubcore\ModIntracom;

use DomainException;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use TeamHubcore\ModIntracom\Connection;
use TeamHubcore\ModIntracom\Query\Grammar\ApiGrammar;

class ApiManager implements ConnectionResolverInterface
{
    private $connections = [];

    public function __construct(
        private ApiGrammar $queryGrammar
    ) {
    }

    public function connection($name = null)
    {
        if (null === $name) {
            throw new DomainException('Intracom connection cannot be the default connection.');
        }

        if (empty($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }
        return $this->connections[$name];
    }

    private function makeConnection($name)
    {
        $connection = new Connection($this->queryGrammar, $this->getConfiguration($name));
        return $connection->setName($name);
    }

    /**
     * Get the configuration for a connection.
     *
     * @param  string  $name
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function getConfiguration($name)
    {
        $connections = config('database.connections');

        if (is_null($config = Arr::get($connections, $name))) {
            throw new InvalidArgumentException("Intracom connection [{$name}] not configured.");
        }

        return (new ConfigurationUrlParser)
            ->parseConfiguration($config);
    }

    public function getQueryGrammar()
    {
        return $this->queryGrammar;
    }

    public function getDefaultConnection()
    {
    }

    public function setDefaultConnection($name)
    {
    }
}
