<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser;

use Vajexal\JsObjectParser\Exception\ParserException;

trait ParserError
{
    private function parseError(string $message): void
    {
        throw new ParserException($message, $this->str, $this->position);
    }

    private function unexpectedChar(string $expected = ''): void
    {
        $message = "Unexpected char '{$this->char}' at {$this->position}";

        if ($expected !== '') {
            $message .= ", expected '{$expected}'";
        }

        $this->parseError($message);
    }

    private function unexpectedEndOfInput(): void
    {
        $this->parseError('Unexpected end of input');
    }

    private function invalidUnicodeEscapeSequence(int $position): void
    {
        $this->moveTo($position);
        $this->parseError("Invalid Unicode escape sequence at {$this->position}");
    }

    private function invalidHexadecimalEscapeSequence(int $position): void
    {
        $this->moveTo($position);
        $this->parseError("Invalid hexadecimal escape sequence at {$this->position}");
    }
}
