<?php

namespace TeamHubcore\ModIntracom\Query;

use Exception;
use Illuminate\Contracts\Database\Query\Builder as BuilderInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;
use TeamHubcore\ModIntracom\Concerns\ProcessResponses;
use TeamHubcore\ModIntracom\Connection;

class Builder implements BuilderInterface
{
    use ProcessResponses;

    public Model $model;

    public string $from;

    private array $where = [];

    private array $orderBy = [];

    private ?int $limit = null;

    public function __construct(private ConnectionInterface $connection)
    {
    }

    public function __call($method, $parameters)
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }
        throw new Exception('Method not found: ' . $method . '. This is a remote model.');
    }

    public function get()
    {
        $data = $this->where;
        if (!empty($this->orderBy)) {
            $data['order_by'] = $this->orderBy;
        }
        if (!empty($this->limit)) {
            $data['limit'] = $this->limit;
        }
        return $this->responseToCollection($this->getConnection()->select($this->from, $data));
    }

    public function first()
    {
        return $this->responseToEntity($this->getConnection()->selectOne($this->from, $this->where));
    }

    public function insertGetId(array $values, $sequence = null)
    {
        /** @var Connection $connection */
        $connection = $this->getConnection();
        return $connection->insertGet($this->from, $values)->id;
    }

    public function from($table)
    {

        $this->from = $table;

        return $this;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($value && '=' !== $operator) {
            throw new Exception('Remote Entities do not support more than 2 parameters (column, value) or the "=" operator.');
        }
        $this->where[$column] = '=' === $operator ? $value : $operator;
        return $this;
    }

    public function delete()
    {
        return $this->getConnection()->delete($this->from, $this->where);
    }

    public function select($columns = ['*'])
    {
        //todo: remote select
    }

    public function find($id, $columns = ['*'])
    {
        try {
            return $this->getConnection()->selectOne($this->from, ['id' => $id]);
        } catch (HttpException $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }
        }
    }

    public function create(array $attributes = [])
    {
        return $this->getConnection()->insert($this->from, $attributes);
    }

    public function update(array $values)
    {
        $this->getConnection()->update($this->from, $values);
    }

    public function orderBy($column, $direction = 'asc')
    {
        $this->orderBy[$column] = $direction;
        return $this;
    }

    public function setModel(Model $model)
    {
        $this->model = $model;
        return $this;
    }

    public function limit(int $value)
    {
        $this->limit = $value;
        return $this;
    }
}
