<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser;

use Vajexal\JsObjectParser\Exception\ParserException;

class JsObjectParser
{
    use ParseInput,
        ParserError,
        NumericParser,
        StringParser,
        ArrayParser,
        ObjectParser,
        ExpressionParser;

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
}
