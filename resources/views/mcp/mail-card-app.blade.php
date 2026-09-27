<x-mcp::app :title="$title">
    <x-slot:head>
        @include('mcp.partials.theme')
        @verbatim
        <script type="module">
        createMcpApp(async (app) => {
            const root = document.getElementById('root');

            const card = (mail, index) => {
                const missing = mail.missing_tags ?? [];
                const canSend = mail.status === 'preview' && missing.length === 0 && mail.lead?.email;

                return `
                    <div class="card" style="margin-bottom:10px" data-index="${index}">
                        <div class="bar">
                            <span class="title">${h(mail.lead?.company ?? 'Mail')}</span>
                            ${badge(mail.status)}
                            <span class="spacer"></span>
                            <span>${h(mail.sends_at ?? '')}</span>
                        </div>
                        <dl class="rows">
                            <dt>To</dt><dd>${h(mail.lead?.email ?? 'no email')}</dd>
                            <dt>From</dt><dd>${h(mail.mailbox ?? 'the box with the most room')}</dd>
                        </dl>
                        <div style="padding:0 14px;font-weight:600;font-size:15px">${h(mail.subject)}</div>
                        <pre class="body">${h(mail.body)}</pre>
                        ${missing.length ? `<div class="hint">Still unfilled: ${missing.map((t) => `<span class="tag missing">{{${h(t)}}}</span>`).join(' ')}</div>` : ''}
                        ${mail.hint ? `<div class="hint">${h(mail.hint)}</div>` : ''}
                        <div class="actions">
                            ${mail.status === 'preview' ? `<button class="btn primary send" type="button" ${canSend ? '' : 'disabled'}>Send</button>` : ''}
                            <button class="btn edit" type="button">Edit in Snelreach</button>
                        </div>
                    </div>`;
            };

            const render = (data) => {
                const mails = data.mails ?? [data];
                const skipped = data.skipped ?? [];

                root.innerHTML = mails.map(card).join('')
                    + (skipped.length ? `<div class="card"><div class="hint" style="padding-top:12px">Skipped: ${skipped.map(h).join('; ')}</div></div>` : '');

                root.querySelectorAll('[data-index]').forEach((el) => {
                    const mail = mails[Number(el.dataset.index)];

                    el.querySelector('.edit').addEventListener('click', () => app.openLink(mail.edit_url));

                    el.querySelector('.send')?.addEventListener('click', async (event) => {
                        event.target.disabled = true;
                        event.target.textContent = 'Sending…';
                        const result = await app.callServerTool(mail.send_tool, mail.send_arguments);
                        const sent = result.structuredContent?.mails?.[0];
                        if (sent) {
                            mails[Number(el.dataset.index)] = sent;
                            render({ ...data, mails });
                        } else {
                            event.target.textContent = result.content?.[0]?.text?.split('\n')[0] ?? 'Something went wrong.';
                        }
                    });
                });
            };

            app.onToolResult((params) => {
                if (params.structuredContent) render(params.structuredContent);
            });
        });
        </script>
        @endverbatim
    </x-slot:head>

    <div id="root"><div class="card"><div class="empty">Loading…</div></div></div>
</x-mcp::app>
