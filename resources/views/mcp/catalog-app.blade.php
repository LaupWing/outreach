<x-mcp::app :title="$title">
    <x-slot:head>
        @include('mcp.partials.theme')
        @verbatim
        <script type="module">
        createMcpApp(async (app) => {
            const root = document.getElementById('root');
            const tags = (list, missing = []) => (list ?? []).map((t) => `<span class="tag${missing.includes(t) ? ' missing' : ''}">{{${h(t)}}}</span>`).join(' ');

            const offer = (o) => `
                <div class="card" style="margin-bottom:10px">
                    <div class="bar"><span class="title">${h(o.name)}</span>${badge(o.status)}<span class="spacer"></span><span>${h(o.niche ?? '')}${o.auto_follow_up ? ' · auto follow-up' : ''}</span></div>
                    ${o.description ? `<div class="hint" style="padding-top:10px">${h(o.description)}</div>` : ''}
                    ${(o.steps ?? []).map((s) => `<div class="section"><div><span class="muted">${s.step}.</span> <strong>${h(s.subject)}</strong> <span class="muted">${s.step > 1 ? `· ${s.days_after_previous} days later` : ''}</span></div><div class="muted" style="margin-top:2px">${tags(s.tags)}</div></div>`).join('')}
                    ${o.tag_explanations ? `<div class="section"><h3>Tags</h3>${Object.entries(o.tag_explanations).map(([k, v]) => `<div><span class="tag">{{${h(k)}}}</span> <span class="muted">${h(v)}</span></div>`).join('')}</div>` : ''}
                </div>`;

            const niches = (list) => `
                <div class="card">
                    <table>
                        <thead><tr><th>Niche</th><th>Status</th><th class="num">Leads</th><th class="num">Email</th><th class="num">Replied</th><th class="num">Customers</th><th>Findings</th></tr></thead>
                        <tbody>${list.map((n) => `<tr><td><strong>${h(n.name)}</strong></td><td>${badge(n.status)}</td><td class="num">${n.leads ?? '—'}</td><td class="num">${n.with_email ?? '—'}</td><td class="num">${n.replied ?? '—'}</td><td class="num">${n.customers ?? '—'}</td><td class="muted">${h(n.findings ?? n.why ?? '')}</td></tr>`).join('')}</tbody>
                    </table>
                </div>`;

            const render = (data) => {
                let html = '';
                if (data.offers) html = data.offers.length ? data.offers.map(offer).join('') : '<div class="card"><div class="empty">No offers yet.</div></div>';
                else if (data.niches) html = niches(data.niches);
                else if (data.name) html = niches([data]);
                root.innerHTML = html + `<div class="actions" style="border-top:0">${openButton(data.offers ? 'Open offers in Snelreach' : 'Open niches in Snelreach')}</div>`;
                root.querySelector('.open').addEventListener('click', () => app.openLink(data.url));
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
