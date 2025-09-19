<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Job;

final readonly class ListenerEvent
{
    public function __construct(private mixed $class, private string $method)
    {
    }

    public function getClass(): mixed
    {
        return $this->class;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
