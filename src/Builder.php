<?php

namespace TeamHubcore\ModIntracom;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use stdClass;
use TeamHubcore\ModIntracom\Concerns\ProcessResponses;

class Builder extends EloquentBuilder
{
    use ProcessResponses;

    public function __construct(QueryBuilderContract $query)
    {
        $this->query = $query;
    }

    public function create(array $attributes = [])
    {
        $model = $this->newModelInstance($attributes);
        $model->save();
        return $model;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function find($id, $columns = ['*']): Model
    {   
        $res = $this->query->find($id);
        if (!empty($res)) {
            return $this->responseToEntity($res);
        }
        return null;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($column instanceof Closure && is_null($operator)) {
            $column($query = $this->model->newQueryWithoutRelationships());

            $this->query->addNestedWhereQuery($query->getQuery(), $boolean);
        } else {
            $this->query->where(...func_get_args());
        }

        return $this;
    }
}
