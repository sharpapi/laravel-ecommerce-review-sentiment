<?php

declare(strict_types=1);

namespace SharpAPI\EcommerceReviewSentiment\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SharpAPI\EcommerceReviewSentiment\EcommerceReviewSentimentProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [EcommerceReviewSentimentProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sharpapi-ecommerce-review-sentiment.api_key', 'test-key');
    }
}
