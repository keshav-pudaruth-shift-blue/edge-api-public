<?php
/**
 * Created by PhpStorm.
 * User: Keshav Pudaruth
 * Date: 10/10/2018
 * Time: 14:08
 */

namespace App\Repositories;


use Illuminate\Database\Eloquent\Model;

class BaseRepository
{
    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $query;

    /**
     * Get new query builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getQuery()
    {
        return $this->model->query();
    }

    /**
     * Get Eloquent model
     *
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    public function find($id) {
        return $this->getQuery()->find($id);
    }
}
