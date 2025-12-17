<?php

namespace App\Models;

use DateTimeInterface;
use App\Support\Traits\QueryTrait;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    use QueryTrait;

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
