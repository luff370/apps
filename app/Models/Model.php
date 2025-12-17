<?php

namespace App\Models;

use DateTimeInterface;
use App\Support\Traits\QueryTrait;
use Illuminate\Database\Eloquent\Model as BaseModel;

class Model extends BaseModel
{
    use QueryTrait;

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $dateFormat = 'U';

    // protected $dateFormat = CarbonInterface::DEFAULT_TO_STRING_FORMAT;

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param \DateTimeInterface $date
     *
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function getData($field)
    {
        return $this->$field ?? '';
    }
}
