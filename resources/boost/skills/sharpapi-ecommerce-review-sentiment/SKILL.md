---
name: sharpapi-ecommerce-review-sentiment
description: Analyse the sentiment of a product review (POSITIVE/NEGATIVE/NEUTRAL plus score) via SharpAPI (sharpapi/laravel-ecommerce-review-sentiment). Use when scoring customer or product reviews with AI, or when code touches EcommerceReviewSentimentService, productReviewSentiment(), fetchResults() or config/sharpapi-ecommerce-review-sentiment.php.
---

# SharpAPI E-commerce Review Sentiment (`sharpapi/laravel-ecommerce-review-sentiment`)

Classifies a product review as POSITIVE, NEGATIVE or NEUTRAL with a 0-100 score. The package is a thin Laravel wrapper around `sharpapi/php-core`: one service class (`SharpAPI\EcommerceReviewSentiment\EcommerceReviewSentimentService`), one config file, no facade and no container binding. Requires `sharpapi/php-core` ≥ 1.4.1 (pulled in by v1.1.0 of this package).

## When to use this skill

- Scoring or classifying the sentiment of product or customer reviews with AI.
- Writing or reviewing code that calls `EcommerceReviewSentimentService::productReviewSentiment()` or `fetchResults()`, or reads `config('sharpapi-ecommerce-review-sentiment.*')`.
- Moving a SharpAPI call out of a web request into a queued job, or writing tests around it.

## Install / wiring checklist

1. `composer require sharpapi/laravel-ecommerce-review-sentiment`. `SharpAPI\EcommerceReviewSentiment\EcommerceReviewSentimentProvider` is auto-discovered; it only merges and publishes config.
2. Set `SHARP_API_KEY` in `.env`. The service constructor throws `InvalidArgumentException` when the key is empty, so resolving the service without a key fails at once (in tests too).
3. Optional: `php artisan vendor:publish --tag=sharpapi-ecommerce-review-sentiment` copies `config/sharpapi-ecommerce-review-sentiment.php`. Only publish it if you need per-package overrides; the env keys below already work without it.
4. Get the service by type-hinting `EcommerceReviewSentimentService` (constructor or `handle()` injection) or `app(EcommerceReviewSentimentService::class)`. `new EcommerceReviewSentimentService()` works too (no constructor args) but tests can't swap it with `$this->mock()`.

## API & config reference

### Config: `config/sharpapi-ecommerce-review-sentiment.php`

| Key | Env | Default | Effect |
|---|---|---|---|
| `api_key` | `SHARP_API_KEY` | none | Required. |
| `base_url` | `SHARP_API_BASE_URL` | `https://sharpapi.com/api/v1` | API base URL. |
| `api_job_status_polling_wait` | `SHARP_API_JOB_STATUS_POLLING_WAIT` | `180` | Seconds `fetchResults()` keeps polling before it throws `ApiException`. |
| `api_job_status_polling_interval` | `SHARP_API_JOB_STATUS_POLLING_INTERVAL` | `10` | Seconds between status checks when the API sends no `Retry-After` header, or always when the next key is `true`. |
| `api_job_status_use_polling_interval` | `SHARP_API_JOB_STATUS_USE_POLLING_INTERVAL` | `false` | `true` ignores `Retry-After` and polls every `api_job_status_polling_interval` seconds. Applied since v1.1.0; older versions ignored it. |

The other `sharpapi/laravel-*` wrappers read the same `SHARP_API_*` env keys, so one `.env` block configures all of them. The service also inherits the php-core setters (`setApiJobStatusPollingWait()`, `setApiJobStatusPollingInterval()`, `setUseCustomInterval()`) for per-instance overrides.

### Submit: `productReviewSentiment()`

```php
public function productReviewSentiment(string $review): string
```

| Parameter | Type | Default | Meaning |
|---|---|---|---|
| `$review` | `string` | (required) | The review text. Sent as `content`. There are no language or tone options. |

Sends `POST /ecommerce/review_sentiment` and returns the job's **status URL** (a string), not the result.

### Collect: `fetchResults()`

```php
public function fetchResults(string $statusUrl): \SharpAPI\Core\DTO\SharpApiJob
```

Blocks and polls until the job is `success` or `failed`, honouring `Retry-After` and the polling wait. `SharpApiJob` has public `id`, `type`, `status` (a plain string) and `?stdClass $result`, plus `getResultJson()`, `getResultArray()` (shallow) and `getResultObject()`.

### Result

Example (from the README). `fetchResults()` returns only the `result` part; the README shows it inside the full `data.attributes` envelope.

```json
{
  "score": "85",
  "opinion": "NEGATIVE"
}
```

