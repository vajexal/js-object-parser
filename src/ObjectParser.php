<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser;

trait ObjectParser
{
    use ParseInput,
        ParserError,
        NumericParser,
        StringParser;

    private function parseObject(): array
    {
        $object = [];
        $this->nextChar();

        while ($this->char !== '}') {
            $this->skipSpaces();

            if ($this->char === '') {
                $this->unexpectedEndOfInput();
            }

            if ($this->char >= '0' && $this->char <= '9' || $this->char === '.') {
                $key = (int) $this->parseDecimalDigits();
            } elseif ($this->char === '"' || $this->char === "'") {
                $key = $this->parseString();
            } else {
                $key = $this->parseIdentifierName();
                if ($key === '') {
                    $this->unexpectedChar();
                }
            }

            $this->skipSpaces();

            if ($this->char !== ':') {
                $this->unexpectedChar(':');
            }

            $this->nextChar();
            $this->skipSpaces();

            $object[$key] = $this->parseExpression();

            $this->skipSpaces();

            if ($this->char === '') {
                $this->unexpectedEndOfInput();
            } elseif ($this->char === '}') {
                break;
            } elseif ($this->char === ',') {
                $this->nextChar();
                $this->skipSpaces();
            } else {
                $this->unexpectedChar(',');
            }
        }

        $this->nextChar();

        return $object;
    }
}
