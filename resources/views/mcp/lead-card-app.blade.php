<x-mcp::app :title="$title">
    <x-slot:head>
        @include('mcp.partials.theme')
        @verbatim
        <script type="module">
        createMcpApp(async (app) => {
            const root = document.getElementById('root');

            const render = (data) => {
                const leads = data.leads ?? [data.lead ?? data];
                root.innerHTML = '';
                leads.forEach((lead) => {
                    const el = document.createElement('div');
                    el.className = 'card';
                    el.style.marginBottom = '10px';
                    root.appendChild(el);
                    one(el, { ...lead, lead });
                });
                if (leads.length === 0) root.innerHTML = '<div class="card"><div class="empty">No leads.</div></div>';
            };

            const one = (root, data) => {
                const lead = data.lead ?? data;
                const offer = data.offer ?? null;
                const signals = lead.signals ?? {};
                const facts = Object.entries(lead.facts ?? {});
                const messages = data.messages ?? [];
                const notes = data.notes ?? [];
                const steps = offer?.steps ?? [];

                const row = (label, value) => value ? `<dt>${h(label)}</dt><dd>${value}</dd>` : '';
                const software = (signals.software ?? []).join(', ');
                const signalBits = [
                    signals.copyright_year ? `© ${signals.copyright_year}` : null,
                    signals.viewport === false ? 'not mobile' : signals.viewport ? 'mobile' : null,
                    software || null,
                    signals.last_news_at ? `last news ${signals.last_news_at}` : null,
                    signals.blocked ? 'site blocked us' : null,
                    signals.javascript_only ? 'JavaScript only' : null,
                ].filter(Boolean).join(' · ');

                root.innerHTML = `
                    <div class="bar">
                        <span class="title">${h(lead.company)}</span>
                        ${badge(lead.status)}
                        <span class="spacer"></span>
                        <span>${h([lead.city, lead.niche].filter(Boolean).join(' · '))}</span>
                    </div>
                    <dl class="rows">
                        ${row('Email', lead.email ? h(lead.email) : '<span class="muted">none found</span>')}
                        ${row('Phone', h(lead.phone))}
                        ${row('Website', h(lead.website))}
                        ${row('Signals', h(signalBits))}
                        ${row('Hook', h(lead.hook))}
                        ${row('Offer', offer ? `${h(offer.name)}${offer.next_step ? ` <span class="muted">· next step ${offer.next_step}</span>` : ' <span class="muted">· sequence finished</span>'}` : '')}
                        ${row('Next', lead.next_action_at ? h(new Date(lead.next_action_at).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })) : '')}
                    </dl>
                    ${facts.length ? `<div class="section"><h3>Facts</h3><dl class="rows" style="padding:0">${facts.map(([k, v]) => `<dt class="mono">${h(k)}</dt><dd>${h(v)}</dd>`).join('')}</dl></div>` : ''}
                    ${steps.length ? `<div class="section"><h3>Sequence</h3>${steps.map((s) => `<div style="margin-bottom:4px"><span class="muted">${s.step}.</span> ${h(s.subject)} ${(s.missing_tags ?? []).map((t) => `<span class="tag missing">{{${h(t)}}}</span>`).join(' ')}</div>`).join('')}</div>` : ''}
                    ${messages.length ? `<div class="section"><h3>Messages</h3>${messages.map((m) => `<div style="margin-bottom:4px">${badge(m.status)} <span class="muted">step ${m.step}</span> ${h(m.subject)}${m.reply ? `<div class="muted" style="margin-left:8px">↳ ${h(m.reply.body)}</div>` : ''}</div>`).join('')}</div>` : ''}
                    ${notes.length ? `<div class="section"><h3>Notes</h3>${notes.map((n) => `<div class="muted">${h(n.body)}</div>`).join('')}</div>` : ''}
                    <div class="actions">${openButton()}</div>`;

                root.querySelector('.open').addEventListener('click', () => app.openLink(lead.url));
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
