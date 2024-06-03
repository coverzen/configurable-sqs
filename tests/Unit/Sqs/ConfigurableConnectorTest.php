<?php declare(strict_types=1);

namespace Coverzen\ConfigurableSqs\Tests\Unit\Sqs;

use Aws\Sqs\SqsClient;
use Coverzen\ConfigurableSqs\Sqs\ConfigurableConnector;
use Coverzen\ConfigurableSqs\Sqs\ConfigurableQueue;
use Coverzen\ConfigurableSqs\Tests\TestCase;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Config;

class ConfigurableConnectorTest extends TestCase
{
    private QueueManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new QueueManager($this->app);

        $this->manager->addConnector('configurable-sqs', fn () => new ConfigurableConnector());
    }

    /**
     * @test
     * @return void
     */
    public function it_can_use_connector(): void
    {
        $this->assertInstanceOf(ConfigurableQueue::class, $this->manager->connection('test'));
    }

    /**
     * @test
     * @return void
     */
    public function it_can_connect_to_sqs(): void
    {
        $this->assertInstanceOf(ConfigurableQueue::class, $this->manager->connection('test'));

        /** @var ConfigurableQueue $connection */
        $connection = $this->manager->connection('test');

        $sqs = $connection->getSqs();

        $this->assertInstanceOf(SqsClient::class, $sqs);

        /** @var Uri $endpoint */
        $endpoint = $sqs->getEndpoint();

        $this->assertSame(Config::get('queue.connections.test.endpoint'), $endpoint->__toString());
    }
}
