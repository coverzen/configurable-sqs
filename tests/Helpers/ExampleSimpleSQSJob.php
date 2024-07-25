<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers;

use Coverzen\ConfigurableSqs\Job\SimpleSQSJob;

class ExampleSimpleSQSJob extends SimpleSQSJob
{
    protected string $event = 'example';

    public function __construct($data)
    {
        parent::__construct($data);

        // your implementation here
    }
}
