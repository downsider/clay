<?php

namespace Lexide\Clay\Test\Implementation;

use Lexide\Clay\Model\DataTypeHandlingTrait;

/**
 *
 */
class DataTypeHandlingTraitImplementation 
{
    use DataTypeHandlingTrait;

    protected $date;

    public function setDate($date)
    {
        $this->date = $this->handleSetDate($date);
    }

    public function getDate()
    {
        return $this->handleGetDate($this->date);
    }

    public function setDateFormat($format)
    {
        $this->dateFormat = $format;
    }

} 
