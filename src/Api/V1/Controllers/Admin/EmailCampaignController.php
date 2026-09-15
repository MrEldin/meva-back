<?php

namespace Meva\Api\V1\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Meva\Api\V1\Controllers\Controller;
use Meva\Entities\Marketing\Email\AudienceResolver;
use Meva\Entities\Marketing\Email\BlockLibrary;
use Meva\Entities\Marketing\Email\EmailRenderer;
use Meva\Entities\Marketing\Email\ProductResolver;
use Meva\Entities\Marketing\Email\TemplateLibrary;
use Meva\Entities\Marketing\Jobs\SendCampaign;
use Meva\Entities\Marketing\Models\EmailCampaign;

/**
 * The e-mail campaign desk.
 *
 * A campaign is stored as data -- a subject, a preheader and an ordered list of
 * blocks -- and turned into HTML only when it is previewed or sent. Both go
 * through the same renderer, which is what makes the preview honest: the admin
 * is looking at the message, not at an approximation of it.
 */
class EmailCampaignController extends Controller
{
    public function __construct(
        protected EmailRenderer $renderer,
        protected AudienceResolver $audiences,
        protected ProductResolver $products,
    ) {}

    /**
     * Everything the editor needs to draw itself: blocks, templates, segments
     * and the catalogue. One request instead of four.
     */
    public function library()
    {
        return response()->json([
            'data' => [
                'blocks' => BlockLibrary::all(),
                'templates' => TemplateLibrary::all(),
                'segments' => AudienceResolver::segments(),
                'sizes' => $this->audiences->sizes(Auth::user()?->email),
                'products' => $this->products->catalogue(),
                'tokens' => [
                    ['token' => '{ime}', 'note' => 'Ime primaoca, ili „draga/i" kad ga nema.'],
                    ['token' => '{email}', 'note' => 'Adresa primaoca.'],
                    ['token' => '{prodavnica}', 'note' => 'Adresa prodavnice.'],
                    ['token' => '{odjava}', 'note' => 'Link za odjavu.'],
                    ['token' => '{godina}', 'note' => 'Tekuća godina.'],
                ],
            ],
        ]);
    }

    /**
     * The campaign list, newest first.
     */
    public function index(Request $request)
    {
        $campaigns = EmailCampaign::query()
            ->with('author:id,first_name,last_name')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(min(60, (int) $request->query('per_page', 30)));

        return response()->json([
            'data' => collect($campaigns->items())->map(fn (EmailCampaign $c): array => $this->summary($c))->all(),
            'meta' => [
                'total' => $campaigns->total(),
                'page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
            ],
        ]);
    }

    /**
     * Create a campaign from a template. The editor asks for a name and a
     * template and lands straight in the editor, so the blocks are filled in
     * here rather than left for the first save.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:160',
            'template' => 'required|string|max:60',
        ]);

        $template = TemplateLibrary::find($request->input('template'));

        if ($template === null) {
            return response()->json(['message' => 'Nepoznat šablon.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $campaign = EmailCampaign::create([
            'name' => $request->input('name'),
            'template' => $template['key'],
            'subject' => $template['subject'],
            'preheader' => $template['preheader'],
            'audience' => 'subscribers',
            'blocks' => TemplateLibrary::blocks($template['key']),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        return response()->json(['data' => $this->detail($campaign)], Response::HTTP_CREATED);
    }

    public function show(EmailCampaign $campaign)
    {
        return response()->json(['data' => $this->detail($campaign)]);
    }

    /**
     * Save the editor's work. A campaign that has already gone out is frozen,
     * so what was sent can always be read back exactly.
     */
    public function update(Request $request, EmailCampaign $campaign)
    {
        if ($campaign->status === 'sent') {
            return response()->json(['message' => 'Poslata kampanja se više ne menja.'], Response::HTTP_CONFLICT);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:160',
            'subject' => 'nullable|string|max:200',
            'preheader' => 'nullable|string|max:200',
            'audience' => 'nullable|string|max:40',
            'blocks' => 'nullable|array',
            'blocks.*.type' => 'required|string|max:40',
        ]);

        $campaign->fill($request->only(['name', 'subject', 'preheader', 'audience', 'blocks']))->save();

