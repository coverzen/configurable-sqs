<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Helpers;

abstract class AbstractCallQueuedHandlerTestJobWithMiddleware
{
    public static mixed $middlewareCommand;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new class() {
                public function handle(mixed $command, callable $next): mixed
                {
                    AbstractCallQueuedHandlerTestJobWithMiddleware::$middlewareCommand = $command;

                    return $next($command);
                }
            },
        ];
    }
}
