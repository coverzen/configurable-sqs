<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SimpleSQSJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var mixed
     */
    protected mixed $data = [];

    /**
     * @var string
     */
    protected string $event;

    /**
     * DispachableJob constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Fake execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // This method is empty because it is not necessary to implement it.
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->data;
    }

    /**
     * @return string|null
     */
    public function getEvent(): ?string
    {
        return $this->event;
    }
}