        return response()->json(['data' => $this->detail($campaign)]);
    }

    public function destroy(EmailCampaign $campaign)
    {
        $campaign->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Render the campaign as HTML.
     *
     * The editor posts the blocks it currently has on screen rather than
     * relying on what is saved, so the preview follows typing without waiting
     * for a save. The result is returned as HTML for an iframe's srcdoc.
     */
    public function preview(Request $request, EmailCampaign $campaign)
    {
        $draft = clone $campaign;

        if ($request->has('blocks')) {
            $draft->blocks = $request->input('blocks', []);
        }

        foreach (['subject', 'preheader'] as $field) {
            if ($request->has($field)) {
                $draft->{$field} = $request->input($field);
            }
        }

        $user = Auth::user();

        $html = $this->renderer->render($draft, [
            'ime' => $request->input('sample_name') ?: ($user?->first_name ?: 'Milice'),
            'email' => $user?->email ?? '',
        ]);

        return response($html, Response::HTTP_OK)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Send one copy to the signed-in admin, or to a named address.
     */
    public function test(Request $request, EmailCampaign $campaign)
    {
        $request->validate(['email' => 'nullable|email']);

        $to = $request->input('email') ?: Auth::user()?->email;

        if (! $to) {
            return response()->json(['message' => 'Nema adrese za probu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $campaign->subject) {
            return response()->json(['message' => 'Kampanja nema naslov.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        dispatch_sync(new SendCampaign($campaign->id, [[
            'email' => $to,
            'name' => Auth::user()?->first_name,
        ]], test: true));

        return response()->json(['data' => ['sent_to' => $to]]);
    }

    /**
     * How many people the campaign would reach right now.
     */
    public function audience(Request $request, EmailCampaign $campaign)
    {
        $segment = $request->query('audience', $campaign->audience ?? 'subscribers');
        $recipients = $this->audiences->recipients($segment, Auth::user()?->email);

        return response()->json([
            'data' => [
                'audience' => $segment,
                'count' => count($recipients),
                'sample' => array_slice($recipients, 0, 8),
            ],
        ]);
    }

    /**
     * Send the campaign. Queued, because a few thousand addresses must not
     * hold an HTTP request open.
     */
    public function send(Request $request, EmailCampaign $campaign)
    {
        if ($campaign->status === 'sent' || $campaign->status === 'sending') {
            return response()->json(['message' => 'Kampanja je već poslata.'], Response::HTTP_CONFLICT);
        }

        if (! $campaign->subject) {
            return response()->json(['message' => 'Kampanja nema naslov.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $recipients = $this->audiences->recipients($campaign->audience ?? 'subscribers', Auth::user()?->email);

        if ($recipients === []) {
            return response()->json(['message' => 'Izabrana publika je prazna.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $campaign->update([
            'status' => 'sending',
            'recipients' => count($recipients),
        ]);

        // Chunked so one failure costs a hundred messages, not all of them.
        foreach (array_chunk($recipients, 100) as $chunk) {
            dispatch(new SendCampaign($campaign->id, $chunk));
        }

        $campaign->update(['status' => 'sent', 'sent_at' => now()]);

        return response()->json(['data' => $this->detail($campaign->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(EmailCampaign $c): array
    {
        $template = TemplateLibrary::find($c->template ?? '');

        return [
            'id' => $c->id,
            'name' => $c->name,
            'template' => $c->template,
            'template_name' => $template['name'] ?? $c->template,
            'stage' => $template['stage'] ?? null,
            'accent' => $template['accent'] ?? null,
            'subject' => $c->subject,
            'preheader' => $c->preheader,
            'audience' => $c->audience,
            'status' => $c->status,
            'blocks' => is_array($c->blocks) ? count($c->blocks) : 0,
            'recipients' => $c->recipients,
            'sent_at' => $c->sent_at?->toAtomString(),
            'created_at' => $c->created_at?->toAtomString(),
            'author' => $c->author ? trim($c->author->first_name.' '.$c->author->last_name) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detail(EmailCampaign $c): array
    {
        return array_merge($this->summary($c), [
            'blocks' => $c->blocks ?? [],
            'advice' => TemplateLibrary::find($c->template ?? '')['advice'] ?? null,
        ]);
    }
}
