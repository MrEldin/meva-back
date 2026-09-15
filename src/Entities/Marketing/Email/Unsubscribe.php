<?php

namespace Meva\Entities\Marketing\Email;

use Illuminate\Support\Facades\URL;
use Meva\Entities\Marketing\Models\Subscriber;

/**
 * Leaving the list.
 *
 * The link carries a signature rather than a stored token, so it works for
 * people who bought once and were never on a subscriber row. Opting out must
 * take one click and no login: anything harder and people press "spam"
 * instead, which costs the whole shop's deliverability.
 */
class Unsubscribe
{
    /** A signed, never-expiring opt-out link for one address. */
    public static function url(string $email): string
    {
        return URL::signedRoute('marketing.unsubscribe', ['email' => strtolower(trim($email))]);
    }

    /** Record the opt-out. Safe to call twice. */
    public static function record(string $email): void
    {
        $email = strtolower(trim($email));

        $subscriber = Subscriber::firstOrNew(['email' => $email]);
        $subscriber->unsubscribed_at ??= now();
        $subscriber->source ??= 'unsubscribe';
        $subscriber->save();
    }
}
