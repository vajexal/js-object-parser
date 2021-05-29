<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser\Tests;

use PHPUnit\Framework\TestCase;
use Vajexal\JsObjectParser\Exception\ParserException;
use Vajexal\JsObjectParser\JsObjectParser;

class JsObjectParserTest extends TestCase
{
    private JsObjectParser $jsObjectParser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsObjectParser = new JsObjectParser();
    }

    public function testEmptyLiteral()
    {
        $this->assertSame(null, $this->jsObjectParser->parse(''));
    }

    public function badLiterals(): array
    {
        return [
            [',', "Unexpected char ',' at 0"],
            ['null,', "Unexpected char ',' at 4"],
            ['NULL', 'Unexpected identifier "NULL"'],
            ['FALSE', 'Unexpected identifier "FALSE"'],
            ['TRUE', 'Unexpected identifier "TRUE"'],
            ['foo', 'Unexpected identifier "foo"'],
        ];
    }

    /**
     * @dataProvider badLiterals
     */
    public function testBadLiteral(string $literal, string $message)
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage($message);

        $this->jsObjectParser->parse($literal);
    }

    public function testNullLiteral()
    {
        $this->assertSame(null, $this->jsObjectParser->parse('null'));
        $this->assertSame(null, $this->jsObjectParser->parse('     null     '));
    }

    public function testBooleanLiteral()
    {
        $this->assertSame(true, $this->jsObjectParser->parse('true'));
        $this->assertSame(false, $this->jsObjectParser->parse('false'));
    }

    public function badNumerics(): array
    {
        return [
            ['1a', "Unexpected char 'a' at 1"],
            ['10nn', "Unexpected char 'n' at 3"],
            ['0b', 'No binary digits after "0b"'],
            ['0b102', "Unexpected char '2' at 4"],
            ['0b1__0', "Unexpected char '_' at 3"],
            ['0b_1', "Unexpected char '_' at 2"],
            ['0o', 'No octal digits after "0o"'],
            ['0o18', "Unexpected char '8' at 3"],
            ['0o1__7', "Unexpected char '_' at 3"],
            ['0x', 'No hexadecimal digits after "0x"'],
            ['0x1__f', "Unexpected char '_' at 3"],
            ['0x1g', "Unexpected char 'g' at 3"],
            ['_1', 'Unexpected identifier "_1"'],
            ['1_', "Unexpected char '_' at 1"],
            ['..2', "Unexpected char '.' at 1"],
            ['.e', "Unexpected char '.' at 0"],
            ['.e2', "Unexpected char '.' at 0"],
            ['1e', "Invalid decimal"],
            ['.2n', "Unexpected char 'n' at 2"],
        ];
    }

    /**
     * @dataProvider badNumerics
     */
    public function testBadNumeric(string $numeric, string $message)
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage($message);

        $this->jsObjectParser->parse($numeric);
    }

    public function testNumericLiteral()
    {
        $this->assertSame(0, $this->jsObjectParser->parse('0'));
        $this->assertSame(123, $this->jsObjectParser->parse('123'));
        $this->assertSame(123, $this->jsObjectParser->parse('1_2_3'));
        $this->assertSame(0, $this->jsObjectParser->parse('0n'));
        $this->assertSame(10, $this->jsObjectParser->parse('10n'));
        $this->assertSame(123, $this->jsObjectParser->parse('1_2_3n'));
        $this->assertSame(.1, $this->jsObjectParser->parse('.1'));
        $this->assertSame(1.2e3, $this->jsObjectParser->parse('1.2e3'));
        $this->assertSame(.2e3, $this->jsObjectParser->parse('.2E+3'));
        $this->assertSame(12.34e-5, $this->jsObjectParser->parse('1_2.3_4E-5'));
        $this->assertSame(123, $this->jsObjectParser->parse('123n'));
        $this->assertSame(0b101, $this->jsObjectParser->parse('0b101'));
        $this->assertSame(0b101, $this->jsObjectParser->parse('0B1_0_1'));
        $this->assertSame(0b101, $this->jsObjectParser->parse('0b101n'));
        $this->assertSame(0123, $this->jsObjectParser->parse('0o123'));
        $this->assertSame(0123, $this->jsObjectParser->parse('0O1_2_3'));
        $this->assertSame(0123, $this->jsObjectParser->parse('0o123n'));
        $this->assertSame(0x1a, $this->jsObjectParser->parse('0x1a'));
        $this->assertSame(0x1ab, $this->jsObjectParser->parse('0X1_a_B'));
        $this->assertSame(0x1a, $this->jsObjectParser->parse('0x1an'));
    }

    public function badStrings(): array
    {
        return [
            ['"foo', 'Unexpected end of input'],
            ["'foo", 'Unexpected end of input'],
            ['foo"', 'Unexpected identifier "foo"'],
            ['"f"oo"', "Unexpected char 'o' at 3"],
            ["'f'oo'", "Unexpected char 'o' at 3"],
            ["'\n'", "Unexpected line terminator at 1"],
            ["'\r\n'", "Unexpected line terminator at 1"],
            ["'\\u{ffffff}'", "Invalid Unicode escape sequence at 1"],
            ["'\\x1g'", "Unexpected char 'g' at 4"],
        ];
    }

    /**
     * @dataProvider badStrings
     */
    public function testBadString(string $str, string $message)
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage($message);

        $this->jsObjectParser->parse($str);
    }

    public function testStringLiteral()
    {
        $this->assertSame('', $this->jsObjectParser->parse("''"));
        $this->assertSame('', $this->jsObjectParser->parse('""'));
        $this->assertSame('foo', $this->jsObjectParser->parse('"foo"'));
        $this->assertSame("f'oo", $this->jsObjectParser->parse('"f\'oo"'));
        $this->assertSame('f"oo', $this->jsObjectParser->parse("'f\"oo'"));
        $this->assertSame('f"oo', $this->jsObjectParser->parse('"f\\"oo"'));
        $this->assertSame("f'oo", $this->jsObjectParser->parse("'f\\'oo'"));
        $this->assertSame("\0", $this->jsObjectParser->parse("'\\0'"));
        $this->assertSame("\0" . '3', $this->jsObjectParser->parse("'\\03'"));
        $this->assertSame('a_b', $this->jsObjectParser->parse("'\\x61\\u005F\\u{62}'"));
        $this->assertSame("'", $this->jsObjectParser->parse("'\\''"));
        $this->assertSame("\n", $this->jsObjectParser->parse("'\\n'"));
        $this->assertSame("\n", $this->jsObjectParser->parse("'\\\n'"));
        $this->assertSame("\r\n", $this->jsObjectParser->parse("'\\\r\n'"));
    }

    public function badArrays(): array
    {
        return [
            ['[', 'Unexpected end of input'],
            [']', "Unexpected char ']' at 0"],
            ['[1, []', 'Unexpected end of input'],
            ['[1, ]]', "Unexpected char ']' at 5"],
            ['["foo" => "bar"]', "Unexpected char '=' at 7, expected ','"],
            ['["foo": "bar"]', "Unexpected char ':' at 6, expected ','"],
            ['[1 2]', "Unexpected char '2' at 3, expected ','"],
        ];
    }

    /**
     * @dataProvider badArrays
     */
    public function testBadArray(string $arr, string $message)
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage($message);

        $this->jsObjectParser->parse($arr);
    }

    public function testArrayLiteral()
    {
        $this->assertSame([], $this->jsObjectParser->parse('[]'));
        $this->assertSame([123, 'foo'], $this->jsObjectParser->parse('[123, "foo"]'));
        $this->assertSame([[1]], $this->jsObjectParser->parse('[[1]]'));
        $this->assertSame([2 => 2, 4 => 4], $this->jsObjectParser->parse('[,,2,,4]'));
        $this->assertSame(['Ó', true], $this->jsObjectParser->parse('["Ó", true]'));
    }

    public function badObjects(): array
    {
        return [
            ['{', 'Unexpected end of input'],
            ['}', "Unexpected char '}' at 0"],
            ['{1: 2', 'Unexpected end of input'],
            ['{"foo": "bar", {}', "Unexpected char '{' at 15"],
            ['{"foo": "bar", }}', "Unexpected char '}' at 16"],
            ['{"foo" => "bar"}', "Unexpected char '=' at 7, expected ':'"],
            ['{1: 2 "foo": "bar"}', "Unexpected char '\"' at 6, expected ','"],
            ['{1a: 1}', "Unexpected char 'a' at 2"],
            ['{: 1}', "Unexpected char ':' at 1"],
            ['{\u{}: 1}', "Invalid Unicode escape sequence at 1"],
            ['{\u{00_5F}: 1}', "Unexpected char '_' at 6"],
            ['{\u{g}: 1}', "Unexpected char 'g' at 4"],
            ['{\u5F: 1}', "Invalid Unicode escape sequence at 1"],
            ['{\uffff: 1}', "Invalid Unicode escape sequence at 1"],
            ['{\u{5f}\u0020: 1}', "Invalid Unicode escape sequence at 7"],
            ['{\u{31}\u005f: 1}', "Invalid Unicode escape sequence at 1"],
        ];
    }

    /**
     * @dataProvider badObjects
     */
    public function testBadObject(string $obj, string $message)
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage($message);

        $this->jsObjectParser->parse($obj);
    }

    public function testObjectLiteral()
    {
        $this->assertSame([], $this->jsObjectParser->parse('{}'));
        $this->assertSame(['foo' => 'bar'], $this->jsObjectParser->parse('{foo: "bar"}'));
        $this->assertSame(['foo' => 'baz'], $this->jsObjectParser->parse('{foo: "bar", foo: "baz"}'));
        $this->assertSame(['' => 1], $this->jsObjectParser->parse('{"": 1}'));
        $this->assertSame(['Ó' => 1], $this->jsObjectParser->parse('{Ó: 1}'));
        $this->assertSame(['a1' => 1, '_b' => 2, "\$\u{200C}" => 3], $this->jsObjectParser->parse('{\u0061\u{31}: 1, \u{005f}\u{000062}: 2, \u{024}\u200C: 3}'));
        $this->assertSame([1 => 2, 'foo' => 'bar', 'baz' => 'quux'], $this->jsObjectParser->parse(<<<'JSON'
{
    1: 2,
    foo: 'bar',
    'baz' : "quux",
}
JSON
        ));
    }

    public function testComplex()
    {
        $this->assertSame(
            [
                'name'    => 'Jim',
                'age'     => 21,
                'friends' => [
                    [
                        'name'    => 'Sara',
                        'age'     => 23,
                        'friends' => [
                            [
                                'name'    => 'Bob',
                                'age'     => 45,
                                'friends' => [],
                            ],
                        ],
                    ],
                    [
                        'name'    => 'George',
                        'age'     => 22,
                        'friends' => [],
                    ],
                ],
            ],
            $this->jsObjectParser->parse(<<<'JSON'
{
    name: 'Jim',
    age: 21,
    friends: [
        {
            name: 'Sara',
            age: 23,
            friends: [
                {
                    name: "Bob",
                    age: 45,
                    friends: [],
                }
            ]
        },
        {
            name: 'George',
            age: 22,
            friends: [
            ]
        }
    ]
}
JSON
            )
        );
    }
}
