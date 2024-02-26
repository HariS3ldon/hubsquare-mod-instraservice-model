<?php
namespace TeamHubcore\ModIntracom\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use stdClass;

trait ProcessResponses {

    private function responseToEntity(stdClass $response, bool $exists = true): Model
    {
        return $this->createEntityFromResponseObject($response, $exists);
    }

    private function createEntityFromResponseObject(stdClass $entity, bool $exists = true): Model
    {
        $array = (array)$entity;
        $model = $this->model->newInstance((array)$entity); //todo: relations should be handled here
        $model->exists = $exists;
        return $model;
    }

    private function responseToCollection(stdClass|array $response, bool $exists = true): Collection
    {
        if (!is_array($response)) {
            $response = [$response];
        }
        $collection = new Collection();
        foreach($response as $obj) {
            $collection->add(
                $this->createEntityFromResponseObject($obj, $exists)
            );
        }
        return $collection;
    }
}