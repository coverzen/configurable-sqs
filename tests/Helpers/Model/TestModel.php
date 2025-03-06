<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers\Model;

class TestModel
{
    public function __construct(private string $name, private ?bool $toQueue = true)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toQueue(): bool
    {
        return $this->toQueue;
    }
}
