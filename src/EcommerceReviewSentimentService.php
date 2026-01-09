<?php

declare(strict_types=1);

namespace SharpAPI\EcommerceReviewSentiment;

use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use SharpAPI\Core\Client\SharpApiClient;

/**
 * @api
 */
class EcommerceReviewSentimentService extends SharpApiClient
{
    /**
     * Initializes a new instance of the class.
     *
     * @throws InvalidArgumentException if the API key is empty.
     */
    public function __construct()
    {
        parent::__construct(config('sharpapi-ecommerce-review-sentiment.api_key'));
        $this->setApiBaseUrl(
            config(
                'sharpapi-ecommerce-review-sentiment.base_url',
                'https://sharpapi.com/api/v1'
            )
        );
        $this->setApiJobStatusPollingInterval(
            (int) config(
                'sharpapi-ecommerce-review-sentiment.api_job_status_polling_interval',
                5)
        );
        $this->setApiJobStatusPollingWait(
            (int) config(
                'sharpapi-ecommerce-review-sentiment.api_job_status_polling_wait',
                180)
        );
        $this->setUserAgent('SharpAPILaravelEcommerceReviewSentiment/1.0.0');
    }

    /**
     * Parses the customer's product review and provides its sentiment (POSITIVE/NEGATIVE/NEUTRAL)
     * with a score between 0-100%. Great for sentiment report processing for any online store.
     *
     * @throws GuzzleException
     *
     * @api
     */
    public function productReviewSentiment(string $review): string
    {
        $response = $this->makeRequest(
            'POST',
            '/ecommerce/review_sentiment',
            ['content' => $review]
        );

        return $this->parseStatusUrl($response);
    }
}