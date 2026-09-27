<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Mcp\LeadSummary;
use App\Mcp\MessageSummary;
use App\Models\SequenceStep;
use App\Support\Enrichment\SiteReader;
use App\Support\Mail\Placeholders;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('lead_context')]
#[Description('Everything needed to write to one lead: the lead with its signals, hook and facts, the offer with its sequence (each step with the {{tags}} it uses and which ones the lead still lacks), the mail thread so far, the notes, and optionally the text of the lead\'s homepage. Call this before send_step or send.')]
#[IsReadOnly]
class LeadContext extends Tool
{
    public function handle(Request $request, SiteReader $reader): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'lead_id' => ['required', 'integer'],
            'with_site_text' => ['sometimes', 'boolean'],
        ]);

        $lead = $user->leads()->with(['niche', 'offer', 'notes'])->find($validated['lead_id']);

        if ($lead === null) {
            return Response::error("No lead with id {$validated['lead_id']} on this account.");
        }

        $steps = $lead->offer === null ? collect() : $user->sequenceSteps()->where('offer_id', $lead->offer_id)->orderBy('step')->get();
        $lastStep = (int) $lead->messages()->whereNotIn('status', ['queued', 'failed', 'draft'])->max('step');
        $messages = $lead->messages()->reorder()->orderBy('id')->get();

        $context = [
            'lead' => [...LeadSummary::from($lead), 'facts' => $lead->facts, 'niche' => $lead->niche?->name],
            'offer' => $lead->offer === null ? null : [
                'id' => $lead->offer->id,
                'name' => $lead->offer->name,
                'description' => $lead->offer->description,
                'auto_follow_up' => $lead->offer->auto_follow_up,
                'tag_explanations' => $lead->offer->placeholders,
                'steps' => $steps->map(fn (SequenceStep $step) => [
                    'step' => $step->step,
                    'days_after_previous' => $step->days_after_previous,
                    'subject' => $step->subject,
                    'body' => $step->body,
                    'tags' => Placeholders::tagsIn($step->subject.' '.$step->body),
                    'missing_tags' => Placeholders::missing($step->subject.' '.$step->body, $lead),
                ])->all(),
                'next_step' => $steps->firstWhere('step', $lastStep + 1)?->step,
            ],
            'messages' => $messages->map(fn ($message) => MessageSummary::from($message))->all(),
            'notes' => $lead->notes->map(fn ($note) => ['body' => $note->body, 'at' => $note->created_at?->toJSON()])->all(),
        ];

        if (($validated['with_site_text'] ?? false) && $lead->website !== null) {
            $html = $reader->fetch('https://'.$lead->website);
            $context['site_text'] = $html === null ? null : $this->text($html);
        }

        $summary = sprintf(
            '%s (%s, %s). Status %s. %s%s',
            $lead->company, $lead->city ?? '-', $lead->niche?->name ?? 'no niche', $lead->status->value,
            $lead->offer === null ? 'No offer assigned; pass offer_id to send_step or use send.' : "Offer \"{$lead->offer->name}\", ".($context['offer']['next_step'] === null ? 'sequence finished.' : "next step {$context['offer']['next_step']}."),
            $messages->isEmpty() ? '' : ' '.$messages->count().' messages so far.',
        );

        return Response::make(Response::text($summary))->withStructuredContent($context);
    }

    /**
     * The readable text of a page, whitespace collapsed, capped so it fits a prompt.
     */
    private function text(string $html): string
    {
        $stripped = preg_replace('/<(script|style|noscript|svg)[^>]*>.*?<\/\1>/is', ' ', $html) ?? '';
        $stripped = preg_replace('/<br\s*\/?>|<\/(p|div|li|h[1-6]|tr)>/i', "\n", $stripped) ?? '';
        $text = html_entity_decode(strip_tags($stripped), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? '';
        $text = preg_replace("/\s*\n\s*/", "\n", $text) ?? '';

        return Str::limit(trim($text), 6000);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->description('The lead to look at.')->required(),
            'with_site_text' => $schema->boolean()->description('Also fetch the homepage and include its text (up to 6,000 characters). Takes a few seconds.')->default(false),
        ];
    }
}
