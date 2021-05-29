<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser;

trait ExpressionParser
{
    use ParseInput,
        ParserError,
        NumericParser,
        StringParser,
        ArrayParser,
        ObjectParser;

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

        $identifierPosition = $this->position;
        $identifier         = $this->parseIdentifierName();
        if ($identifier) {
            $this->moveTo($identifierPosition);
            $this->parseError(sprintf('Unexpected identifier "%s"', $identifier));
        }

        $this->unexpectedChar();
    }
}
