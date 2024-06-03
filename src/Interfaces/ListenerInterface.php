<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Interfaces;

/**
 * Interface ListenerInterface.
 */
interface ListenerInterface
{
    /**
     * Handle the payload.
     *
     * @param array $payload the payload of sqs message
     *
     * @return void
     */
    public function handle(array $payload): void;
}
