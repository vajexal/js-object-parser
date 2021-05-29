<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser;

trait NumericParser
{
    use ParseInput, ParserError;

    private function parseNumeric(): float|int
    {
        if ($this->consumeString('0b', '0B')) {
            return $this->parseBinaryDigits();
        }

        if ($this->consumeString('0o', '0O')) {
            return $this->parseOctalDigits();
        }

        if ($this->consumeString('0x', '0X')) {
            return $this->parseHexDigits();
        }

        return $this->parseDecimal();
    }

    private function parseBinaryDigits(): int
    {
        $number = '';

        while ($this->char >= '0' && $this->char <= '1' || $this->char === '_') {
            if ($this->char >= '0' && $this->char <= '1') {
                $number .= $this->char;
            } else {
                $prevChar = $this->behind();
                $nextChar = $this->ahead();

                if (!($prevChar >= '0' && $prevChar <= '1') || !($nextChar >= '0' && $nextChar <= '1')) {
                    $this->unexpectedChar();
                }
            }

            $this->nextChar();
        }

        if ($this->char === 'n') {
            $this->nextChar();
        }

        if ($number === '') {
            $this->parseError('No binary digits after "0b"');
        }

        return (int) bindec($number);
    }

    private function parseOctalDigits(): int
    {
        $number = '';

        while ($this->char >= '0' && $this->char <= '7' || $this->char === '_') {
            if ($this->char >= '0' && $this->char <= '7') {
                $number .= $this->char;
            } else {
                $prevChar = $this->behind();
                $nextChar = $this->ahead();

                if (!($prevChar >= '0' && $prevChar <= '7') || !($nextChar >= '0' && $nextChar <= '7')) {
                    $this->unexpectedChar();
                }
            }

            $this->nextChar();
        }

        if ($this->char === 'n') {
            $this->nextChar();
        }

        if ($number === '') {
            $this->parseError('No octal digits after "0o"');
        }

        return (int) octdec($number);
    }

    private function parseHexDigits(): int
    {
        $number = '';

        while (
            ($this->char >= '0' && $this->char <= '9') ||
            ($this->char >= 'a' && $this->char <= 'f') ||
            ($this->char >= 'A' && $this->char <= 'F') || $this->char === '_'
        ) {
            if (($this->char >= '0' && $this->char <= '9') || ($this->char >= 'a' && $this->char <= 'f') || ($this->char >= 'A' && $this->char <= 'F')) {
                $number .= $this->char;
            } else {
                $prevChar = $this->behind();
                $nextChar = $this->ahead();

                if (
                    !(($prevChar >= '0' && $prevChar <= '9') || ($prevChar >= 'a' && $prevChar <= 'f') || ($prevChar >= 'A' && $prevChar <= 'F')) ||
                    !(($nextChar >= '0' && $nextChar <= '9') || ($nextChar >= 'a' && $nextChar <= 'f') || ($nextChar >= 'A' && $nextChar <= 'F'))
                ) {
                    $this->unexpectedChar();
                }
            }

            $this->nextChar();
        }

        if ($this->char === 'n') {
            $this->nextChar();
        }

        if ($number === '') {
            $this->parseError('No hexadecimal digits after "0x"');
        }

        return (int) hexdec($number);
    }

    private function parseDecimal(): float|int
    {
        $isFloat         = false;
        $decimalPosition = $this->position;
        $number          = $this->parseDecimalDigits();

        if ($this->char === '.') {
            $number  .= $this->char;
            $isFloat = true;
            $this->nextChar();
        }

        $number .= $this->parseDecimalDigits();

        if ($this->char === 'e' || $this->char === 'E') {
            if ($number === '.') {
                $this->prevChar();
                $this->unexpectedChar();
            }

            $number .= 'e';
            $this->nextChar();

            if ($this->char === '+' || $this->char === '-') {
                $number .= $this->char;
                $this->nextChar();
            }

            $number .= $exponentPart = $this->parseDecimalDigits();

            if ($exponentPart === '') {
                $this->moveTo($decimalPosition);
                $this->parseError('Invalid decimal');
            }
        }

        if ($this->char === 'n') {
            if ($isFloat || isset($exponentPart)) {
                $this->unexpectedChar();
            }

            $this->nextChar();
        }

        return $isFloat ? (float) $number : (int) $number;
    }

    private function parseDecimalDigits(): string
    {
        $digits = '';

        while ($this->char >= '0' && $this->char <= '9' || $this->char === '_') {
            if ($this->char >= '0' && $this->char <= '9') {
                $digits .= $this->char;
            } else {
                $prevChar = $this->behind();
                $nextChar = $this->ahead();

                if (!($prevChar >= '0' && $prevChar <= '9') || !($nextChar >= '0' && $nextChar <= '9')) {
                    $this->unexpectedChar();
                }
            }

            $this->nextChar();
        }

        return $digits;
    }
}
