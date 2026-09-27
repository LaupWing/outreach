{{-- Snelreach look for cards inside Claude: the app's tokens, light and dark, raised surfaces. --}}
<style>
    :root {
        color-scheme: light dark;
        --background: oklch(1 0 0); --foreground: oklch(0.145 0 0);
        --card: oklch(1 0 0); --muted: oklch(0.97 0 0); --muted-foreground: oklch(0.556 0 0);
        --accent: oklch(0.97 0 0); --border: oklch(0.922 0 0); --primary: oklch(0.541 0.281 293.009);
        --raised-border: oklch(0.91 0 0);
        --raised-shadow: inset 0 1px 0 oklch(1 0 0), 0 1px 2px oklch(0 0 0 / 0.06);
        --sky: oklch(0.55 0.15 240); --amber: oklch(0.6 0.15 70); --green: oklch(0.55 0.17 150); --red: oklch(0.58 0.2 25); --violet: oklch(0.55 0.25 293);
    }
    :root[data-theme="dark"], :root:not([data-theme="light"]) {
        --background: oklch(0.145 0 0); --foreground: oklch(0.985 0 0);
        --card: oklch(0.17 0 0); --muted: oklch(0.205 0 0); --muted-foreground: oklch(0.708 0 0);
        --accent: oklch(0.25 0 0); --border: oklch(0.269 0 0); --primary: oklch(0.606 0.25 292.717);
        --raised-border: oklch(0.3 0 0);
        --raised-shadow: inset 0 1px 0 oklch(0.37 0 0), 0 0 0 1px oklch(0.1 0 0);
        --sky: oklch(0.75 0.13 240); --amber: oklch(0.8 0.14 80); --green: oklch(0.75 0.17 150); --red: oklch(0.7 0.19 25); --violet: oklch(0.75 0.16 293);
    }
    :root[data-theme="light"] {
        --background: oklch(1 0 0); --foreground: oklch(0.145 0 0);
        --card: oklch(1 0 0); --muted: oklch(0.97 0 0); --muted-foreground: oklch(0.556 0 0);
        --accent: oklch(0.97 0 0); --border: oklch(0.922 0 0); --primary: oklch(0.541 0.281 293.009);
        --raised-border: oklch(0.91 0 0);
        --raised-shadow: inset 0 1px 0 oklch(1 0 0), 0 1px 2px oklch(0 0 0 / 0.06);
        --sky: oklch(0.55 0.15 240); --amber: oklch(0.6 0.15 70); --green: oklch(0.55 0.17 150); --red: oklch(0.58 0.2 25); --violet: oklch(0.55 0.25 293);
    }
    * { box-sizing: border-box; }
    body { margin: 0; font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, sans-serif; color: var(--foreground); background: transparent; -webkit-font-smoothing: antialiased; }
    .card { border: 1px solid var(--raised-border); border-radius: 12px; background: var(--card); box-shadow: var(--raised-shadow); overflow: hidden; }
    /* The brand on every card: the stack colours as a hairline on top, the envelope stack before each title. */
    .card::before { content: ''; display: block; height: 2px; background: linear-gradient(90deg, #38bdf8, #a78bfa, #f472b6); }
    .bar::before { content: ''; flex: none; width: 18px; height: 18px; background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='-9' y='-6' width='18' height='12' rx='1.8' transform='translate(12 16.2) scale(1 0.62) rotate(45)' fill='%2338bdf8'/%3E%3Crect x='-9' y='-6' width='18' height='12' rx='1.8' transform='translate(12 12) scale(1 0.62) rotate(45)' fill='%23a78bfa'/%3E%3Cg transform='translate(12 7.8) scale(1 0.62) rotate(45)'%3E%3Crect x='-9' y='-6' width='18' height='12' rx='1.8' fill='%23f472b6'/%3E%3Cpath d='M-8.4 -5.4 L0 0.6 L8.4 -5.4 Z' fill='%23db2777' stroke='%23db2777' stroke-width='1' stroke-linejoin='round'/%3E%3Cpath d='M-8.1 -5.1 L0 0.6 L8.1 -5.1' fill='none' stroke='%23fce7f3' stroke-width='0.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/g%3E%3C/svg%3E") center / contain no-repeat; }
    .bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 12px; color: var(--muted-foreground); }
    .bar .title { color: var(--foreground); font-weight: 600; font-size: 14px; }
    .bar .spacer { flex: 1; }
    .badge { display: inline-flex; align-items: center; padding: 1px 7px; border-radius: 6px; font-size: 11px; font-weight: 500; white-space: nowrap; border: 1px solid transparent; background: var(--accent); color: var(--foreground); }
    .badge.new { background: var(--accent); }
    .badge.emailed, .badge.sent, .badge.queued.sky { color: var(--sky); background: color-mix(in oklch, var(--sky) 12%, transparent); border-color: color-mix(in oklch, var(--sky) 25%, transparent); }
    .badge.followed_up, .badge.preview, .badge.queued, .badge.testing { color: var(--violet); background: color-mix(in oklch, var(--violet) 12%, transparent); border-color: color-mix(in oklch, var(--violet) 25%, transparent); }
    .badge.replied, .badge.idea { color: var(--amber); background: color-mix(in oklch, var(--amber) 12%, transparent); border-color: color-mix(in oklch, var(--amber) 25%, transparent); }
    .badge.customer, .badge.proven, .badge.active { color: var(--green); background: color-mix(in oklch, var(--green) 12%, transparent); border-color: color-mix(in oklch, var(--green) 25%, transparent); }
    .badge.undeliverable, .badge.bounced, .badge.failed, .badge.dropped { color: var(--red); background: color-mix(in oklch, var(--red) 12%, transparent); border-color: color-mix(in oklch, var(--red) 25%, transparent); }
    .badge.no, .badge.draft, .badge.stopped, .badge.paused { color: var(--muted-foreground); background: transparent; border-color: var(--border); }
    .rows { display: grid; grid-template-columns: 88px minmax(0, 1fr); row-gap: 6px; column-gap: 10px; padding: 12px 14px; font-size: 13px; margin: 0; }
    .rows dt { color: var(--muted-foreground); }
    .rows dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
    .section { padding: 10px 14px; border-top: 1px solid var(--border); }
    .section h3 { margin: 0 0 6px; font-size: 11px; font-weight: 500; letter-spacing: .04em; text-transform: uppercase; color: var(--muted-foreground); }
    .muted { color: var(--muted-foreground); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
    .actions { display: flex; gap: 8px; padding: 12px 14px; border-top: 1px solid var(--border); }
    .btn { cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; padding: 7px 12px; font: inherit; font-size: 13px; font-weight: 500; border: 1px solid var(--raised-border); background: var(--background); color: var(--foreground); box-shadow: var(--raised-shadow); }
    .btn:hover { background: var(--accent); }
    .btn.primary { background: var(--primary); border-color: var(--primary); color: white; box-shadow: none; }
    .btn:disabled { opacity: .5; cursor: default; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { text-align: left; font-weight: 500; font-size: 12px; color: var(--muted-foreground); padding: 8px 14px; border-bottom: 1px solid var(--border); white-space: nowrap; }
    td { padding: 8px 14px; border-bottom: 1px solid var(--border); vertical-align: top; }
    tr:last-child td { border-bottom: 0; }
    tr.row { cursor: pointer; }
    tr.row:hover td { background: var(--accent); }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px; padding: 12px 14px; }
    .tile { border: 1px solid var(--raised-border); border-radius: 10px; padding: 10px 12px; box-shadow: var(--raised-shadow); background: var(--background); }
    .tile .label { font-size: 11px; color: var(--muted-foreground); }
    .tile .value { font-size: 20px; font-weight: 600; letter-spacing: -.01em; }
    /* The headline number drifts through the brand gradient, like on the dashboard. */
    .tile.lava .value { background: linear-gradient(100deg, #38bdf8, #8b5cf6, #d946ef, #8b5cf6, #38bdf8) 0 50% / 300% 100%; -webkit-background-clip: text; background-clip: text; color: transparent; animation: lava-drift 14s ease-in-out infinite; }
    @keyframes lava-drift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
    @media (prefers-reduced-motion: reduce) { .tile.lava .value { animation: none; } }
    .empty { padding: 20px 14px; color: var(--muted-foreground); font-size: 13px; }
    .tag { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 1px 5px; border-radius: 5px; background: color-mix(in oklch, var(--violet) 12%, transparent); color: var(--violet); }
    .tag.missing { background: color-mix(in oklch, var(--red) 12%, transparent); color: var(--red); }
    pre.body { margin: 0; padding: 8px 14px 14px; white-space: pre-wrap; font: inherit; }
    .hint { padding: 0 14px 12px; font-size: 12px; color: var(--muted-foreground); }
</style>
<script>
    // Shared helpers for every card: escaping, badges, and opening the app.
    window.h = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    window.badge = (status) => status ? `<span class="badge ${h(status)}">${h(String(status).replace(/_/g, ' '))}</span>` : '';
    window.openButton = (label = 'Open in Snelreach') => `<button class="btn open" type="button">${h(label)}</button>`;
</script>
