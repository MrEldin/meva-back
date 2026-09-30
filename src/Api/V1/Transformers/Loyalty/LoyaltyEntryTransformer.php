<?php

namespace Meva\Api\V1\Transformers\Loyalty;

use Meva\Entities\Loyalty\Models\LoyaltyEntry;
use PHPOpenSourceSaver\Fractal\TransformerAbstract;

/**
 * One line of a member's points history, in words.
 */
class LoyaltyEntryTransformer extends TransformerAbstract
{
    public function transform(LoyaltyEntry $entry): array
    {
        $reference = $entry->order?->reference;

        return [
            'id' => (int) $entry->id,
            'points' => (int) $entry->points,
            'reason' => $entry->reason,
            'label' => $this->label($entry, $reference),
            'order_reference' => $reference,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }

    protected function label(LoyaltyEntry $entry, ?string $reference): string
    {
        return match ($entry->reason) {
            LoyaltyEntry::WELCOME => 'Dobrodošlica',
            LoyaltyEntry::ORDER => trim('Porudžbina '.$reference),
            LoyaltyEntry::REVERSAL => trim('Poništeno: porudžbina '.$reference),
            LoyaltyEntry::REDEEM => 'Kupon '.($entry->note ?: 'popusta'),
            default => $entry->note ?: 'Korekcija poena',
        };
    }
}
