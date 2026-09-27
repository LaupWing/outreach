<x-mcp::app :title="$title">
    <x-slot:head>
        @include('mcp.partials.theme')
        @verbatim
        <script type="module">
        createMcpApp(async (app) => {
            const root = document.getElementById('root');
            const pct = (rate) => rate === null || rate === undefined ? '—' : `${Math.round(rate * 100)}%`;

            const group = (title, rows, first) => `
                <div class="section" style="padding:0">
                    <table>
                        <thead><tr><th>${h(title)}</th><th class="num">Leads</th><th class="num">Sent</th><th class="num">Replied</th><th class="num">Rate</th><th class="num">Customers</th><th class="num">Bounced</th></tr></thead>
                        <tbody>${rows.map((r) => `<tr><td>${h(r[first])} ${badge(r.status)}</td><td class="num">${r.leads ?? '—'}</td><td class="num">${r.sent}</td><td class="num">${r.replied}</td><td class="num">${pct(r.reply_rate)}</td><td class="num">${r.customers ?? '—'}</td><td class="num">${r.bounced}</td></tr>`).join('')}</tbody>
                    </table>
                </div>`;

            const render = (data) => {
                const t = data.total;
                root.innerHTML = `
                    <div class="bar"><span class="title">Last ${data.days} days</span></div>
                    <div class="tiles">
                        <div class="tile"><div class="label">Sent</div><div class="value">${t.sent}</div></div>
                        <div class="tile"><div class="label">Replies</div><div class="value">${t.replied}</div></div>
                        <div class="tile"><div class="label">Reply rate</div><div class="value">${pct(t.reply_rate)}</div></div>
                        <div class="tile"><div class="label">Customers</div><div class="value">${t.customers}</div></div>
                        <div class="tile"><div class="label">Bounced</div><div class="value">${t.bounced}</div></div>
                        <div class="tile"><div class="label">Leads</div><div class="value">${t.leads} <span class="muted" style="font-size:12px;font-weight:400">${t.with_email} with email</span></div></div>
                    </div>
                    ${group('Niche', data.niches, 'name')}
                    ${group('Offer', data.offers, 'name')}
                    <div class="section" style="padding:0">
                        <table>
                            <thead><tr><th>Mailbox</th><th class="num">Today</th><th class="num">Sent</th><th class="num">Replied</th><th class="num">Bounced</th></tr></thead>
                            <tbody>${data.mailboxes.map((m) => `<tr><td>${h(m.address)} ${badge(m.status)}</td><td class="num">${m.sent_today}/${m.limit_today}</td><td class="num">${m.sent}</td><td class="num">${m.replied}</td><td class="num">${m.bounced}</td></tr>`).join('')}</tbody>
                        </table>
                    </div>
                    <div class="actions">${openButton('Open dashboard in Snelreach')}</div>`;
                root.querySelector('.open').addEventListener('click', () => app.openLink(data.url));
            };

            app.onToolResult((params) => {
                if (params.structuredContent) render(params.structuredContent);
            });
        });
        </script>
        @endverbatim
    </x-slot:head>

    <div id="root" class="card"><div class="empty">Loading…</div></div>
</x-mcp::app>
