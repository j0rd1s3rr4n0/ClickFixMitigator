<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Fake Security Scan</title>
    <style>
      :root {
        --bg:#0b111d;
        --panel:#121b2f;
        --accent:#5eead4;
        --accent2:#fbbf24;
        --text:#e2e8f0;
        --muted:#94a3b8;
        --danger:#fb7185;
        --radius:16px;
      }
      * { box-sizing: border-box; }
      body {
        margin:0;
        font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
        background: radial-gradient(900px 420px at 10% -10%, rgba(94,234,212,.15), transparent 60%), var(--bg);
        color: var(--text);
        min-height: 100vh;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:32px;
      }
      .card {
        max-width: 820px;
        width: 100%;
        background: linear-gradient(160deg, rgba(18,27,47,.95), rgba(12,18,32,.95));
        border:1px solid rgba(148,163,184,.2);
        border-radius: var(--radius);
        box-shadow: 0 22px 48px rgba(2,8,20,.45);
        padding: 28px;
      }
      .kicker {
        color: var(--accent);
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: .18em;
      }
      h1 { margin: 10px 0 10px; font-size: 28px; }
      p { color: var(--muted); line-height: 1.5; }
      .grid {
        margin-top: 20px;
        display:grid;
        grid-template-columns: 1.2fr .8fr;
        gap: 16px;
      }
      .panel {
        background: rgba(15,23,42,.6);
        border:1px solid rgba(148,163,184,.18);
        border-radius: 12px;
        padding: 16px;
      }
      .steps { list-style: none; padding:0; margin:0; }
      .steps li {
        padding:8px 0;
        border-bottom:1px dashed rgba(148,163,184,.2);
      }
      .steps li:last-child { border-bottom:none; }
      .code {
        background:#0f172a;
        border:1px solid rgba(148,163,184,.25);
        border-radius: 10px;
        padding: 12px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        color: #f8fafc;
        word-break: break-all;
      }
      .cta {
        margin-top: 18px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding: 12px 18px;
        border-radius: 999px;
        background: linear-gradient(120deg, var(--accent), var(--accent2));
        color:#0b111d;
        font-weight: 700;
        text-decoration:none;
      }
      .warn {
        margin-top: 14px;
        color: var(--danger);
        font-weight: 600;
        font-size: 13px;
      }
      @media (max-width: 780px) {
        .grid { grid-template-columns: 1fr; }
      }
    </style>
  </head>
  <body>
    <main class="card">
      <div class="kicker">Threats found on your device</div>
      <h1>Immediate cleanup required</h1>
      <p>This is a simulated alert for testing. Do not use real security tools from this page.</p>
      <div class="grid">
        <section class="panel">
          <h3>Steps</h3>
          <ul class="steps">
            <li>Press Win + X</li>
<li>Open Terminal</li>
<li>Paste the cleanup command</li>
          </ul>
        </section>
        <section class="panel">
          <h3>Command (demo)</h3>
          <div class="code">cmd /c "echo SAFE-DEMO"</div>
          <div class="warn">Demo content only. Do not execute on real systems.</div>
        </section>
      </div>
      <a class="cta" href="#">Start Cleanup</a>
    </main>
  </body>
</html>
