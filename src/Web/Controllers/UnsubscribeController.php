<?php

namespace Meva\Web\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Meva\Entities\Marketing\Email\Unsubscribe;

/**
 * The page behind the "unsubscribe" link in every campaign.
 *
 * Served by the API host rather than the storefront so the link keeps working
 * even if the front end is down, and so the signature can be checked where it
 * was made. Gmail and Yahoo also POST to this address without a person
 * involved -- that is what one-click unsubscribe is -- so POST answers with
 * nothing but a 200.
 */
class UnsubscribeController extends Controller
{
    /** The one-click POST mail clients send on the recipient's behalf. */
    public function post(Request $request)
    {
        if ($request->hasValidSignature()) {
            Unsubscribe::record((string) $request->query('email'));
        }

        return response('', 200);
    }

    /** The page a person lands on when they click the link themselves. */
    public function show(Request $request)
    {
        $valid = $request->hasValidSignature();
        $email = (string) $request->query('email');

        if ($valid) {
            Unsubscribe::record($email);
        }

        return response()->view('marketing.unsubscribed', [
            'valid' => $valid,
            'email' => $email,
            'shop' => rtrim(config('meva.storefront_url', 'https://meva.life'), '/'),
        ]);
    }
}
