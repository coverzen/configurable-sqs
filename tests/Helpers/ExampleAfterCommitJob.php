<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class ExampleAfterCommitJob implements ShouldQueueAfterCommit
{
}
