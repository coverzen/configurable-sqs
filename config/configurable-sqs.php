<?php

declare(strict_types=1);

use Coverzen\ConfigurableSqs\Job\ConfigurableJob;
use Coverzen\ConfigurableSqs\Job\LogMessageListener;

return [
    'test' => [
        [
            'type' => ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD,
            'search' => [
                'key' => 'message',
                'value' => 'test',
            ],
            'listener' => LogMessageListener::class,
        ],
        [
            'type' => ConfigurableJob::TYPE_SQS_REGEX_PAYLOAD,
            'search' => [
                'key' => 'message',
                'value' => '/^test$/i',
            ],
            'listener' => LogMessageListener::class,
        ],
        [
            'type' => ConfigurableJob::TYPE_SNS_FROM,
            'arn' => 'arn:aws:sns:eu-south-1:0000000000:HelloWorld',
            'listener' => LogMessageListener::class,
        ],
    ],
];
