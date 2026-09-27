<x-mcp::app :title="$title">
    <x-slot:head>
        @include('mcp.partials.theme')
        @verbatim
        <script type="module">
        createMcpApp(async (app) => {
            const el = (id) => document.getElementById(id);
            let state = null;

            const render = (data) => {
                state = data;
                el('status').outerHTML = `<span id="status" class="badge ${h(data.status)}">${h(data.status_label)}</span>`;
                el('to').textContent = `${data.lead.company} <${data.lead.email ?? 'no email'}>`;
                el('from').textContent = data.mailbox ?? 'the box with the most room';
                el('when').textContent = data.sends_at ?? '';
                el('subject').textContent = data.subject;
                el('body').textContent = data.body;

                const missing = data.missing_tags ?? [];
                el('missing').hidden = missing.length === 0;
                el('missing').innerHTML = missing.length
                    ? 'Still unfilled: ' + missing.map((tag) => `<span class="tag missing">{{${h(tag)}}}</span>`).join(' ')
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
            <span class="title">Mail</span>
            <span id="status" class="badge preview">Preview</span>
            <span class="spacer"></span>
            <span id="when"></span>
        </div>
        <dl class="rows">
            <dt>To</dt><dd id="to"></dd>
            <dt>From</dt><dd id="from"></dd>
        </dl>
        <div id="subject" style="padding: 0 14px; font-weight: 600; font-size: 15px;"></div>
        <pre id="body" class="body"></pre>
        <div id="missing" class="hint" hidden></div>
        <div id="hint" class="hint"></div>
        <div class="actions">
            <button id="send" class="btn primary" type="button">Send</button>
            <button id="edit" class="btn" type="button">Edit in Snelreach</button>
        </div>
    </div>
</x-mcp::app>
