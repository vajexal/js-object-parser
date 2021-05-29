<?php

declare(strict_types=1);

namespace Vajexal\JsObjectParser;

use Vajexal\JsObjectParser\Exception\ParserException;

trait ParseInput
{
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
            throw new ParserException('Could not split string into array of chars', $this->str, $this->position);
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
            $this->char     = $this->chars[$this->position];
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

    private function skipSpaces(): void
    {
        while (\in_array($this->char, self::SPACES, true)) {
            $this->nextChar();
        }
    }
}
