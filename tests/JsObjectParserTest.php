<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser\Tests;

use PHPUnit\Framework\TestCase;
use Vajexal\JsObjectParser\Exception\ParserException;
use Vajexal\JsObjectParser\JsObjectParser;

class JsObjectParserTest extends TestCase
{
    public function testEmptyLiteral()
    {
        $this->assertSame(null, JsObjectParser::parse(''));
    }

    /**
     * @dataProvider badLiterals
     */
    public function testBadLiteral(string $literal)
    {
        $this->expectException(ParserException::class);

        JsObjectParser::parse($literal);
    }

    public function badLiterals(): array
    {
        return [
            [','],
            ['null,'],
            ['NULL'],
            ['FALSE'],
            ['TRUE'],
            ['foo'],
        ];
    }

    public function testNullLiteral()
    {
        $this->assertSame(null, JsObjectParser::parse('null'));
        $this->assertSame(null, JsObjectParser::parse('     null     '));
    }

    public function testBooleanLiteral()
    {
        $this->assertSame(true, JsObjectParser::parse('true'));
        $this->assertSame(false, JsObjectParser::parse('false'));
    }

    public function badNumerics(): array
    {
        return [
            ['1a'],
            ['10nn'],
            ['0b'],
            ['0b102'],
            ['0b1__0'],
            ['0b_1'],
            ['0o'],
            ['0o18'],
            ['0o1__7'],
            ['0x'],
            ['0x1__f'],
            ['0x1g'],
            ['_1'],
            ['1_'],
            ['..2'],
            ['.e'],
            ['.e2'],
            ['.e2'],
            ['1e'],
            ['.2n'],
        ];
    }

    /**
     * @dataProvider badNumerics
     */
    public function testBadNumeric(string $numeric)
    {
        $this->expectException(ParserException::class);

        JsObjectParser::parse($numeric);
    }

    public function testNumericLiteral()
    {
        $this->assertSame(0, JsObjectParser::parse('0'));
        $this->assertSame(123, JsObjectParser::parse('123'));
        $this->assertSame(123, JsObjectParser::parse('1_2_3'));
        $this->assertSame(0, JsObjectParser::parse('0n'));
        $this->assertSame(10, JsObjectParser::parse('10n'));
        $this->assertSame(123, JsObjectParser::parse('1_2_3n'));
        $this->assertSame(.1, JsObjectParser::parse('.1'));
        $this->assertSame(1.2e3, JsObjectParser::parse('1.2e3'));
        $this->assertSame(.2e3, JsObjectParser::parse('.2E+3'));
        $this->assertSame(12.34e-5, JsObjectParser::parse('1_2.3_4E-5'));
        $this->assertSame(123, JsObjectParser::parse('123n'));
        $this->assertSame(0b101, JsObjectParser::parse('0b101'));
        $this->assertSame(0b101, JsObjectParser::parse('0B1_0_1'));
        $this->assertSame(0b101, JsObjectParser::parse('0b101n'));
        $this->assertSame(0123, JsObjectParser::parse('0o123'));
        $this->assertSame(0123, JsObjectParser::parse('0O1_2_3'));
        $this->assertSame(0123, JsObjectParser::parse('0o123n'));
        $this->assertSame(0x1a, JsObjectParser::parse('0x1a'));
        $this->assertSame(0x1ab, JsObjectParser::parse('0X1_a_B'));
        $this->assertSame(0x1a, JsObjectParser::parse('0x1an'));
    }

    public function badStrings(): array
    {
        return [
            ['"foo'],
            ["'foo"],
            ['foo"'],
            ['"f"oo"'],
            ["'f'oo'"],
        ];
    }

    /**
     * @dataProvider badStrings
     */
    public function testBadString(string $s)
    {
        $this->expectException(ParserException::class);

        JsObjectParser::parse($s);
    }

    public function testStringLiteral()
    {
        $this->assertSame('', JsObjectParser::parse("''"));
        $this->assertSame('', JsObjectParser::parse('""'));
        $this->assertSame('foo', JsObjectParser::parse('"foo"'));
        $this->assertSame("f'oo", JsObjectParser::parse('"f\'oo"'));
        $this->assertSame('f"oo', JsObjectParser::parse("'f\"oo'"));
        $this->assertSame('f"oo', JsObjectParser::parse('"f\\"oo"'));
        $this->assertSame("f'oo", JsObjectParser::parse("'f\\'oo'"));
    }

    public function badArrays(): array
    {
        return [
            ['['],
            [']'],
            ['[1, []'],
            ['[1, ]]'],
            ['["foo" => "bar"]'],
            ['["foo": "bar"]'],
        ];
    }

    /**
     * @dataProvider badArrays
     */
    public function testBadArray(string $arr)
    {
        $this->expectException(ParserException::class);

        JsObjectParser::parse($arr);
    }

    public function testArrayLiteral()
    {
        $this->assertSame([], JsObjectParser::parse('[]'));
        $this->assertSame([123, 'foo'], JsObjectParser::parse('[123, "foo"]'));
        $this->assertSame([[1]], JsObjectParser::parse('[[1]]'));
    }

    public function badObjects(): array
    {
        return [
            ['{'],
            ['}'],
            ['{"foo": "bar", {}'],
            ['{"foo": "bar", }}'],
            ['{"foo" => "bar"}'],
            ['{1: 2 "foo": "bar"}'],
            ['{"": false}'],
        ];
    }

    /**
     * @dataProvider badObjects
     */
    public function testBadObject(string $obj)
    {
        $this->expectException(ParserException::class);

        JsObjectParser::parse($obj);
    }

    public function testObjectLiteral()
    {
        $this->assertSame([], JsObjectParser::parse('{}'));
        $this->assertSame(['foo' => 'bar'], JsObjectParser::parse('{foo: "bar"}'));
        $this->assertSame([1 => 2, 'foo' => 'bar', 'baz' => 'quux'], JsObjectParser::parse(<<<'JSON'
{
    1: 2,
    foo: 'bar',
    'baz' : "quux",
}
JSON
        ));
    }

    // todo maybe some complex test
}
