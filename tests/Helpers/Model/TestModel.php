<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers\Model;

class TestModel
{
    public function __construct(private string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }
}
