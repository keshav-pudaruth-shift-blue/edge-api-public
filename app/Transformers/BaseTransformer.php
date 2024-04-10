<?php

namespace App\Transformers;


use League\Fractal\TransformerAbstract;

class BaseTransformer extends TransformerAbstract
{
    /**
     * Default date time format
     *
     * @var string
     */
    protected $defaultDateTimeFormat = 'Y-m-d H:i:sP';

    /**
     * Get default data time format
     *
     * @return string
     */
    public function getDefaultDateTimeFormat()
    {
        return $this->defaultDateTimeFormat;
    }
}
