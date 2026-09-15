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

    /**
     * One attempt only: a batch that is retried would send a second copy to
     * everyone it already reached before it failed, and a duplicate is worse
     * than a gap we can see in the log. Individual messages get their own
     * retry inside handle().
     */
    public int $tries = 1;

    /**
     * Long enough for a paced batch. The worker's own default is sixty
     * seconds, which would kill a batch halfway through.
     */
    public int $timeout = 900;

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

        // Resend meters sends per second and answers 429 over the limit. A
        // pause between messages keeps a four thousand address campaign under
        // it instead of losing the tail of the list to rate limiting.
        $gap = (int) round(1_000_000 / max(1, (int) config('meva.mail_rate', 2)));

        foreach ($this->recipients as $index => $recipient) {
            $email = (string) ($recipient['email'] ?? '');

            if ($email === '') {
                continue;
            }

            if ($index > 0) {
                usleep($gap);
            }

            $unsubscribe = Unsubscribe::url($email);

            $tokens = [
                'ime' => $this->firstName($recipient['name'] ?? null),
                'email' => $email,
                'odjava' => $unsubscribe,
            ];

            $message = fn (): CampaignMail => new CampaignMail(
                subjectLine: ($this->test ? '[PROBA] ' : '').(string) $campaign->subject,
                htmlBody: $renderer->render($campaign, $tokens),
                plain: (new EmailRenderer)->renderText($campaign, $tokens),
                unsubscribeUrl: $unsubscribe,
                campaignKey: 'meva-campaign-'.$campaign->id,
            );

            try {
                Mail::to($email)->send($message());
            } catch (\Throwable $e) {
                // One retry after a breath: most failures here are the provider
                // asking us to slow down, and a lost message is a lost sale.
                try {
                    usleep($gap * 4);
                    Mail::to($email)->send($message());
                } catch (\Throwable $again) {
                    Log::warning('Campaign send failed', [
                        'campaign' => $campaign->id,
                        'email' => $email,
                        'error' => $again->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * A batch that fell over entirely -- the provider is down, or the queue
     * lost its connection. Recorded with the addresses so it can be re-sent.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Campaign batch failed', [
            'campaign' => $this->campaignId,
            'recipients' => count($this->recipients),
            'first' => $this->recipients[0]['email'] ?? null,
            'error' => $e->getMessage(),
        ]);
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
