<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser;

use Vajexal\JsObjectParser\Exception\ParserException;

class JsObjectParser
{
    private const SPACES = [' ', "\t", "\n", "\r", "\0", "\x0B"];

    private string $str;
    /** @var string[] */
    private array  $chars;
    private int    $position = 0;
    private string $char;

    private function __construct(string $str)
    {
        $this->str = $str;

        $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            $this->parseError('could not split string into array of chars');
        }
        $this->chars = $chars;

        $this->char = $this->chars ? $this->chars[0] : '';
    }

    private function behind(): string
    {
        return $this->position > 0 ? $this->chars[$this->position - 1] : '';
    }

    private function ahead(): string
    {
        return $this->position < (\count($this->chars) - 1) ? $this->chars[$this->position + 1] : '';
    }

    private function nextChar(): void
    {
        $this->char = $this->position + 1 < \count($this->chars) ? $this->chars[++$this->position] : '';
    }

    private function consumeString(string ...$strings): bool
    {
        foreach ($strings as $str) {
            if ($this->position + \strlen($str) > \count($this->chars)) {
                continue;
            }

            for ($i = 0; $i < \strlen($str); $i++) {
                if ($this->chars[$this->position + $i] !== $str[$i]) {
                    continue 2;
                }
            }

            $this->position += \strlen($str);
            $this->char     = $this->position < \count($this->chars) ? $this->chars[$this->position] : '';

            return true;
        }

        return false;
    }

    /**
     * Parse js primary expression
     *
     * @param string $str
     * @return mixed
     * @throws ParserException
     */
    public static function parse(string $str): mixed
    {
        $str = trim($str);

        if ($str === '') {
            return null;
        }

        $parser = new self($str);

        $expression = $parser->parseExpression();

        if ($parser->char !== '') {
            $parser->unexpectedChar();
        }

        return $expression;
    }

    /**
     * @psalm-suppress InvalidReturnType
     */
    private function parseExpression(): mixed
    {
        if ($this->consumeString('null')) {
            return null;
        }

        if ($this->consumeString('true')) {
            return true;
        }

        if ($this->consumeString('false')) {
            return false;
        }

        if ($this->char >= '0' && $this->char <= '9' || $this->char === '.') {
            return $this->parseNumeric();
        }

        // todo "`"
        if ($this->char === '"' || $this->char === "'") {
            return $this->parseString();
        }

        if ($this->char === '[') {
            return $this->parseArray();
        }

        if ($this->char === '{') {
            return $this->parseObject();
        }

        $this->unexpectedChar();
    }

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
        $isFloat = false;
        $number  = $this->parseDecimalDigits();

        if ($this->char === '.') {
            $number  .= $this->char;
            $isFloat = true;
            $this->nextChar();
        }

        $number .= $this->parseDecimalDigits();

        if ($this->char === 'e' || $this->char === 'E') {
            if ($number === '.') {
                $this->parseError('empty number before exponent');
            }

            $number .= 'e';
            $this->nextChar();

            if ($this->char === '+' || $this->char === '-') {
                $number .= $this->char;
                $this->nextChar();
            }

            $number .= $exponentPart = $this->parseDecimalDigits();

            if ($exponentPart === '') {
                $this->parseError('empty exponent part');
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

    /**
     * @psalm-suppress InvalidReturnType
     */
    private function parseString(): string
    {
        $str        = '';
        $terminator = $this->char;

        do {
            $this->nextChar();

            if ($this->char === $terminator && $this->behind() !== '\\') {
                $this->nextChar();
                return $str;
            }

            // todo all escaped chars
            if ($this->char === '\\') {
                $this->nextChar();
            }

            $str .= $this->char;
        } while ($this->char !== '');

        $this->parseError('Unexpected EOF');
    }

    private function parseIdentifierName(): string
    {
        $name = '';

        // todo unicode
        if ($this->char === '$' || $this->char === '_' || ($this->char >= 'a' && $this->char <= 'z') || ($this->char >= 'A' && $this->char <= 'Z')) {
            $name .= $this->char;
            $this->nextChar();
        } else {
            $this->unexpectedChar();
        }

        while (
            $this->char === '_' ||
            ($this->char >= 'a' && $this->char <= 'z') || ($this->char >= 'A' && $this->char <= 'Z') ||
            ($this->char >= '0' && $this->char <= '9')
        ) {
            $name .= $this->char;
            $this->nextChar();
        }

        return $name;
    }

    private function parseArray(): array
    {
        $elements = [];
        $this->nextChar();

        while ($this->char !== ']') {
            // todo element index
            if ($this->char === ',' || \in_array($this->char, self::SPACES, true)) {
                $this->nextChar();
                continue;
            }

            $elements[] = $this->parseExpression();
        }

        $this->nextChar();

        return $elements;
    }

    private function parseObject(): array
    {
        $object = [];
        $this->nextChar();

        while ($this->char !== '}') {
            $this->skipSpaces();

            if ($this->char >= '0' && $this->char <= '9' || $this->char === '.') {
                $key = (int) $this->parseDecimalDigits();
            } elseif ($this->char === '"' || $this->char === "'") { // todo "`"
                $key = $this->parseString();
            } else {
                $key = $this->parseIdentifierName();
            }

            if (\is_string($key) && $key === '') {
                $this->parseError('empty key');
            }

            $this->skipSpaces();

            if ($this->char !== ':') {
                $this->unexpectedChar(':');
            }

            $this->nextChar();
            $this->skipSpaces();

            $object[$key] = $this->parseExpression();

            $this->skipSpaces();

            if ($this->char === '}') {
                break;
            }

            if ($this->char === ',') {
                $this->nextChar();
                $this->skipSpaces();
            } else {
                $this->unexpectedChar(',');
            }
        }

        $this->nextChar();

        return $object;
    }

    private function skipSpaces(): void
    {
        while (\in_array($this->char, self::SPACES, true)) {
            $this->nextChar();
        }
    }

    private function parseError(string $message): void
    {
        throw new ParserException($message, $this->str, $this->position);
    }

    private function unexpectedChar(string $expected = ''): void
    {
        $message = "unexpected char '{$this->char}' at {$this->position}";

        if ($expected !== '') {
            $message .= ", expected '{$expected}'";
        }

        $this->parseError($message);
    }
}
