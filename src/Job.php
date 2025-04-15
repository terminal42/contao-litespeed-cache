<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache;

final class Job
{
    private function __construct(
        private bool $clear,
        private array $tags,
    ) {
    }

    public function serialize(): string
    {
        $data = $this->getTags();

        if ($this->isClear()) {
            $data = ['*'];
        }

        return json_encode($data);
    }

    public static function fromSerialized(string $serialized): self
    {
        $data = json_decode($serialized, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return self::createForClear(); // Clear entire cache on error
        }

        if (['*'] === $data) {
            return self::createForClear();
        }

        return self::createForTags($data);
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function isClear(): bool
    {
        return $this->clear;
    }

    public static function createForTags(array $tags): self
    {
        return new self(false, $tags);
    }

    public static function createForClear(): self
    {
        return new self(true, []);
    }
}
