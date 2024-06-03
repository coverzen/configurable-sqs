<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Job;

final class ListenerEvent
{
    public function __construct(private $class, private readonly string $method)
    {
    }

    public function getClass()
    {
        return $this->class;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