- `opinion` is one of `POSITIVE`, `NEGATIVE`, `NEUTRAL` (per the service docblock).
- `score` (0-100) arrives as a **string** in the README example. Cast it with `(int)` before comparing or storing it in an integer column.

### Exceptions

- `InvalidArgumentException`: empty `SHARP_API_KEY`, thrown by the service constructor.
- `SharpAPI\Core\Exceptions\ApiException`: polling ran past `api_job_status_polling_wait`, or HTTP 429 retries ran out.
- `GuzzleHttp\Exception\ClientException` (4xx such as 401 bad key or 422 validation) and other `GuzzleHttp\Exception\GuzzleException`s (5xx, network): from either call.
- A job that finishes with status `failed` does **not** throw. See Gotchas.

## Recipes

### Queued job (the default way to call it)

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SharpAPI\Core\Enums\SharpApiJobStatusEnum;
use SharpAPI\EcommerceReviewSentiment\EcommerceReviewSentimentService;

class ScoreReviewSentiment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Must exceed SHARP_API_JOB_STATUS_POLLING_WAIT (180 s by default) plus request time.
    public int $timeout = 300;

    // Every retry submits a new SharpAPI job and spends quota again.
    public int $tries = 1;

    public function __construct(public string $reviewText) {}

    public function handle(EcommerceReviewSentimentService $service): void
    {
        $statusUrl = $service->productReviewSentiment($this->reviewText);

        $job = $service->fetchResults($statusUrl);

        if ($job->status !== SharpApiJobStatusEnum::SUCCESS->value) {
            Log::warning('SharpAPI productReviewSentiment failed', ['job_id' => $job->id, 'status' => $job->status]);

            return;
        }

        $result = json_decode($job->getResultJson(), true);

        // ... persist $result
    }
}
```

Dispatch it with `ScoreReviewSentiment::dispatch(...)`. The worker's `--timeout` (or the Horizon supervisor `timeout`) and the queue connection's `retry_after` must both be at least the job's `$timeout`. Otherwise the worker kills the job mid-poll, or a second worker picks it up and submits it again.

### Reading the result

```php
$sentiment = json_decode($job->getResultJson(), true);
$opinion = $sentiment['opinion'] ?? null; // POSITIVE | NEGATIVE | NEUTRAL
$score = (int) ($sentiment['score'] ?? 0);
```

## Gotchas

1. `fetchResults()` already blocks and polls, up to `api_job_status_polling_wait` seconds, and honours `Retry-After`. Never write your own `while ($job->status === 'pending')` loop or call `fetchResults()` repeatedly.
2. A failed job does not throw. Compare `$job->status` with `SharpApiJobStatusEnum::SUCCESS->value` (`SharpAPI\Core\Enums\SharpApiJobStatusEnum`, values `new`, `pending`, `failed`, `success`) before reading the result. A failed job's `$result` carries no usable payload, so `->field` access on it gives undefined-property warnings and `null`s.
3. Never call `fetchResults()` inside an HTTP request, Nova action or Livewire action that runs synchronously: it can block for minutes. Use a queued job whose `$timeout` exceeds the polling wait. Keep `$tries` low (1-2), because each retry re-submits and burns quota. Worker/Horizon `timeout` and `retry_after` must be ≥ the job `$timeout`.
4. For reliable arrays use `json_decode($job->getResultJson(), true)`. `getResultArray()` only converts the top level, so nested values stay `stdClass`, and a list result comes back as an object with numeric keys.

## Testing

`Http::fake()` does **not** intercept this package: php-core sends requests through its own Guzzle client. Mock the service instead. That only works when your code resolves it from the container (DI or `app()`), not with `new`.

```php
use Mockery\MockInterface;
use SharpAPI\Core\DTO\SharpApiJob;
use SharpAPI\EcommerceReviewSentiment\EcommerceReviewSentimentService;

$this->mock(EcommerceReviewSentimentService::class, function (MockInterface $mock) {
    $mock->shouldReceive('productReviewSentiment')->once()->andReturn('https://sharpapi.com/api/v1/job/status/test-job');
    $mock->shouldReceive('fetchResults')->once()->andReturn(new SharpApiJob(
        id: 'test-job',
        type: 'ecommerce_review_sentiment',
        status: 'success',
        result: (object) json_decode(json_encode(['score' => '85', 'opinion' => 'POSITIVE'])),
    ));
});
```

- Also cover the failure branch: return a `SharpApiJob` with `status: 'failed'` and `result: null`, and assert nothing gets persisted.
- The real constructor needs a non-empty key. Set `SHARP_API_KEY` (any value) in `phpunit.xml` if a test resolves the real service.
- Queue tests: `Queue::fake()` plus `Queue::assertPushed(ScoreReviewSentiment::class)`, then test `handle()` separately with the mocked service.
