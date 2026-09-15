<?php

namespace Meva\Entities\Marketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Meva\Entities\Marketing\Email\EmailRenderer;
use Meva\Entities\Marketing\Email\Unsubscribe;
use Meva\Entities\Marketing\Mail\CampaignMail;
use Meva\Entities\Marketing\Models\EmailCampaign;

/**
 * Sends one batch of a campaign.
 *
 * Each recipient gets their own render, because the greeting and the
 * unsubscribe link are theirs. A failure on one address must not stop the
 * batch, so every send is caught and logged and the job carries on -- losing a
 * hundred messages to one bad address would be worse than the bad address.
 */
class SendCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<int, array{email: string, name: string|null}>  $recipients
     */
    public function __construct(
        public int $campaignId,
        public array $recipients,
        public bool $test = false,
    ) {}

    public function handle(EmailRenderer $renderer): void
    {
        $campaign = EmailCampaign::find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        foreach ($this->recipients as $recipient) {
            $email = (string) ($recipient['email'] ?? '');

            if ($email === '') {
                continue;
            }

            $unsubscribe = Unsubscribe::url($email);

            $tokens = [
                'ime' => $this->firstName($recipient['name'] ?? null),
                'email' => $email,
                'odjava' => $unsubscribe,
            ];

            try {
                Mail::to($email)->send(new CampaignMail(
                    subjectLine: ($this->test ? '[PROBA] ' : '').(string) $campaign->subject,
                    htmlBody: $renderer->render($campaign, $tokens),
                    plain: (new EmailRenderer)->renderText($campaign, $tokens),
                    unsubscribeUrl: $unsubscribe,
                    campaignKey: 'meva-campaign-'.$campaign->id,
                ));
            } catch (\Throwable $e) {
                Log::warning('Campaign send failed', [
                    'campaign' => $campaign->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * The name as you would say it out loud: "Milice", not "Milica Petrović".
     */
    protected function firstName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'draga/i';
        }

        return explode(' ', $name)[0];
    }
}
