<?php

namespace App\Services;

class StockException extends \Exception
{
    protected $errors;

    public function __construct(array $errors)
    {
        parent::__construct("Stock insuffisant");
        $this->errors = $errors;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
