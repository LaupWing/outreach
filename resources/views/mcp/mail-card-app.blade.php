<x-mcp::app :title="$title">
    <x-slot:head>
        <style>
            :root { color-scheme: light dark; }
            body { margin: 0; font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, sans-serif; color: var(--color-text-primary, #e5e5e5); background: transparent; }
            .card { border: 1px solid var(--color-border-primary, rgba(255,255,255,.12)); border-radius: 12px; background: var(--color-background-secondary, #171717); box-shadow: 0 1px 0 rgba(255,255,255,.06) inset, 0 8px 24px rgba(0,0,0,.25); overflow: hidden; }
            .bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--color-border-primary, rgba(255,255,255,.12)); font-size: 12px; color: var(--color-text-secondary, #a3a3a3); }
            .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; }
            .badge.preview { background: rgba(139,92,246,.15); color: #a78bfa; }
            .badge.queued { background: rgba(14,165,233,.15); color: #38bdf8; }
            .badge.draft { background: rgba(163,163,163,.15); color: #a3a3a3; }
            .meta { padding: 12px 14px 0; display: grid; grid-template-columns: 64px 1fr; row-gap: 4px; column-gap: 8px; font-size: 13px; }
            .meta dt { color: var(--color-text-secondary, #a3a3a3); }
            .meta dd { margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .subject { padding: 12px 14px 0; font-weight: 600; font-size: 15px; }
            .body { padding: 8px 14px 14px; white-space: pre-wrap; color: var(--color-text-primary, #e5e5e5); }
            .missing { margin: 0 14px 12px; padding: 8px 10px; border-radius: 8px; background: rgba(239,68,68,.12); color: #f87171; font-size: 12px; }
            .missing code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
            .actions { display: flex; gap: 8px; padding: 0 14px 14px; }
            button { cursor: pointer; border-radius: 8px; padding: 8px 12px; font: inherit; font-size: 13px; font-weight: 500; border: 1px solid var(--color-border-primary, rgba(255,255,255,.14)); background: transparent; color: inherit; }
            button.primary { background: #8b5cf6; border-color: #8b5cf6; color: white; }
            button:disabled { opacity: .5; cursor: default; }
            .hint { padding: 0 14px 12px; font-size: 12px; color: var(--color-text-secondary, #a3a3a3); }
        </style>
        @verbatim
        <script type="module">
        createMcpApp(async (app) => {
            const el = (id) => document.getElementById(id);
            let state = null;

            const render = (data) => {
                state = data;
                el('status').textContent = data.status_label;
                el('status').className = 'badge ' + data.status;
                el('to').textContent = `${data.lead.company} <${data.lead.email ?? 'no email'}>`;
                el('from').textContent = data.mailbox ?? 'the box with the most room';
                el('when').textContent = data.sends_at ?? '';
                el('subject').textContent = data.subject;
                el('body').textContent = data.body;

                const missing = data.missing_tags ?? [];
                el('missing').hidden = missing.length === 0;
                el('missing').innerHTML = missing.length
                    ? 'Still unfilled: ' + missing.map((tag) => '<code>{{' + tag + '}}</code>').join(', ')
                    : '';

                el('send').hidden = data.status !== 'preview';
                el('send').disabled = missing.length > 0 || !data.lead.email;
                el('hint').textContent = data.hint ?? '';
            };

            app.onToolResult((params) => {
                if (params.structuredContent) render(params.structuredContent);
            });

            el('edit').addEventListener('click', () => state && app.openLink(state.edit_url));

            el('send').addEventListener('click', async () => {
                if (!state) return;
                el('send').disabled = true;
                el('send').textContent = 'Sending…';
                const result = await app.callServerTool(state.send_tool, state.send_arguments);
                if (result.structuredContent) render(result.structuredContent);
                else el('hint').textContent = result.content?.[0]?.text ?? 'Something went wrong.';
            });
        });
        </script>
        @endverbatim
    </x-slot:head>

    <div class="card">
        <div class="bar">
            <span id="status" class="badge preview">Preview</span>
            <span id="when"></span>
        </div>
        <dl class="meta">
            <dt>To</dt><dd id="to"></dd>
            <dt>From</dt><dd id="from"></dd>
        </dl>
        <div id="subject" class="subject"></div>
        <div id="body" class="body"></div>
        <div id="missing" class="missing" hidden></div>
        <div id="hint" class="hint"></div>
        <div class="actions">
            <button id="send" class="primary">Send</button>
            <button id="edit">Edit in Snelreach</button>
        </div>
    </div>
</x-mcp::app>
