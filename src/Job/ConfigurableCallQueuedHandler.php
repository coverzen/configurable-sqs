<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Job;

use __PHP_Incomplete_Class;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Queue\CallQueuedHandler;

final class ConfigurableCallQueuedHandler extends CallQueuedHandler
{
    /**
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     * @param ConfigurableJob $job
     * @param array $data
     *
     * @throws Exception
     * @return void|null
     */
    public function call($job, array $data)
    {
        try {
            $command = $this->setJobInstanceIfNecessary(
                $job,
                $this->getCurrentCommand($job)
            );
        } catch (BindingResolutionException $e) {
            $this->handleModelNotFound($job, $e);

            return;
        }

        if ($command instanceof ShouldBeUniqueUntilProcessing) {
            $this->ensureUniqueJobLockIsReleased($command);
        }

        $this->dispatchThroughMiddleware($job, $command);

        if (!$job->isReleased() && !$command instanceof ShouldBeUniqueUntilProcessing) {
            $this->ensureUniqueJobLockIsReleased($command);
        }

        if (!$job->hasFailed() && !$job->isReleased()) {
            $this->ensureNextJobInChainIsDispatched($command);
            $this->ensureSuccessfulBatchJobIsRecorded($command);
        }

        if (!$job->isDeletedOrReleased()) {
            $job->delete();
        }
    }

    /**
     * @param ConfigurableJob $job
     *
     * @throws BindingResolutionException
     * @return mixed
     */
    protected function getCurrentCommand(ConfigurableJob $job): mixed
    {
        return $this->container->make($job->getCommand());
    }

    /**
     * Dispatch the given job / command through its specified middleware.
     *
     * @param ConfigurableJob $job
     * @param mixed $command
     *
     * @throws Exception
     * @return mixed
     */
    protected function dispatchThroughMiddleware(Job $job, $command): mixed
    {
        if ($command instanceof __PHP_Incomplete_Class) {
            throw new Exception('Job is incomplete class: ' . json_encode($command));
        }

        return (new Pipeline($this->container))->send($command)
                                               ->through(array_merge(method_exists($command, 'middleware') ? $command->middleware() : [], $command->middleware ?? []))
                                               ->then(function ($command) use ($job) {
                                                   if ($job->getJobType() === ConfigurableJob::TYPE_SQS_UNMATCHED_PAYLOAD) {
                                                       $logger = $job->getUnmatchedListener();
                                                       if ($logger && $job->payload()) {
                                                           return $command->handle($job, $logger);
                                                       }

                                                       return true;
                                                   }

                                                   return $command->handle($job->getHandlerPayload());
                                               });
    }
}
