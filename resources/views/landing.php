<?php

// Static marketing page shown at "/" for logged-out visitors — no
// dynamic data, so nothing here needs View::e() escaping. Its styles are
// intentionally self-contained rather than built on the app's Tailwind
// component classes; this page has its own visual identity separate
// from the dashboard shell.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MyCFO+</title>
    <style>
        :root {
            --bg: #fafaf9;
            --surface: #ffffff;
            --surface-2: #f5f5f4;
            --ink: #1c1917;
            --muted: #78716c;
            --line: #e7e5e4;
            --accent: #c94f32;
            --accent-ink: #ffffff;
            --accent-soft: #fbe4dc;
            --accent-soft-ink: #a53d27;
            --shadow: 0 1px 2px rgba(28,25,23,.04), 0 8px 24px -12px rgba(28,25,23,.12);
            --focus: #2563eb;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0c0a09;
                --surface: #1c1917;
                --surface-2: #221f1d;
                --ink: #f5f5f4;
                --muted: #a8a29e;
                --line: #292524;
                --accent: #e2694b;
                --accent-ink: #1c1917;
                --accent-soft: rgba(226,105,75,.14);
                --accent-soft-ink: #f0a488;
                --shadow: 0 1px 2px rgba(0,0,0,.3), 0 12px 32px -12px rgba(0,0,0,.55);
            }
        }

        :root[data-theme="dark"] {
            --bg: #0c0a09;
            --surface: #1c1917;
            --surface-2: #221f1d;
            --ink: #f5f5f4;
            --muted: #a8a29e;
            --line: #292524;
            --accent: #e2694b;
            --accent-ink: #1c1917;
            --accent-soft: rgba(226,105,75,.14);
            --accent-soft-ink: #f0a488;
            --shadow: 0 1px 2px rgba(0,0,0,.3), 0 12px 32px -12px rgba(0,0,0,.55);
        }

        :root[data-theme="light"] {
            --bg: #fafaf9;
            --surface: #ffffff;
            --surface-2: #f5f5f4;
            --ink: #1c1917;
            --muted: #78716c;
            --line: #e7e5e4;
            --accent: #c94f32;
            --accent-ink: #ffffff;
            --accent-soft: #fbe4dc;
            --accent-soft-ink: #a53d27;
            --shadow: 0 1px 2px rgba(28,25,23,.04), 0 8px 24px -12px rgba(28,25,23,.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
        }

        a { color: inherit; }

        .wrap {
            max-width: 72rem;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* ---------- Header ---------- */

        header.top {
            border-bottom: 1px solid var(--line);
        }

        .top-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.1rem 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: -0.01em;
        }

        .brand-mark {
            width: 2rem;
            height: 2rem;
            border-radius: 0.65rem;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .brand-mark svg { width: 1.1rem; height: 1.1rem; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 0.65rem;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.55rem 1.1rem;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease;
        }

        .btn:focus-visible {
            outline: 2px solid var(--focus);
            outline-offset: 2px;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--accent-ink);
        }

        .btn-primary:hover { transform: translateY(-1px); box-shadow: var(--shadow); }

        .btn-ghost {
            background: transparent;
            border-color: var(--line);
            color: var(--ink);
        }

        .btn-ghost:hover { border-color: var(--muted); }

        /* ---------- Hero ---------- */

        .hero {
            padding: 5rem 0 4.5rem;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 3.5rem;
            align-items: center;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent-soft-ink);
            background: var(--accent-soft);
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            margin-bottom: 1.25rem;
        }

        h1.headline {
            font-size: clamp(2.4rem, 4.6vw, 3.4rem);
            line-height: 1.06;
            letter-spacing: -0.025em;
            font-weight: 700;
            margin: 0 0 1.1rem;
            text-wrap: balance;
        }

        h1.headline em {
            font-style: normal;
            color: var(--accent);
        }

        .hero-sub {
            font-size: 1.1rem;
            color: var(--muted);
            max-width: 34rem;
            margin: 0 0 2rem;
            line-height: 1.65;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .hero-note {
            font-size: 0.85rem;
            color: var(--muted);
        }

        /* ---------- Dashboard preview ---------- */

        .preview {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 1.1rem;
            box-shadow: var(--shadow);
            padding: 1.4rem;
            opacity: 0;
            transform: translateY(10px);
            animation: rise .6s ease .1s forwards;
        }

        @keyframes rise {
            to { opacity: 1; transform: translateY(0); }
        }

        .preview-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 0.9rem;
        }

        .preview-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .preview-delta {
            font-size: 0.8rem;
            font-weight: 600;
            color: #15803d;
        }

        @media (prefers-color-scheme: dark) { .preview-delta { color: #4ade80; } }
        :root[data-theme="dark"] .preview-delta { color: #4ade80; }
        :root[data-theme="light"] .preview-delta { color: #15803d; }

        .net-worth {
            font-size: 2.15rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
            margin: 0 0 0.9rem;
        }

        .spark {
            display: block;
            width: 100%;
            height: 56px;
            margin-bottom: 1.1rem;
        }

        .spark path.line {
            fill: none;
            stroke: var(--accent);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 260;
            stroke-dashoffset: 260;
            animation: draw 1.1s ease .4s forwards;
        }

        .spark path.fill {
            fill: var(--accent);
            opacity: 0.08;
        }

        @keyframes draw { to { stroke-dashoffset: 0; } }

        .accounts {
            border-top: 1px solid var(--line);
            padding-top: 0.9rem;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .account-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.88rem;
        }

        .account-name {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            color: var(--ink);
        }

        .dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            flex-shrink: 0;
        }

        .account-amount {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }

        .budget-block {
            margin-top: 1.1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
        }

        .budget-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            margin-bottom: 0.4rem;
        }

        .budget-row .label { color: var(--muted); }
        .budget-row .value { font-variant-numeric: tabular-nums; font-weight: 600; }

        .track {
            height: 6px;
            border-radius: 999px;
            background: var(--surface-2);
            overflow: hidden;
        }

        .fillbar {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: var(--accent);
            animation: fillbar 1s ease .5s forwards;
        }

        @keyframes fillbar { to { width: 82%; } }

        /* ---------- Features ---------- */

        .features {
            padding: 3.5rem 0 4rem;
            border-top: 1px solid var(--line);
        }

        .features-head {
            max-width: 34rem;
            margin-bottom: 2.75rem;
        }

        .features-head h2 {
            font-size: clamp(1.6rem, 2.6vw, 2rem);
            letter-spacing: -0.02em;
            margin: 0 0 0.6rem;
            text-wrap: balance;
        }

        .features-head p {
            color: var(--muted);
            margin: 0;
            font-size: 1.02rem;
            line-height: 1.6;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.75rem;
        }

        .feature {
            padding-top: 1.5rem;
            border-top: 2px solid var(--line);
        }

        .feature-icon {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.6rem;
            background: var(--accent-soft);
            color: var(--accent-soft-ink);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .feature-icon svg { width: 1.2rem; height: 1.2rem; }

        .feature h3 {
            font-size: 1.05rem;
            margin: 0 0 0.5rem;
            letter-spacing: -0.01em;
        }

        .feature p {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* ---------- Privacy band ---------- */

        .privacy {
            background: var(--surface);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .privacy-inner {
            padding: 3.25rem 0;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .privacy h2 {
            font-size: clamp(1.5rem, 2.4vw, 1.9rem);
            letter-spacing: -0.02em;
            margin: 0 0 0.75rem;
            text-wrap: balance;
        }

        .privacy p {
            color: var(--muted);
            margin: 0;
            font-size: 1rem;
            line-height: 1.65;
            max-width: 32rem;
        }

        .privacy-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
        }

        .privacy-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            font-size: 0.92rem;
        }

        .privacy-list svg {
            width: 1.05rem;
            height: 1.05rem;
            color: var(--accent);
            flex-shrink: 0;
            margin-top: 0.15rem;
        }

        /* ---------- Closing CTA ---------- */

        .closing {
            padding: 4.5rem 0 3.5rem;
            text-align: center;
        }

        .closing h2 {
            font-size: clamp(1.6rem, 3vw, 2.1rem);
            letter-spacing: -0.02em;
            margin: 0 0 0.75rem;
            text-wrap: balance;
        }

        .closing p {
            color: var(--muted);
            margin: 0 0 1.75rem;
        }

        /* ---------- Footer ---------- */

        footer.bottom {
            border-top: 1px solid var(--line);
            padding: 1.75rem 0;
        }

        .bottom-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .bottom-inner a { text-decoration: none; }
        .bottom-inner a:hover { color: var(--ink); }

        @media (max-width: 860px) {
            .hero { grid-template-columns: 1fr; padding-top: 3rem; }
            .privacy-inner { grid-template-columns: 1fr; }
            .feature-grid { grid-template-columns: 1fr; gap: 2rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            .preview, .spark path.line, .fillbar {
                animation: none !important;
            }
            .preview { opacity: 1; transform: none; }
            .spark path.line { stroke-dashoffset: 0; }
            .fillbar { width: 82%; }
        }
    </style>
</head>
<body>
    <header class="top">
        <div class="wrap top-inner">
            <div class="brand">
                <span class="brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 12l3-3 4 4 5-6 6 5" />
                        <path d="M14 6h6v6" />
                    </svg>
                </span>
                MyCFO+
            </div>
            <a class="btn btn-ghost" href="/login">Log in</a>
        </div>
    </header>

    <section>
        <div class="wrap hero">
            <div>
                <span class="eyebrow">Self-hosted &middot; manual entry only</span>
                <h1 class="headline">Your household's money, kept <em>entirely yours.</em></h1>
                <p class="hero-sub">
                    Accounts, budgets, bills, and net worth in one calm dashboard —
                    entered by hand, stored on your own server. No bank logins,
                    no data brokers, nothing syncing anywhere you can't see.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="/login">Log in to your household</a>
                    <span class="hero-note">Invite-only &mdash; ask whoever set up your household.</span>
                </div>
            </div>

            <div class="preview" role="img" aria-label="Preview of the MyCFO+ dashboard showing net worth, account balances, and a budget line">
                <div class="preview-head">
                    <span class="preview-label">Net worth</span>
                    <span class="preview-delta">+$2,140 this month</span>
                </div>
                <div class="net-worth">$184,320</div>
                <svg class="spark" viewBox="0 0 300 56" preserveAspectRatio="none" aria-hidden="true">
                    <path class="fill" d="M0,44 L25,40 L50,42 L75,34 L100,36 L125,26 L150,29 L175,20 L200,22 L225,14 L250,16 L275,8 L300,10 L300,56 L0,56 Z" />
                    <path class="line" d="M0,44 L25,40 L50,42 L75,34 L100,36 L125,26 L150,29 L175,20 L200,22 L225,14 L250,16 L275,8 L300,10" />
                </svg>
                <div class="accounts">
                    <div class="account-row">
                        <span class="account-name"><span class="dot" style="background:#2563eb"></span>Checking</span>
                        <span class="account-amount">$4,820</span>
                    </div>
                    <div class="account-row">
                        <span class="account-name"><span class="dot" style="background:#15803d"></span>Savings</span>
                        <span class="account-amount">$32,150</span>
                    </div>
                    <div class="account-row">
                        <span class="account-name"><span class="dot" style="background:var(--accent)"></span>Mortgage</span>
                        <span class="account-amount">&minus;$212,400</span>
                    </div>
                </div>
                <div class="budget-block">
                    <div class="budget-row">
                        <span class="label">Groceries this month</span>
                        <span class="value">$412 / $500</span>
                    </div>
                    <div class="track"><div class="fillbar"></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="wrap">
            <div class="features-head">
                <h2>Built around one household, not a userbase.</h2>
                <p>No tiers, no upsells, no growth metrics to feed. Just the parts of a finance app that are actually worth having.</p>
            </div>
            <div class="feature-grid">
                <div class="feature">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z" />
                        </svg>
                    </div>
                    <h3>Manual by design</h3>
                    <p>Every balance and transaction is entered by a person, not pulled from a bank. Nothing to revoke, nothing to leak.</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <path d="M3 15h18M9 3v18" />
                        </svg>
                    </div>
                    <h3>Budgets that hold up</h3>
                    <p>Plan a category once, see it carry forward automatically, and know exactly what's left to spend before you spend it.</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 3v18h18" />
                            <path d="M7 15l4-5 4 3 5-7" />
                        </svg>
                    </div>
                    <h3>The whole picture</h3>
                    <p>Every account, asset, and debt rolled into one net worth line, tracked over time &mdash; not just this month's snapshot.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="privacy">
        <div class="wrap privacy-inner">
            <div>
                <h2>It runs on your server. It stays on your server.</h2>
                <p>This isn't a hosted product with a privacy policy to trust &mdash; it's software you run yourself, so the answer to "where does my data go" is always "nowhere."</p>
            </div>
            <ul class="privacy-list">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
                    No bank credentials or account-aggregation services, ever
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
                    No analytics, trackers, or third-party scripts
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
                    Every household member has their own login and role
                </li>
            </ul>
        </div>
    </section>

    <section class="closing">
        <div class="wrap">
            <h2>Ready to see where things stand?</h2>
            <p>Log in to pick up right where your household left off.</p>
            <a class="btn btn-primary" href="/login">Log in to your household</a>
        </div>
    </section>

    <footer class="bottom">
        <div class="wrap bottom-inner">
            <span>MyCFO+ &mdash; a self-hosted household finance dashboard.</span>
            <a href="/login">Log in &rarr;</a>
        </div>
    </footer>
</body>
</html>
