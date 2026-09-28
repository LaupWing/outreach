<?php

namespace App\Mcp\Tools;

use App\Mcp\Account;
use App\Mcp\LeadSummary;
use App\Mcp\MessageSummary;
use App\Mcp\Reply;
use App\Mcp\Resources\LeadCardApp;
use App\Models\Lead;
use App\Models\SequenceStep;
use App\Models\User;
use App\Support\Enrichment\SiteReader;
use App\Support\Enrichment\SiteText;
use App\Support\Mail\Placeholders;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('leads_context')]
#[Description('Everything needed to write to leads, one or many: each lead with its signals, hook, facts and site_text (what their homepage and about page say, saved while enriching), its offer with the sequence (each step with the {{tags}} it uses and which ones the lead still lacks), the mail thread so far, the notes, and optionally the text of its homepage. Call this before send_steps or send_mails.')]
#[IsReadOnly]
#[RendersApp(resource: LeadCardApp::class)]
class LeadsContext extends Tool
{
    public function handle(Request $request, SiteReader $reader): Response|ResponseFactory
    {
        $user = Account::for($request);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1', 'max:50'],
            'lead_ids.*' => ['integer'],
            'with_site_text' => ['sometimes', 'boolean'],
        ]);

        $leads = $user->leads()->with(['niche', 'offer', 'notes'])->whereIn('id', $validated['lead_ids'])->get();
        $unknown = array_values(array_diff($validated['lead_ids'], $leads->modelKeys()));

        $contexts = $leads->map(fn (Lead $lead) => $this->context($user, $lead, $reader, $validated['with_site_text'] ?? false))->values()->all();

        $text = sprintf('%d leads.', count($contexts));

        if ($unknown !== []) {
            $text .= ' Not on this account: '.implode(', ', $unknown).'.';
        }

        return Reply::make($text, ['leads' => $contexts, 'unknown' => $unknown, 'url' => rtrim(config('app.url'), '/').'/leads']);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(User $user, Lead $lead, SiteReader $reader, bool $withSite): array
    {
        $steps = $lead->offer === null ? collect() : $user->sequenceSteps()->where('offer_id', $lead->offer_id)->orderBy('step')->get();
        $lastStep = (int) $lead->messages()->whereNotIn('status', ['queued', 'failed', 'draft'])->max('step');
        $messages = $lead->messages()->reorder()->orderBy('id')->get();

        $context = [
            ...LeadSummary::from($lead),
            'facts' => $lead->facts,
            // Saved while enriching: what the business says about itself. with_site_text reads it live.
            'site_text' => $lead->site_text,
            'niche' => $lead->niche?->name,
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

        if ($withSite && $lead->website !== null) {
            $html = $reader->fetch('https://'.$lead->website);
            $context['site_text'] = $html === null ? null : $this->text($html);
        }

        return $context;
    }

    /**
     * The readable text of a page, whitespace collapsed, capped so it fits a prompt.
     */
    private function text(string $html): string
    {
        return SiteText::from($html, 6000);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_ids' => $schema->array()->description('The leads to look at, up to 50.')->required(),
            'with_site_text' => $schema->boolean()->description('Also fetch each homepage and include its text (up to 6,000 characters each). A few seconds per lead.')->default(false),
        ];
    }
}
