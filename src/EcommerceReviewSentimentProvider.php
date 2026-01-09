<?php

declare(strict_types=1);

namespace SharpAPI\EcommerceReviewSentiment;

use Illuminate\Support\ServiceProvider;

/**
 * @api
 */
class EcommerceReviewSentimentProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sharpapi-ecommerce-review-sentiment.php' => config_path('sharpapi-ecommerce-review-sentiment.php'),
            ], 'sharpapi-ecommerce-review-sentiment');
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Merge the package configuration with the app configuration.
        $this->mergeConfigFrom(
            __DIR__.'/../config/sharpapi-ecommerce-review-sentiment.php', 'sharpapi-ecommerce-review-sentiment'
        );
    }
}