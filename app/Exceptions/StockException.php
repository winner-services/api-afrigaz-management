<?php

namespace App\Exceptions;

use Exception;

class StockException extends Exception
{
    protected $errors;

    public function __construct($message, $errors = null)
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'type' => 'stock_error',
            'message' => $this->getMessage(),
            'errors' => $this->errors
        ], 400);
    }
}
