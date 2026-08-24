<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Emit webhooks from model events
    |--------------------------------------------------------------------------
    |
    | Models using the DispatchesWebhooks trait emit a webhook on create,
    | update, and delete. Set WEBHOOKS_ENABLED=false to stop that everywhere
    | without a deploy — useful when rolling the feature out, or if a
    | subscriber's endpoint starts misbehaving.
    |
    | Managing subscriptions and retrying past deliveries stay available either
    | way; only new emissions are suppressed.
    |
    */

    'enabled' => env('WEBHOOKS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dispatch webhooks during tests
    |--------------------------------------------------------------------------
    |
    | Emission is suppressed while testing so the suite does not queue
    | deliveries; tests that cover webhook behaviour turn it back on.
    |
    | Per-webhook delivery settings (timeout, retry count) are columns on the
    | webhooks table, not configuration.
    |
    */

    'dispatch_in_tests' => env('WEBHOOKS_DISPATCH_IN_TESTS', false),

];
