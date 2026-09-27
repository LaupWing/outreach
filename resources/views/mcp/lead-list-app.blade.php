<x-mcp::app :title="$title">
    <x-slot:head>
        @include('mcp.partials.theme')
        @verbatim
        <script type="module">
        createMcpApp(async (app) => {
            const root = document.getElementById('root');

            const table = (leads, extra) => leads.length === 0 ? '<div class="empty">Nothing here.</div>' : `
                <table>
                    <thead><tr><th>Company</th><th>City</th><th>Status</th><th>Email</th>${extra ? `<th>${h(extra.label)}</th>` : ''}</tr></thead>
                    <tbody>${leads.map((lead) => `
                        <tr class="row" data-url="${h(lead.url)}">
                            <td><strong>${h(lead.company)}</strong></td>
                            <td class="muted">${h(lead.city ?? '')}</td>
                            <td>${badge(lead.status)}</td>
                            <td class="muted">${h(lead.email ?? '—')}</td>
                            ${extra ? `<td class="muted">${extra.value(lead)}</td>` : ''}
                        </tr>`).join('')}
                    </tbody>
                </table>`;

            const render = (data) => {
                let sections = [];

                if (data.waiting) {
                    // check_inbox: replies waiting for an answer.
                    sections.push({ title: `${data.waiting.length} replies waiting`, html: data.waiting.length === 0 ? '<div class="empty">No replies waiting.</div>' : data.waiting.map((m) => `
                        <div class="section" style="border-top:0;border-bottom:1px solid var(--border)">
                            <div><strong>${h(m.company)}</strong> <span class="muted">${h(m.email ?? '')}</span></div>
                            <div class="muted">Re: ${h(m.subject)}</div>
                            <div style="margin-top:4px">${h(m.reply?.body ?? '')}</div>
                        </div>`).join('') });
                }
                if (data.due) {
                    sections.push({ title: `${data.due.length} due for a follow-up`, html: table(data.due, { label: 'Why', value: (l) => `step ${l.next_step ?? '—'} · ${h(l.reason)}` }) });
                    sections.push({ title: `${data.replied.length} replied`, html: table(data.replied) });
                }
                if (data.leads) {
                    const found = data.found !== undefined ? ` · ${data.found} found, ${data.leads.length} new` : '';
                    sections.push({ title: `${data.leads.length} leads${found}`, html: table(data.leads, { label: 'Hook', value: (l) => h(l.hook ?? '') }) });
                }

                root.innerHTML = sections.map((s) => `
                    <div class="card" style="margin-bottom:10px">
                        <div class="bar"><span class="title">${h(s.title)}</span></div>
                        ${s.html}
                    </div>`).join('') + `<div class="actions" style="border-top:0;padding-top:0">${openButton(data.run_url ? 'Open run in Snelreach' : 'Open leads in Snelreach')}</div>`;

                root.querySelectorAll('tr.row').forEach((tr) => tr.addEventListener('click', () => app.openLink(tr.dataset.url)));
                root.querySelector('.open').addEventListener('click', () => app.openLink(data.run_url ?? data.url));
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
