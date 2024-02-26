<?php
namespace TeamHubcore\ModIntracom\Concerns;

use TeamHubcore\ModIntracom\ApiManager;
use TeamHubcore\ModIntracom\Builder;
use Illuminate\Support\Str;

trait HasRemoteModel {

    private array $unsuportedMethods = [
        'whereKey',
        'cursor',
        'chuck',
        'raw',
        'selectRaw',
        'whereFullText',
        'whereBetween',
        'orWhere',
        'orWhereNot',
        'orWhereBetween',
        'whereIn',
        'whereNotIn',
        'whereNot',
        'whereNotExists',
        'select'
    ];

    //@todo: after saving populate eventual related models in relationship attributes
    public static function boot()
    {
        parent::boot();

        static::setConnectionResolver(app(ApiManager::class));
    }
/*
    public function newQuery() {
        return app(QueryBuilder::class);
    }
*/
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $query = $this->getConnection()->query();
        $query->setModel($this);
        return $query;
    }

    protected function stopUnsupportedMethods(string $method) {
        if (in_array($method, $this->unsuportedMethods)) {
            throw new \Exception('Method not suppoterd in remote models');
        }
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $this->stopUnsupportedMethods($method);

        if (in_array($method, ['increment', 'decrement', 'incrementQuietly', 'decrementQuietly'])) {
            return $this->$method(...$parameters);
        }

        if ($resolver = $this->relationResolver(static::class, $method)) {
            return $resolver($this);
        }

        if (Str::startsWith($method, 'through') &&
            method_exists($this, $relationMethod = Str::of($method)->after('through')->lcfirst()->toString())) {
            return $this->through($relationMethod);
        }

        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }
}