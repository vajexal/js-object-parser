<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser;

trait ArrayParser
{
    use ParseInput,
        ParserError;

    private function parseArray(): array
    {
        $elements = [];
        $index    = 0;
        $this->nextChar();

        while ($this->char !== ']') {
            $this->skipSpaces();

            if ($this->char === '') {
                $this->unexpectedEndOfInput();
            } elseif ($this->char === ',') {
                $index++;
                $this->nextChar();
                continue;
            } elseif ($this->char === ']') {
                break;
            }

            $elements[$index] = $this->parseExpression();

            $this->skipSpaces();

            if ($this->char === '') {
                $this->unexpectedEndOfInput();
            } elseif ($this->char === ']') {
                break;
            } elseif ($this->char !== ',') {
                $this->unexpectedChar(',');
            }

            $index++;
            $this->nextChar();
        }

        $this->nextChar();

        return $elements;
    }
}
