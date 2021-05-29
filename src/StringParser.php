<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser;

trait StringParser
{
    use ParseInput, ParserError;

    /**
     * @psalm-suppress InvalidReturnType
     */
    private function parseString(): string
    {
        $str        = '';
        $terminator = $this->char;
        $this->nextChar();

        while ($this->char !== '') {
            if ($this->char === $terminator) {
                $this->nextChar();
                return $str;
            }

            if ($this->char === '\\') {
                $this->nextChar();

                if ($this->char === '0') {
                    $str .= "\0";
                    $this->nextChar();
                } elseif ($this->char === 'x') {
                    $this->nextChar();
                    $str .= $this->parseHexEscapeSequence();
                } elseif ($this->char === 'u') {
                    $this->nextChar();
                    $str .= $this->parseUnicodeEscapeSequence();
                } elseif (isset(self::SINGLE_ESCAPE_CHARACTERS[$this->char])) {
                    $str .= self::SINGLE_ESCAPE_CHARACTERS[$this->char];
                    $this->nextChar();
                } elseif ($this->consumeString(self::CRLF)) {
                    $str .= self::CRLF;
                } elseif (\in_array($this->char, self::LINE_TERMINATORS, true)) {
                    $str .= $this->char;
                    $this->nextChar();
                }

                continue;
            }

            if ($this->char === self::LF || $this->char === self::CR) {
                $this->parseError("Unexpected line terminator at {$this->position}");
            }

            $str .= $this->char;
            $this->nextChar();
        }

        $this->unexpectedEndOfInput();
    }

    private function parseIdentifierName(): string
    {
        // todo refactor
        $name = '';

        if ($this->consumeString('\\u')) {
            $unicodeEscapeSequencePosition = $this->position - 2;
            $name                          .= $unicodeChar = $this->parseUnicodeEscapeSequence();
            if (!$this->checkIdentifierStart($unicodeChar)) {
                $this->invalidUnicodeEscapeSequence($unicodeEscapeSequencePosition);
            }
        } elseif ($this->checkIdentifierStart($this->char)) {
            $name .= $this->char;
            $this->nextChar();
        } else {
            return $name;
        }

        while (true) {
            if ($this->consumeString('\\u')) {
                $unicodeEscapeSequencePosition = $this->position - 2;
                $name                          .= $unicodeChar = $this->parseUnicodeEscapeSequence();
                if (!$this->checkIdentifierPart($unicodeChar)) {
                    $this->invalidUnicodeEscapeSequence($unicodeEscapeSequencePosition);
                }
                continue;
            }

            if ($this->checkIdentifierPart($this->char)) {
                $name .= $this->char;
                $this->nextChar();
                continue;
            }

            break;
        }

        return $name;
    }

    private function checkIdentifierStart(string $char): bool
    {
        return $char === '$' || $char === '_' ||
               ($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z') ||
               preg_match(self::ID_START_PATTERN, $char) ||
               \in_array($char, self::OTHER_ID_START, true);
    }

    private function checkIdentifierPart(string $char): bool
    {
        return $char === '$' || $char === '_' ||
               ($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z') ||
               ($char >= '0' && $char <= '9') ||
               preg_match(self::ID_CONTINUE_PATTERN, $char) ||
               \in_array($char, self::OTHER_ID_START, true) || \in_array($char, self::OTHER_ID_CONTINUE, true) ||
               $char === self::ZWNJ || $char === self::ZWJ;
    }

    private function parseUnicodeEscapeSequence(): string
    {
        $unicodeEscapeSequencePosition = $this->position - 2;
        $code                          = $this->char === '{' ? $this->parseCodePoint() : $this->parseHex4DigitsUnicodeEscapeSequence();

        $code = (int) hexdec($code);

        if ($code > self::MAX_CODE_POINT_VALUE) {
            $this->invalidUnicodeEscapeSequence($unicodeEscapeSequencePosition);
        }

        $char = mb_chr($code);
        if ($char === false) {
            $this->invalidUnicodeEscapeSequence($unicodeEscapeSequencePosition);
        }

        return $char;
    }

    private function parseCodePoint(): string
    {
        $code              = '';
        $codePointPosition = $this->position - 2;
        $this->nextChar();

        while ($this->char !== '}') {
            $code .= $this->parseHexDigit();
        }

        $this->nextChar();

        if ($code === '') {
            $this->invalidUnicodeEscapeSequence($codePointPosition);
        }

        return $code;
    }

    private function parseHex4DigitsUnicodeEscapeSequence(): string
    {
        $code              = '';
        $hexDigitsPosition = $this->position - 2;

        for ($i = 0; $i < 4; $i++) {
            if (($this->char >= '0' && $this->char <= '9') || ($this->char >= 'a' && $this->char <= 'f') || ($this->char >= 'A' && $this->char <= 'F')) {
                $code .= $this->char;
                $this->nextChar();
            } else {
                $this->invalidUnicodeEscapeSequence($hexDigitsPosition);
            }
        }

        return $code;
    }

    private function parseHexEscapeSequence(): string
    {
        $code                      = '';
        $hexEscapeSequencePosition = $this->position - 2;

        for ($i = 0; $i < 2; $i++) {
            $code .= $this->parseHexDigit();
        }

        $code = (int) hexdec($code);

        $char = mb_chr($code);
        if ($char === false) {
            $this->invalidHexadecimalEscapeSequence($hexEscapeSequencePosition);
        }

        return $char;
    }

    /**
     * @psalm-suppress InvalidReturnType
     */
    private function parseHexDigit(): string
    {
        if (($this->char >= '0' && $this->char <= '9') || ($this->char >= 'a' && $this->char <= 'f') || ($this->char >= 'A' && $this->char <= 'F')) {
            $digit = $this->char;
            $this->nextChar();
            return $digit;
        }

        $this->unexpectedChar();
    }
}
