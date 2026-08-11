<?php

namespace App\Sentry;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Strips potentially sensitive data from Sentry events before they leave the app.
 *
 * SiVote handles secret ballots and voter PII. Request bodies, query strings,
 * cookies and the server environment can carry voting codes, voter identifiers
 * or vote selections — none of which belong in an error tracker (even a
 * self-hosted one with 90-day retention). `send_default_pii` is already false;
 * this is defence in depth on top of it.
 *
 * Referenced from config/sentry.php as an array callable `[Scrubber::class,
 * 'handle']` rather than a closure, so the config stays serialisable for
 * `php artisan config:cache` (which the deploy runs).
 */
class Scrubber
{
    public static function handle(Event $event, ?EventHint $hint = null): ?Event
    {
        $request = $event->getRequest();

        if ($request !== []) {
            // Keep only the path of the URL — a voting code or token can ride in
            // the query string.
            if (isset($request['url']) && is_string($request['url'])) {
                $request['url'] = explode('?', $request['url'], 2)[0];
            }

            unset(
                $request['query_string'],
                $request['cookies'],
                $request['data'],
                $request['env'],
            );

            $event->setRequest($request);
        }

        return $event;
    }
}
