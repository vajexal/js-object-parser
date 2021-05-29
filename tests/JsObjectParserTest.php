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

    public function numerics(): array
    {
        return [
            [0, '0'],
            [123, '123'],
            [123, '1_2_3'],
            [0, '0n'],
            [10, '10n'],
            [123, '1_2_3n'],
            [.1, '.1'],
            [1.2e3, '1.2e3'],
            [.2e3, '.2E+3'],
            [12.34e-5, '1_2.3_4E-5'],
            [123, '123n'],
            [0b101, '0b101'],
            [0b101, '0B1_0_1'],
            [0b101, '0b101n'],
            [0123, '0o123'],
            [0123, '0O1_2_3'],
            [0123, '0o123n'],
            [0x1a, '0x1a'],
            [0x1ab, '0X1_a_B'],
            [0x1a, '0x1an'],
        ];
    }

    /**
     * @dataProvider numerics
     */
    public function testNumericLiteral(float|int $expected, string $numeric)
    {
        $this->assertSame($expected, $this->jsObjectParser->parse($numeric));
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

    public function strings(): array
    {
        return [
            ['', "''"],
            ['', '""'],
            ['foo', '"foo"'],
            ["f'oo", '"f\'oo"'],
            ['f"oo', "'f\"oo'"],
            ['f"oo', '"f\\"oo"'],
            ["f'oo", "'f\\'oo'"],
            ["\0", "'\\0'"],
            ["\0" . '3', "'\\03'"],
            ['a_b', "'\\x61\\u005F\\u{62}'"],
            ["'", "'\\''"],
            ["\n", "'\\n'"],
            ["\n", "'\\\n'"],
            ["\r\n", "'\\\r\n'"],
        ];
    }

    /**
     * @dataProvider strings
     */
    public function testStringLiteral(string $expected, string $str)
    {
        $this->assertSame($expected, $this->jsObjectParser->parse($str));
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

    public function arrays(): array
    {
        return [
            [[], '[]'],
            [[123, 'foo'], '[123, "foo"]'],
            [[[1]], '[[1]]'],
            [[2 => 2, 4 => 4], '[,,2,,4]'],
            [['Ó', true], '["Ó", true]'],
        ];
    }

    /**
     * @dataProvider arrays
     */
    public function testArrayLiteral(array $expected, string $arr)
    {
        $this->assertSame($expected, $this->jsObjectParser->parse($arr));
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

    public function objects(): array
    {
        return [
            [[], '{}'],
            [['foo' => 'bar'], '{foo: "bar"}'],
            [['foo' => 'baz'], '{foo: "bar", foo: "baz"}'],
            [['' => 1], '{"": 1}'],
            [['Ó' => 1], '{Ó: 1}'],
            [['a1' => 1, '_b' => 2, "\$\u{200C}" => 3], '{\u0061\u{31}: 1, \u{005f}\u{000062}: 2, \u{024}\u200C: 3}'],
            [[1 => 2, 'foo' => 'bar', 'baz' => 'quux'], <<<'JSON'
{
    1: 2,
    foo: 'bar',
    'baz' : "quux",
}
JSON],
        ];
    }

    /**
     * @dataProvider objects
     */
    public function testObjectLiteral(array $expected, string $obj)
    {
        $this->assertSame($expected, $this->jsObjectParser->parse($obj));
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
