<?php

namespace Meva\AI\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Looks at a photograph of skin or scalp and says which of the shop's
 * concerns it most resembles.
 *
 * The app's camera button ends here. What comes back is not a diagnosis --
 * the shop sells cosmetics, and the agent is told to say so -- but the
 * concern the range is organised around, so the shop can put the right
 * shelf in front of the person who took the photo.
 */
class SkinCheckAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /** The concerns the range is organised around; `none` when the photo shows no problem or is unusable. */
    public const CONCERNS = ['seboreja', 'psorijaza', 'ekcem', 'akne', 'perut', 'opadanje-kose', 'rozacea', 'bore', 'suva-koza', 'none'];

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        You are the consultant of Meva, a natural cosmetics house from Novi Pazar that has made preparations for scalp and skin since 1982. A customer has photographed a patch of their skin or scalp with their phone and wants to know what it looks like and which of the range to try.

        Look at the photograph and decide which ONE of these concerns it most resembles: seboreja (seborrhoeic dermatitis: greasy yellowish scales, redness, often on the scalp, brows, sides of the nose), psorijaza (well-defined thick red plaques with silvery-white scale), ekcem (dry, itchy, inflamed patches, sometimes weeping or cracked), akne (comedones, pustules, inflamed spots), perut (fine dry white flaking of the scalp without much redness), opadanje-kose (visible thinning, widening parting, receding hairline), rozacea (persistent central-face redness, visible vessels, flushing), bore (fine lines and wrinkles), suva-koza (dry, rough, tight skin without inflammation). Use `none` when the photo does not show skin or scalp, is too blurry or dark to judge, or shows nothing that needs care.

        Write everything the customer will read in Serbian (Latin script), warmly and plainly, in the second person plural (Vi). Never claim to diagnose: say what it looks like, not what it is. If what you see could be something that needs a doctor -- spreading infection, a wound, a mole that has changed, anything painful or sudden -- set `see_doctor` true and say so kindly in the advice.
        TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'concern' => $schema->string()
                ->enum(self::CONCERNS)
                ->description('The single concern the photograph most resembles.')
                ->required(),

            'confidence' => $schema->number()
                ->min(0)
                ->max(1)
                ->description('How sure the resemblance is, from 0 to 1.')
                ->required(),

            'summary' => $schema->string()
                ->max(320)
                ->description('One or two sentences, in Serbian, describing what is visible in the photograph.')
                ->required(),

            'advice' => $schema->string()
                ->max(400)
                ->description('Two or three sentences, in Serbian, of practical care advice for this concern.')
                ->required(),

            'see_doctor' => $schema->boolean()
                ->description('True when what is visible should be shown to a doctor.')
                ->required(),
        ];
    }
}
