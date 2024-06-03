# Configurable sqs subscriber for laravel

![Build Status](https://github.com/coverzen/configurable-sqs/actions/workflows/ci.yml/badge.svg)
![Latest Stable Version](https://img.shields.io/packagist/v/coverzen/configurable-sqs)
![License](https://img.shields.io/packagist/l/coverzen/configurable-sqs)

This package provides a simple way to subscribe to an AWS SQS queue in Laravel.
The package is designed to be as simple as possible to use, with a simple configuration for redirect any message to a specific listener.


## Installation
```bash
composer require coverzen/configurable-sqs
```

## Configuration
First, you need to add the following environment variables to your `.env` file:

```dotenv
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=your-region
AWS_ACCOUNT_ID=your-account-id
```

Then, you need to add the following configuration to your `config/queue.php` file:

```php
'connections' => [

    ...
    
    'configurable-sqs' => [
        'driver' => 'configurable-sqs',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'prefix' => 'https://sqs.'.env('AWS_DEFAULT_REGION').'.amazonaws.com/'.env('AWS_ACCOUNT_ID').'/',
        'queue' => 'your-queue-name',
        'suffix' => '',
        'region' => env('AWS_DEFAULT_REGION'),
    ],
]
```

## Usage
To subscribe custom listener to a queue, you need to create a new listener class and configure it in the `configurable-sqs.php` file.

```php
    'your-queue-name' => [
        [
            'type' => ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD,
            'search' => [
                'key' => 'message',
                'value' => 'test',
            ],
            'listener' => YourListener::class,
        ],
        [
            'type' => ConfigurableJob::TYPE_SQS_REGEX_PAYLOAD,
            'search' => [
                'key' => 'message',
                'value' => '/^test$/i',
            ],
            'listener' => YourListener::class,
        ],
        [
            'type' => ConfigurableJob::TYPE_SNS_FROM,
            'arn' => 'arn:aws:sns:eu-south-1:0000000000:HelloWorld',
            'listener' => YourListener::class,
        ],
    ],
```    

The `type` key can be one of the following values:
- `ConfigurableJob::TYPE_SQS_SIMPLE_PAYLOAD`: This type will search for a specific key and value in the SQS message.
- `ConfigurableJob::TYPE_SQS_REGEX_PAYLOAD`: This type will search for a specific key and value in the SQS message using a regex pattern.
- `ConfigurableJob::TYPE_SNS_FROM`: This type will search for a specific SNS ARN in the SQS message.

The listener class can implement the `Coverzen\ConfigurableSqs\Interfaces\ListenerInterface` interface.

```php

namespace App\Listeners;

YourListener implements ListenerInterface
{
    public function handle(array $message): void
    {
        // Your logic here
    }
}
```

## License
The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits
- [Coverzen](https://www.coverzen.it)