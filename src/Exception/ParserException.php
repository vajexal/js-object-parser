<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser\Exception;

use Exception;
use Throwable;

class ParserException extends Exception
{
    protected string $str;
    protected int    $position;

    public function __construct(string $message, string $str, int $position, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->str      = $str;
        $this->position = $position;
    }

    public function getStr(): string
    {
        return $this->str;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
