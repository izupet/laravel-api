<?php

namespace Izupet\Api;

use Illuminate\Database\Eloquent\Model;

class ApiModel extends Model
{
    public function scopeApiSearch($query, array $search)
    {
        return $query->where(function($qu) use ($search) {
            foreach ($search['fields'] as $key => $field) {
                if ($key === 0) {
                    $qu = $qu->where('model', 'LIKE', '%' . $search['q'] . '%');
                } else {
                    $qu = $qu->orWhere($field, 'LIKE', '%' . $search['q'] .  '%');
                }
            }
        });
    }

    public function scopeApiOrder($query, array $order)
    {
        return $query->orderBy($order['field'], $order['direction']);
    }

    public function apiUpdate(...$columns)
    {
        $input = end($columns);

        if (!is_array($input)) {
            throw new \Exception('Not valid method call.');
        }

        $withRelations = false;

        array_pop($columns);

        foreach ($columns as $key => $value) {
            if (!is_string($value) && !in_array($value, ['fields', 'relations'])) {
                throw new \Exception('Some arguments are not valid.');
            }

            switch ($value) {
                case 'fields':
                    $query = $this->setVisible($this->getVisibleFields($input));
                    break;
                case 'relations':
                    $withRelations = true;
                    break;
            }
        }

        $this->update($input['body']);

        if ($withRelations) {
            $this->apiRelations($this, $input['fields']['relations']);
        }

        return $this;
    }

    public function apiCreate(...$columns)
    {
        $input = end($columns);

        if (!is_array($input)) {
            throw new \Exception('Not valid method call.');
        }

        $model = $this->create($input['body']);

        array_pop($columns);

        foreach ($columns as $key => $value) {
            if (!is_string($value) && !in_array($value, ['fields'])) {
                throw new \Exception('Some arguments are not valid.');
            }

            switch ($value) {
                case 'fields':
                    $model->setVisible($this->getVisibleFields($input));
                    break;
            }
        }

        return $model;
    }

    public function apiDelete()
    {
        return $this->delete();
    }

    public function apiRelations($query, array $relations, array $filter = [])
    {
        foreach ($relations as $relation => $fields) {
            $query->load([$relation => function($q) use($fields, $relation, $filter) {
                $q->apiFields($fields);

                if (isset($filter[$relation])) {
                    $q = $this->scopeApiFilter($q, $filter[$relation]);
                }
            }]);
        }

        return $query;
    }

    public function scopeApiFilter($query, array $filter)
    {
        foreach ($filter as $acronym => $f) {
            foreach ($f as $key => $value) {
                $query = $query->where($key, $this->getOperatorFromAcronym($acronym), $value);
            }
        }

        return $query;
    }

    private function getOperatorFromAcronym($acronym)
    {
        switch ($acronym) {
            case 'ne':
                return '<>';
                break;
            case 'gt':
                return '>';
            case 'lt':
                return '<';
            default:
                return '=';
                break;
        }
    }

    public function scopeApiFields($query, array $fields = [], $total = false)
    {
        if ($total) {
            return $query->select(\DB::raw(sprintf('SQL_CALC_FOUND_ROWS %s', implode(',', $fields))));
        } else {
            return $query->select($fields);
        }
    }

    public function scopeApiPagination($query, array $pagination = [])
    {
        return $query
            ->skip($pagination['offset'])
            ->take($pagination['limit']);
    }


    public function scopeApiGet($query, ...$columns)
    {
        $input = end($columns);

        if (!is_array($input)) {
            throw new \Exception('Not valid method call.');
        }

        $withRelations = false;

        array_pop($columns);

        foreach ($columns as $key => $value) {
            if (!is_string($value) && !in_array($value, ['fields', 'relations', 'pagination', 'search', 'order'])) {
                throw new \Exception('Some arguments are not valid.');
            }

            if ($value === 'order' && !isset($input['order'])) {
                continue;
            }

            switch ($value) {
                case 'fields':
                    $query = $this->scopeApiFields($query, $input['fields']['select'], true);
                    break;
                case 'pagination':
                    $query = $this->scopeApiPagination($query, $input['pagination']);
                    break;
                case 'search':
                    $query = $this->scopeApiSearch($query, $input['search']);
                    break;
                case 'order':
                    $query = $this->scopeApiOrder($query, $input['order']);
                    break;
                case 'relations':
                    $withRelations = true;
                    break;
                case 'filter':
                    $query = $this->scopeApiFilter($query, $input['filter']['select']);
                    break;
            }
        }

        $collection = $query->get();

        $total = $this->total();

        $limit = (int) $input['pagination']['limit'];

        $offset = (int) $input['pagination']['offset'];

        if ($withRelations) {
            $this->apiRelations($collection, $input['fields']['relations'], $input['filter']['relations']);
        }

        return (object) compact('total', 'collection', 'limit', 'offset');
    }

    public function total()
    {
        return \DB::select(\DB::raw('SELECT FOUND_ROWS() AS total'))[0]->total;
    }

    private function getVisibleFields(array $input)
    {
        return array_merge($input['fields']['select'], array_keys($input['fields']['relations']));
    }
}
