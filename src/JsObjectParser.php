<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser;

use Vajexal\JsObjectParser\Exception\ParserException;

class JsObjectParser
{
    private const SPACES = [' ', "\t", "\n", "\r", "\0", "\x0B"];

    private const ID_START_PATTERN    = '/^[\p{L}\p{Nl}]$/u';
    private const ID_CONTINUE_PATTERN = '/^[\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}]$/u';
    private const OTHER_ID_START      = ["\u{2118}", "\u{212E}", "\u{309B}", "\u{309C}"];
    private const OTHER_ID_CONTINUE   = ["\u{1369}", "\u{00B7}", "\u{0387}", "\u{19DA}"];

    private const ZWNJ = "\u{200C}";
    private const ZWJ  = "\u{200D}";

    private const MAX_CODE_POINT_VALUE = 0x10ffff;

    private const SINGLE_ESCAPE_CHARACTERS = [
        '\'' => '\'',
        '"'  => '"',
        '\\' => '\\',
        'b'  => "\u{0008}",
        'f'  => "\u{000C}",
        'n'  => "\u{000A}",
        'r'  => "\u{000D}",
        't'  => "\u{0009}",
        'v'  => "\u{000B}",
    ];

    private const LF   = "\u{000A}";
    private const CR   = "\u{000D}";
    private const LS   = "\u{2028}";
    private const PS   = "\u{2029}";
    private const CRLF = self::CR . self::LF;

    private const LINE_TERMINATORS = [
        self::LF,
        self::CR,
        self::LS,
        self::PS,
    ];

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
            $this->parseError('Could not split string into array of chars');
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

    private function prevChar(): void
    {
        $this->char = $this->position > 0 ? $this->chars[--$this->position] : '';
    }

    private function moveTo(int $position): void
    {
        if ($position >= 0 && $position < \count($this->chars)) {
            $this->position = $position;
        }
    }

    /**
     * @param string[] $strings Accepts only one byte strings
     * @return bool
     */
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

        if ($this->char === '"' || $this->char === "'") {
            return $this->parseString();
        }

        if ($this->char === '[') {
            return $this->parseArray();
        }

        if ($this->char === '{') {
            return $this->parseObject();
        }

        $startPosition = $this->position;
        $identifier    = $this->parseIdentifierName();
        if ($identifier) {
            $this->moveTo($startPosition);
            $this->parseError(sprintf('Unexpected identifier "%s"', $identifier));
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
        $isFloat       = false;
        $startPosition = $this->position;
        $number        = $this->parseDecimalDigits();

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
                $this->moveTo($startPosition);
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
            $startPosition = $this->position - 2;
            $name          .= $unicodeChar = $this->parseUnicodeEscapeSequence();
            if (!$this->checkIdentifierStart($unicodeChar)) {
                $this->invalidUnicodeEscapeSequence($startPosition);
            }
        } elseif ($this->checkIdentifierStart($this->char)) {
            $name .= $this->char;
            $this->nextChar();
        } else {
            return $name;
        }

        while (true) {
            if ($this->consumeString('\\u')) {
                $startPosition = $this->position - 2;
                $name          .= $unicodeChar = $this->parseUnicodeEscapeSequence();
                if (!$this->checkIdentifierPart($unicodeChar)) {
                    $this->invalidUnicodeEscapeSequence($startPosition);
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
        $startPosition = $this->position - 2;
        $code          = $this->char === '{' ? $this->parseCodePoint() : $this->parseHex4DigitsUnicodeEscapeSequence();

        $code = (int) hexdec($code);

        if ($code > self::MAX_CODE_POINT_VALUE) {
            $this->invalidUnicodeEscapeSequence($startPosition);
        }

        $char = mb_chr($code);
        if ($char === false) {
            $this->invalidUnicodeEscapeSequence($startPosition);
        }

        return $char;
    }

    private function parseCodePoint(): string
    {
        $code          = '';
        $startPosition = $this->position - 2;
        $this->nextChar();

        while ($this->char !== '}') {
            if (($this->char >= '0' && $this->char <= '9') || ($this->char >= 'a' && $this->char <= 'f') || ($this->char >= 'A' && $this->char <= 'F')) {
                $code .= $this->char;
                $this->nextChar();
            } else {
                $this->unexpectedChar();
            }
        }

        $this->nextChar();

        if ($code === '') {
            $this->invalidUnicodeEscapeSequence($startPosition);
        }

        return $code;
    }

    private function parseHex4DigitsUnicodeEscapeSequence(): string
    {
        $code          = '';
        $startPosition = $this->position - 2;

        for ($i = 0; $i < 4; $i++) {
            if (($this->char >= '0' && $this->char <= '9') || ($this->char >= 'a' && $this->char <= 'f') || ($this->char >= 'A' && $this->char <= 'F')) {
                $code .= $this->char;
                $this->nextChar();
            } else {
                $this->invalidUnicodeEscapeSequence($startPosition);
            }
        }

        return $code;
    }

    private function parseHexEscapeSequence(): string
    {
        $code          = '';
        $startPosition = $this->position - 2;

        for ($i = 0; $i < 2; $i++) {
            if (($this->char >= '0' && $this->char <= '9') || ($this->char >= 'a' && $this->char <= 'f') || ($this->char >= 'A' && $this->char <= 'F')) {
                $code .= $this->char;
                $this->nextChar();
            } else {
                $this->unexpectedChar();
            }
        }

        $code = (int) hexdec($code);

        $char = mb_chr($code);
        if ($char === false) {
            $this->invalidHexadecimalEscapeSequence($startPosition);
        }

        return $char;
    }

    private function parseArray(): array
    {
        $elements = [];
        $index    = 0;
        $this->nextChar();

        while ($this->char !== ']') {
            $this->skipSpaces();

            if ($this->char === '') {
                $this->unexpectedEndOfInput();
            }

            if ($this->char === ',') {
                $index++;
                $this->nextChar();
                continue;
            }

            if ($this->char === ']') {
                break;
            }

            $elements[$index] = $this->parseExpression();

            $this->skipSpaces();

            if ($this->char === '') {
                $this->unexpectedEndOfInput();
            }

            if ($this->char === ']') {
                break;
            }

            if ($this->char !== ',') {
                $this->unexpectedChar(',');
            }

            $index++;
            $this->nextChar();
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
            }

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
