<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&family=Public+Sans:wght@400;600;700&family=DM+Sans:wght@400;500;700&family=Nunito+Sans:wght@400;600;700&display=swap');
    :root{
      --bg:#05070f;
      --bg-layer:#0a1222;
      --bg-soft:#141f35;
      --card:#0d1729cc;
      --line:#2d3f5f;
      --line-soft:#22334f;
      --txt:#e9f4ff;
      --mut:#97afca;
      --brand:#6ff6ff;
      --brand-2:#7fffbf;
      --accent:#9aa4ff;
      --warn:#ffd070;
      --danger:#ff6f92;
      --ok:#59f2b5;
      --shadow:0 26px 70px rgba(2,8,18,.55);
      --radius:20px;
      --radius-sm:12px;
      --glow:0 0 24px rgba(111,246,255,.18);
      --font-main:'Plus Jakarta Sans',sans-serif;
    }
    body.theme-teal{
      --brand:#56d6c9;
      --brand-2:#4fe38d;
      --bg:#051211;
      --bg-layer:#0a2221;
      --bg-soft:#113732;
      --line:#2f6f64;
      --line-soft:#25564f;
    }
    body.theme-sunset{
      --brand:#ffb75e;
      --brand-2:#ff8f70;
      --bg:#170b08;
      --bg-layer:#2a1410;
      --bg-soft:#3c1d15;
      --line:#89543c;
      --line-soft:#6f432f;
    }
    body.theme-mono{
      --brand:#b9c2cf;
      --brand-2:#dee4ec;
      --bg:#07090d;
      --bg-layer:#11161f;
      --bg-soft:#1a212d;
      --line:#4a5568;
      --line-soft:#394353;
    }
    *{box-sizing:border-box}
    html,body{height:100%;scroll-behavior:smooth}
    html{-webkit-text-size-adjust:100%}
    body{
      margin:0;
      color:var(--txt);
      font-family:var(--font-main);
      background:
        radial-gradient(820px 420px at 12% -8%,rgba(111,246,255,.28),transparent 65%),
        radial-gradient(880px 420px at 92% -12%,rgba(127,255,191,.18),transparent 60%),
        radial-gradient(700px 300px at 60% 108%,rgba(154,164,255,.12),transparent 62%),
        linear-gradient(120deg,rgba(255,255,255,.04) 0 1px,transparent 1px 22px),
        linear-gradient(145deg,var(--bg),var(--bg-layer) 50%,#0b1628 100%);
      min-height:100vh;
    }
    body.ui-light{
      --bg:#f3f7fb;
      --bg-layer:#e8eef5;
      --bg-soft:#ffffff;
      --card:#ffffff;
      --line:#c8d4e3;
      --line-soft:#d6e0ec;
      --txt:#0c1726;
      --mut:#3e4a5d;
      --brand:#1a6bff;
      --brand-2:#06c47a;
      --accent:#2b5cff;
      --warn:#b97300;
      --danger:#b5283f;
      --ok:#148a64;
      --shadow:0 18px 40px rgba(7,12,20,.14);
      --glow:none;
    }
    body.ui-light .card,
    body.ui-light .panel,
    body.ui-light table{
      background:rgba(255,255,255,.92);
      border-color:rgba(140,160,190,.5);
    }
    body.ui-light .top,
    body.ui-light .nav,
    body.ui-light .intel-workspace-nav{
      background:rgba(255,255,255,.85);
      border-color:rgba(140,160,190,.45);
    }
    body.ui-contrast{
      --line:#5c7aa8;
      --line-soft:#476a97;
      --mut:#c8ddf4;
    }
    body.ui-compact .card{padding:8px}
    body.ui-compact .grid,
    body.ui-compact .row,
    body.ui-compact .viz-grid,
    body.ui-compact .intel-grid,
    body.ui-compact .intel-side,
    body.ui-compact .intel-workbench-grid{
      gap:6px;
    }
    body.ui-compact .intel-workspace-nav{gap:6px}
    body.ui-reduced-motion *,
    body.ui-reduced-motion *::before,
    body.ui-reduced-motion *::after{
      animation:none !important;
      transition:none !important;
      scroll-behavior:auto !important;
    }
    body.ui-no-decor{
      background:linear-gradient(160deg,var(--bg),var(--bg-layer));
    }
    body.ui-no-decor .card,
    body.ui-no-decor .panel,
    body.ui-no-decor .nav,
    body.ui-no-decor .top{
      box-shadow:none !important;
    }
    body.ui-accent-blue{--accent:#5b8bff;--brand:#66b5ff}
    body.ui-accent-green{--accent:#37e3a7;--brand:#37e3a7}
    body.ui-accent-purple{--accent:#a685ff;--brand:#b59bff}
    body.ui-accent-amber{--accent:#ffb454;--brand:#ffb454}
    body.ui-accent-red{--accent:#ff7a86;--brand:#ff7a86}
    body.ui-accent-cyan{--accent:#5fd8ff;--brand:#5fd8ff}
    body.ui-font-jakarta{--font-main:'Plus Jakarta Sans',sans-serif}
    body.ui-font-public{--font-main:'Public Sans',sans-serif}
    body.ui-font-dm{--font-main:'DM Sans',sans-serif}
    body.ui-font-nunito{--font-main:'Nunito Sans',sans-serif}
    body.ui-font-sora{--font-main:'Sora',sans-serif}
    body.ui-font-arial{--font-main:Arial,'Helvetica Neue',Helvetica,sans-serif}
    body.ui-font-helvetica{--font-main:'Helvetica Neue',Helvetica,Arial,sans-serif}
    body.ui-font-ubuntu{--font-main:Ubuntu,'Segoe UI',Arial,sans-serif}
    body.ui-font-roboto{--font-main:Roboto,'Segoe UI',Arial,sans-serif}
    body.template-corona{
      background:#0f1015;
      color:#f3f3f3;
    }
    body.template-corona .wrap{
      padding:0;
      max-width:100%;
      width:100%;
    }
    body.template-corona .content-wrapper{
      padding:24px 22px;
    }
    body.template-corona .workspace{
      gap:16px;
    }
    body.template-corona .card,
    body.template-corona .panel,
    body.template-corona .side-card,
    body.template-corona .api-result,
    body.template-corona .intel-map-shell,
    body.template-corona .intel-canvas-dock,
    body.template-corona .intel-accordion,
    body.template-corona table{
      background:#191c24;
      border:1px solid #2c2e33;
      box-shadow:none;
      border-radius:6px;
    }
    body.template-corona .kpi{
      background:#1c1f27;
      border:1px solid #2c2e33;
      box-shadow:none;
    }
    body.template-corona .kpi b{
      color:#ffffff;
    }
    body.template-corona .kpi .mut{
      color:#9ca3af;
    }
    body.template-corona .kpi-icon{
      background:#2a2d35;
      border-color:#3a3d45;
      color:#34d399;
    }
    body.template-corona .module-chip,
    body.template-corona .status-chip{
      background:#1c1f27;
      border:1px solid #2c2e33;
      color:#d1d5db;
    }
    body.template-corona .mut,
    body.template-corona .mut-mini{
      color:#9ca3af;
    }
    body.template-corona .mono,
    body.template-corona code{
      background:#111319;
      border-color:#2c2e33;
      color:#e5e7eb;
    }
    body.template-corona pre{
      background:#111319;
      border-color:#2c2e33;
      color:#e5e7eb;
      box-shadow:none;
    }
    body.template-corona input,
    body.template-corona select,
    body.template-corona textarea{
      background:#111319;
      border:1px solid #2c2e33;
      color:#f3f4f6;
    }
    body.template-corona input::placeholder,
    body.template-corona textarea::placeholder{
      color:#6b7280;
    }
    body.template-corona .btn,
    body.template-corona button.btn,
    body.template-corona a.btn{
      border-radius:6px;
      box-shadow:none;
    }
    body.template-corona .display-settings-panel{
      background:#191c24;
      border:1px solid #2c2e33;
      color:#e5e7eb;
    }
    body.template-corona .display-toggle{
      background:#111319;
      border-color:#2c2e33;
    }
    body.template-corona .display-toggle .label{
      color:#cbd5f5;
    }
    body.template-corona .switch-track{
      border-color:#2c2e33;
      background:#0f1015;
    }
    body.template-corona .switch input:checked + .switch-track{
      background:#22c55e;
      border-color:#22c55e;
    }
    body.template-corona .switch input:checked + .switch-track .switch-thumb{
      background:#0f172a;
    }
    body.template-corona .preset-btn,
    body.template-corona .font-btn{
      background:#111319;
      border-color:#2c2e33;
      color:#e5e7eb;
    }
    body.template-corona .font-btn span{
      color:#9ca3af;
    }
    body.template-corona .profile-avatar{
      background:#2b2f36;
      color:#e5e7eb;
      border:1px solid #3b3f48;
      display:grid;
      place-items:center;
      font-weight:700;
    }
    body.template-corona .row{
      display:flex;
      flex-wrap:wrap;
      margin-right:-0.75rem;
      margin-left:-0.75rem;
      gap:0;
    }
    body.template-corona .row > *{
      padding-right:0.75rem;
      padding-left:0.75rem;
    }
    body.template-corona .hero,
    body.template-corona .grid,
    body.template-corona .split,
    body.template-corona .viz-grid,
    body.template-corona .intel-grid,
    body.template-corona .event-workbench,
    body.template-corona .event-columns,
    body.template-corona .intel-layout,
    body.template-corona .intel-workbench-grid{
      display:flex;
      flex-wrap:wrap;
      gap:24px;
    }
    body.template-corona .hero > *,
    body.template-corona .grid > *,
    body.template-corona .split > *,
    body.template-corona .viz-grid > *,
    body.template-corona .intel-grid > *,
    body.template-corona .event-workbench > *,
    body.template-corona .event-columns > *,
    body.template-corona .intel-layout > *,
    body.template-corona .intel-workbench-grid > *{
      flex:1 1 320px;
      min-width:0;
    }
    body.template-corona .logo-text{
      color:#ffffff;
      font:700 1rem 'Sora',sans-serif;
      letter-spacing:.04em;
    }
    body.template-corona .logo-mini{
      color:#ffffff;
      font:800 .9rem 'Sora',sans-serif;
      letter-spacing:.1em;
    }
    body.template-corona .navbar{
      background:#191c24;
      border-bottom:1px solid #2c2e33;
    }
    body.template-corona .navbar .nav-link,
    body.template-corona .navbar .navbar-profile-name{
      color:#e5e7eb !important;
    }
    body.template-corona .navbar .navbar-nav .nav-item{
      list-style:none;
    }
    body.template-corona .navbar-nav{
      list-style:none;
      margin:0;
      padding:0;
    }
    body.template-corona .sidebar .nav,
    body.template-corona .sidebar .nav .nav-item{
      list-style:none;
      padding-left:0;
    }
    body.template-corona .sidebar .nav{
      margin:0;
    }
    body.template-corona .status-chip{
      background:#111319;
      border:1px solid #2c2e33;
      color:#e5e7eb;
      padding:6px 10px;
      border-radius:6px;
      font-size:.75rem;
    }
    body.template-corona .navbar .btn{
      border-radius:6px;
    }
    body.template-corona .sidebar.sidebar-offcanvas{
      background:#191c24;
      border-right:1px solid #2c2e33;
    }
    body.template-corona .sidebar .nav{
      padding-left:0;
      margin:0;
    }
    body.template-corona .sidebar .nav .nav-item{
      list-style:none;
    }
    body.template-corona .navbar-menu-wrapper{
      background:#191c24;
      border-bottom:1px solid #2c2e33;
      padding-left:1rem;
      padding-right:1rem;
    }
    body.template-corona .navbar-menu-wrapper .nav-link{
      margin:0;
    }
    body.template-corona .navbar-menu-wrapper .navbar-nav{
      margin:0;
      padding:0;
      list-style:none;
    }
    body.template-corona h1,
    body.template-corona h2,
    body.template-corona h3,
    body.template-corona h4{
      color:#f3f4f6;
    }
    body.template-corona .hero{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
      gap:24px;
      margin-bottom:24px;
    }
    body.template-corona .grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
      gap:24px;
      margin-bottom:24px;
    }
    body.template-corona .split,
    body.template-corona .viz-grid,
    body.template-corona .intel-grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
      gap:24px;
      margin-bottom:24px;
    }
    body.template-corona .card{
      padding:18px;
    }
    body.template-corona .kpi{
      min-height:120px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }
    body.template-corona .side-column{
      gap:16px;
    }
    body.template-corona .side-card{
      padding:16px;
    }
    body.template-corona table{
      width:100%;
      border-collapse:collapse;
    }
    body.template-corona th,
    body.template-corona td{
      padding:10px 12px;
      border-bottom:1px solid #2c2e33;
    }
    a{
      color:#b7f4ff;
      text-decoration:none;
      transition:color .2s ease,opacity .2s ease;
    }
    a:hover{color:#e9ffff;opacity:.95}
    code{
      font-family:'JetBrains Mono',monospace;
      background:rgba(16,33,55,.7);
      border:1px solid rgba(68,104,150,.45);
      border-radius:8px;
      padding:1px 6px;
    }
    pre{
      background:rgba(9,18,34,.8);
      border:1px solid rgba(62,96,142,.45);
      border-radius:12px;
      padding:10px;
      color:#d7e8ff;
      box-shadow:0 12px 28px rgba(3,10,22,.28) inset;
    }
    input::placeholder,textarea::placeholder{color:rgba(167,189,214,.75)}
    ::selection{background:rgba(111,246,255,.28);color:#031321}
    body.nav-open-mobile{
      overflow:hidden;
    }
    :root{
      --sticky-header-offset:136px;
    }
    .wrap{
      width:100%;
      max-width:none;
      margin:auto;
      padding:
        calc(10px + env(safe-area-inset-top))
        clamp(8px,1.6vw,18px)
        calc(18px + env(safe-area-inset-bottom))
        clamp(8px,1.6vw,18px);
    }
    .section{
      padding:18px 0 26px;
    }
    .workspace{
      display:grid;
      grid-template-columns:minmax(0,1fr) clamp(280px,22vw,380px);
      gap:10px;
      align-items:start;
      min-height:calc(100vh - var(--sticky-header-offset));
    }
    .main-column{min-width:0;min-height:calc(100vh - var(--sticky-header-offset))}
    .side-column{
      display:flex;
      flex-direction:column;
      gap:8px;
      position:sticky;
      top:var(--sticky-header-offset);
      align-self:start;
      max-height:calc(100vh - var(--sticky-header-offset) - 12px);
      overflow:auto;
      min-width:0;
    }
    .dashboard-shell-row{
      display:grid !important;
      grid-template-columns:minmax(0,1.45fr) clamp(320px, 26vw, 520px);
      gap:18px;
      align-items:start;
      margin-left:0 !important;
      margin-right:0 !important;
      width:100%;
    }
    .dashboard-shell-row > *{
      width:auto !important;
      max-width:none !important;
      padding-left:0 !important;
      padding-right:0 !important;
      min-width:0;
    }
    .dashboard-main-col,
    .dashboard-side-col{
      flex:none !important;
      margin-bottom:0 !important;
      min-width:0;
    }
    .dashboard-side-col{
      width:100%;
      max-width:none;
    }
    .side-card-grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
      gap:12px;
    }
    .side-card-grid--ops{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:12px;
      align-items:start;
    }
    .side-card-grid--ops > *{
      min-width:0;
    }
    .side-card-grid .side-card{height:auto;align-self:start}
    .side-card-grid .announcement-aside{grid-column:1/-1}
    .side-card{padding:10px}
    .side-card h3{margin:0 0 8px;font-size:.86rem}
    .side-metrics{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:7px;
    }
    .side-metric{
      border:1px solid #3b608a;
      border-radius:12px;
      background:linear-gradient(150deg,#102843,#0c2038);
      padding:8px;
    }
    .side-metric b{display:block;font-size:1.05rem;line-height:1.1}
    .side-metric span{font:.64rem 'JetBrains Mono',monospace;color:#a9c9e7}
    .mini-list{margin:0;padding:0;list-style:none}
    .mini-list li{
      display:flex;
      justify-content:space-between;
      gap:8px;
      padding:6px 0;
      border-bottom:1px solid rgba(58,96,132,.35);
      font-size:.8rem;
    }
    .mini-list li:last-child{border-bottom:none}
    .mini-links{display:grid;gap:6px}
    .mini-links a{
      text-decoration:none;
      color:#e3f2ff;
      padding:8px 9px;
      border-radius:11px;
      border:1px solid #355f86;
      background:linear-gradient(135deg,#112742,#0f2238);
      font:600 .72rem 'JetBrains Mono',monospace;
      transition:.22s ease;
    }
    .mini-links a:hover{border-color:#75dcff;background:#183857;transform:translateY(-1px)}
    .announcement-aside details{
      border:1px solid #325978;
      border-radius:12px;
      background:linear-gradient(150deg,#0f2841,#0b2136);
      padding:8px;
    }
    .announcement-aside summary{
      list-style:none;
      cursor:pointer;
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:10px;
    }
    .announcement-aside summary::-webkit-details-marker{display:none}
    .announcement-aside summary::after{
      content:'+';
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:18px;
      height:18px;
      border-radius:999px;
      border:1px solid #4d7498;
      color:#d9edff;
      background:#153553;
      flex-shrink:0;
      font:700 .85rem 'JetBrains Mono',monospace;
      line-height:1;
    }
    .announcement-aside details[open] > summary::after{content:'-'}
    .announcement-aside summary b{font:700 .78rem 'Sora',sans-serif}
    .announcement-aside summary span{display:block;color:#9fc0df;font:.67rem 'JetBrains Mono',monospace}
    .announcement-focus{
      margin:8px 0 0;
      padding:6px 8px;
      border-radius:9px;
      border:1px solid #365f85;
      background:#102740;
      color:#bdd7f0;
      font-size:.76rem;
      line-height:1.35;
    }
    .announcement-list{list-style:none;margin:8px 0 0;padding:0;display:grid;gap:7px}
    .announcement-item{
      border:1px solid #31577b;
      border-radius:10px;
      background:#0d2338;
      padding:7px 8px;
    }
    .announcement-item-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      margin-bottom:5px;
    }
    .announcement-severity{
      display:inline-flex;
      align-items:center;
      border-radius:999px;
      border:1px solid #4e7294;
      padding:2px 7px;
      font:.62rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.25px;
    }
    .announcement-severity.info{border-color:#2e7aa5;background:#113953;color:#bdeaff}
    .announcement-severity.warning{border-color:#8f7a41;background:#3a3016;color:#ffe5ab}
    .announcement-severity.critical{border-color:#9f4d5e;background:#491b27;color:#ffd5dd}
    .announcement-meta{font:.64rem 'JetBrains Mono',monospace;color:#a9c7e2}
    .announcement-item b{display:block;font-size:.78rem;line-height:1.25}
    .announcement-item p{margin:4px 0 0;color:#c3dbf2;font-size:.76rem;line-height:1.35}
    .ad-slot{
      border:1px dashed #4ea9db;
      border-radius:10px;
      background:#0b1d2f;
      min-height:120px;
      display:grid;
      place-items:center;
      overflow:hidden;
    }
    .internal-ads-stack{
      display:grid;
      gap:8px;
    }
    .internal-ad-card{
      border:1px solid #355f86;
      border-radius:14px;
      background:linear-gradient(155deg,#102840,#0d2136);
      padding:10px;
      display:grid;
      gap:8px;
    }
    .internal-ad-card.cyan{border-color:#2f82a8;background:linear-gradient(155deg,#0f2941,#0b2235)}
    .internal-ad-card.lime{border-color:#4e8d5c;background:linear-gradient(155deg,#132d2b,#0f2230)}
    .internal-ad-card.amber{border-color:#9b6d35;background:linear-gradient(155deg,#312412,#14263b)}
    .internal-ad-card.fuchsia{border-color:#8a4db6;background:linear-gradient(155deg,#2d1836,#0f2237)}
    .internal-ad-kicker{
      font:.64rem 'JetBrains Mono',monospace;
      color:#b9d8f0;
      text-transform:uppercase;
      letter-spacing:.08em;
    }
    .internal-ad-card b{
      display:block;
      font:700 .9rem 'Sora',sans-serif;
      color:#f3fbff;
      line-height:1.25;
    }
    .internal-ad-card p{
      margin:0;
      color:#c7def2;
      font-size:.78rem;
      line-height:1.42;
    }
    .internal-ad-card .btn{
      width:auto;
    }
    .support-note{margin:0 0 8px;color:#bcd6ef;font-size:.8rem;line-height:1.4}
    .top,.card{
      background:
        radial-gradient(circle at top right,rgba(111,246,255,.08),transparent 40%),
        linear-gradient(165deg,rgba(13,26,44,.92),rgba(9,19,34,.9));
      border:1px solid rgba(84,126,178,.48);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      backdrop-filter:blur(14px);
    }
    .top{
      display:grid;
      gap:12px;
      padding:14px 16px;
      margin-bottom:8px;
      position:sticky;
      top:10px;
      z-index:28;
      background:
        radial-gradient(circle at top right,rgba(127,255,191,.18),transparent 34%),
        linear-gradient(170deg,rgba(13,30,52,.95),rgba(7,15,28,.95));
      border-color:rgba(104,155,208,.58);
      box-shadow:0 22px 50px rgba(3,10,22,.5),var(--glow);
    }
    .app-header-main{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:16px;
      flex-wrap:wrap;
    }
    .nav-toggle{
      display:none;
      align-items:center;
      justify-content:center;
      gap:8px;
      min-height:42px;
      padding:9px 12px;
      border-radius:12px;
      border:1px solid #3f6690;
      background:linear-gradient(140deg,#132d49,#10253d);
      color:#e6f4ff;
      font:700 .74rem 'JetBrains Mono',monospace;
      letter-spacing:.08em;
      text-transform:uppercase;
      cursor:pointer;
      transition:.22s ease;
      box-shadow:0 10px 22px rgba(8,26,44,.22);
    }
    .nav-toggle:hover{
      border-color:#79dbff;
      transform:translateY(-1px);
      box-shadow:0 12px 24px rgba(16,78,129,.32);
    }
    .nav-toggle-lines{
      display:inline-grid;
      gap:3px;
      width:15px;
    }
    .nav-toggle-lines span{
      display:block;
      width:15px;
      height:2px;
      border-radius:999px;
      background:currentColor;
    }
    .app-header-brand{
      display:grid;
      gap:6px;
      min-width:280px;
      flex:1 1 420px;
    }
    .app-header-title{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
    }
    .app-header-title b{font:700 1.06rem 'Sora',sans-serif;letter-spacing:.2px}
    .app-header-subline{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:center;
      color:#b9d4ec;
      font:.78rem 'JetBrains Mono',monospace;
    }
    .app-header-subline .sep{opacity:.55}
    .mono,code{font-family:'JetBrains Mono',monospace}
    .top .mut{font-size:.79rem}
    .module-chip{
      display:inline-flex;
      align-items:center;
      gap:7px;
      padding:5px 10px;
      border-radius:999px;
      border:1px solid #79dbff55;
      background:linear-gradient(135deg,#173f62,#11324f);
      font:600 .7rem 'JetBrains Mono',monospace;
      color:#e0f5ff;
    }
    .top-status{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      justify-content:flex-end;
      align-items:flex-start;
      flex:1 1 500px;
    }
    .status-chip{
      padding:5px 10px;
      border-radius:999px;
      border:1px solid #4a76a2;
      background:linear-gradient(135deg,#152f4a,#11263b);
      font:.7rem 'JetBrains Mono',monospace;
      color:#e5f2ff;
    }
    .status-chip a{color:#c9f2ff;text-decoration:none}
    .app-header-navrow{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      padding-top:10px;
      border-top:1px solid rgba(96,137,177,.28);
    }
    .header-nav{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      flex:1 1 720px;
      min-width:0;
    }
    .header-nav a,
    .nav-actions{
      display:flex;
      align-items:center;
      gap:8px;
      flex-wrap:wrap;
    }
    .nav-actions{
      justify-content:flex-end;
      flex:0 1 auto;
    }
    .nav-actions form{margin:0}
    .header-nav a,
    .nav-actions .nav-btn{
      text-decoration:none;
      color:#dff1ff;
      padding:8px 11px;
      border-radius:12px;
      border:1px solid #3f6690;
      background:linear-gradient(140deg,#132d49,#10253d);
      font:600 .76rem 'JetBrains Mono',monospace;
      letter-spacing:.1px;
      transition:.22s ease;
      display:inline-flex;
      align-items:center;
      width:auto;
      cursor:pointer;
    }
    .header-nav a:hover,
    .nav-actions .nav-btn:hover{
      border-color:#79dbff;
      transform:translateY(-1px);
      box-shadow:0 10px 20px rgba(16,78,129,.3);
    }
    .header-nav a.active,
    .nav-actions .nav-btn.active{
      color:#06182a;
      border-color:transparent;
      background:linear-gradient(135deg,var(--brand),var(--brand-2));
      box-shadow:0 12px 26px rgba(99,217,255,.32);
    }
    .nav-actions .nav-btn.logout{
      border-color:#7b4450;
      background:linear-gradient(140deg,#4d1f2b,#3a1720);
      color:#ffd9e1;
    }
    .nav-actions .nav-btn.logout:hover{
      border-color:#e2879a;
      box-shadow:0 10px 20px rgba(145,42,68,.35);
    }
    .display-settings-panel{
      position:fixed;
      right:18px;
      top:78px;
      width:min(360px,92vw);
      max-height:80vh;
      overflow:auto;
      border-radius:20px;
      border:1px solid rgba(255,255,255,.08);
      background:linear-gradient(160deg,rgba(12,20,32,.96),rgba(9,15,24,.96));
      box-shadow:0 28px 70px rgba(0,0,0,.5);
      padding:14px;
      z-index:1200;
      display:none;
    }
    .display-settings-panel.open{display:block}
    .display-settings-header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      margin-bottom:10px;
    }
    .display-settings-header .nav-btn{
      border:1px solid #2b3a52;
      background:#101c2b;
      color:#dbe9f7;
      border-radius:8px;
      padding:6px 10px;
      cursor:pointer;
    }
    .display-settings-header h4{margin:0;font:700 .9rem 'Sora',sans-serif}
    .display-settings-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:10px;
    }
    .display-toggle{
      border:1px solid #1f2f46;
      border-radius:16px;
      padding:12px;
      background:linear-gradient(160deg,#121c2b,#0e1826);
      display:grid;
      gap:10px;
    }
    .display-toggle .label{font:.75rem 'Manrope',sans-serif;color:#d6e6f7}
    .switch{
      display:inline-flex;
      align-items:center;
      gap:10px;
    }
    .switch input{display:none}
    .switch-track{
      width:42px;height:24px;border-radius:999px;
      border:1px solid #42597a;background:#101f30;position:relative;
      transition:background .2s ease,border-color .2s ease;
    }
    .switch-thumb{
      position:absolute;top:2px;left:2px;width:18px;height:18px;border-radius:50%;
      background:#8197b7;transition:transform .2s ease,background .2s ease;
    }
    .switch input:checked + .switch-track{background:#17324d;border-color:#66b4ff}
    .switch input:checked + .switch-track .switch-thumb{transform:translateX(18px);background:#9de2ff}
    .display-section{margin-top:14px}
    .display-section h5{margin:0 0 8px;font:.72rem 'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:.12em;color:#a9c5df}
    .preset-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
    .preset-btn{
      border:1px solid #20354d;border-radius:12px;padding:10px;background:#0c1826;
      display:flex;align-items:center;justify-content:center;cursor:pointer;
    }
    .preset-btn span{display:block;width:26px;height:26px;border-radius:8px}
    .font-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .font-btn{
      border:1px solid #20354d;border-radius:14px;padding:10px;background:#0c1826;cursor:pointer;
      display:grid;gap:4px;text-align:left;
    }
    .font-btn b{font-size:.86rem}
    .font-btn span{font-size:.68rem;color:#9ab3cc}
    .template-dashboard{
      --panel-bg:#ffffff;
      --panel-border:#e1e6ef;
      --panel-soft:#f5f7fb;
      --panel-strong:#f0f4fb;
    }
    body.template-dashboard{
      background:#f1f5f9;
      color:#5d657b;
    }
    body.template-dashboard .top{
      background:var(--panel-bg);
      border:1px solid var(--panel-border);
      box-shadow:0 10px 26px rgba(31,41,55,.12);
      backdrop-filter:none;
    }
    body.template-dashboard .app-header-subline{
      color:#7a859c;
    }
    body.template-dashboard .module-chip,
    body.template-dashboard .status-chip{
      border:1px solid #e1e7f0;
      background:#f6f8fc;
      color:#485166;
    }
    body.template-dashboard .module-chip{
      background:#eef5ff;
      border-color:#d5e4ff;
      color:#1c4fd7;
    }
    body.template-dashboard .header-nav .nav-link,
    body.template-dashboard .nav-actions .nav-btn{
      border:1px solid #e1e6ef;
      background:#ffffff;
      color:#4b5563;
      box-shadow:0 6px 16px rgba(31,41,55,.06);
      font-family:'Plus Jakarta Sans',sans-serif;
      font-weight:600;
      letter-spacing:.02em;
    }
    body.template-dashboard .header-nav .nav-link:hover,
    body.template-dashboard .nav-actions .nav-btn:hover{
      border-color:#cbd5e1;
      background:#f8fafc;
      transform:translateY(-1px);
      box-shadow:0 10px 20px rgba(31,41,55,.1);
    }
    body.template-dashboard .header-nav .nav-link.active,
    body.template-dashboard .nav-actions .nav-btn.active{
      color:#0f172a;
      background:#e0ecff;
      border-color:#bdd5ff;
      box-shadow:0 12px 24px rgba(59,130,246,.2);
    }
    body.template-dashboard .nav-actions .nav-btn.logout{
      border-color:#f2c7cf;
      background:#ffecef;
      color:#b42318;
    }
    body.template-dashboard .nav-actions .nav-btn.logout:hover{
      border-color:#f7a3af;
      box-shadow:0 10px 18px rgba(185,28,28,.18);
    }
    body.template-dashboard .nav-toggle{
      border-color:#d8e0ea;
      background:#ffffff;
      color:#475569;
      box-shadow:0 8px 18px rgba(31,41,55,.08);
    }
    body.template-dashboard .app-header-navrow{
      border-top:1px solid #e3e9f2;
    }
    body.template-dashboard .sidebar-nav-wrapper{
      background:#0f172a;
    }
    body.template-dashboard .sidebar-nav-wrapper .navbar-logo{
      border-bottom:1px solid rgba(255,255,255,.08);
    }
    body.template-dashboard .sidebar-nav-wrapper .logo-text{
      color:#f8fafc;
      font:700 1rem 'Plus Jakarta Sans',sans-serif;
      letter-spacing:.02em;
    }
    body.template-dashboard .sidebar-nav .nav-item > a{
      color:#e2e8f0;
    }
    body.template-dashboard .sidebar-nav .nav-item > a.active,
    body.template-dashboard .sidebar-nav .dropdown-nav a.active{
      color:#38bdf8;
    }
    body.template-dashboard .sidebar-nav .dropdown-nav a{
      color:#cbd5f5;
    }
    body.template-dashboard .sidebar-nav .divider hr{
      border-color:rgba(255,255,255,.08);
    }
    body.template-dashboard .main-wrapper{
      background:transparent;
    }
    body.template-dashboard .header{
      background:#ffffff;
      border-bottom:1px solid #e2e8f0;
      box-shadow:0 8px 24px rgba(15,23,42,.08);
    }
    body.template-dashboard .header .main-btn{
      border-radius:10px;
    }
    body.template-dashboard .card,
    body.template-dashboard .panel,
    body.template-dashboard .side-card,
    body.template-dashboard .api-result,
    body.template-dashboard .intel-map-shell,
    body.template-dashboard .intel-canvas-dock,
    body.template-dashboard .intel-accordion,
    body.template-dashboard table{
      background:var(--panel-bg);
      border:1px solid var(--panel-border);
      border-radius:12px;
      box-shadow:0 10px 30px rgba(45,55,72,.12);
    }
    body.template-dashboard .card{padding:16px}
    body.template-dashboard h2{font-size:1.05rem;color:#1a1d24}
    body.template-dashboard h3{font-size:.9rem;color:#1a1d24}
    body.template-dashboard .header-nav a,
    body.template-dashboard .nav-actions .nav-btn{
      border-radius:8px;
      border:1px solid #dbe1ee;
      background:#ffffff;
      color:#1a1d24;
    }
    body.template-dashboard .btn,
    body.template-dashboard button{
      border-radius:8px;
      border:1px solid #dbe1ee;
      background:#ffffff;
      color:#1a1d24;
      font-weight:600;
      box-shadow:0 6px 16px rgba(45,55,72,.08);
    }
    body.template-dashboard .btn:hover,
    body.template-dashboard button:hover{
      border-color:#365cf5;
      color:#365cf5;
      box-shadow:0 10px 24px rgba(54,92,245,.15);
    }
    body.template-dashboard input,
    body.template-dashboard select,
    body.template-dashboard textarea{
      background:var(--panel-soft);
      border:1px solid #dbe1ee;
      border-radius:8px;
      color:#1a1d24;
    }
    body.template-dashboard table th{
      background:#f7f9fc;
      border-bottom:1px solid #e4e9f2;
      color:#1a1d24;
    }
    body.template-dashboard .intel-workspace-nav,
    body.template-dashboard .intel-map-toolbar{
      background:var(--panel-strong);
      border:1px solid var(--panel-border);
      border-radius:12px;
    }
    body.template-dashboard .kpi{
      border:1px solid #e2e8f0;
      background:#ffffff;
      box-shadow:0 12px 26px rgba(15,23,42,.08);
    }
    body.template-dashboard .kpi b{
      color:#0f172a;
    }
    body.template-dashboard .kpi .mut{
      color:#64748b;
    }
    body.template-dashboard .kpi-icon{
      background:#eef2ff;
      border-color:#c7d2fe;
      color:#4f46e5;
    }
    body.template-dashboard .role-chip,
    body.template-dashboard .badge,
    body.template-dashboard .event-chip,
    body.template-dashboard .intel-chip{
      background:#f1f5f9;
      border-color:#e2e8f0;
      color:#334155;
    }
    body.template-dashboard .mut,
    body.template-dashboard .mut-mini{
      color:#6b7280;
    }
    body.template-dashboard .mono,
    body.template-dashboard code{
      color:#0f172a;
      background:#f1f5f9;
      border-color:#e2e8f0;
    }
    body.template-dashboard pre{
      background:#f8fafc;
      border-color:#e2e8f0;
      color:#0f172a;
      box-shadow:none;
    }
    body.template-dashboard .side-card{
      background:#ffffff;
      border-color:#e2e8f0;
      box-shadow:0 10px 24px rgba(15,23,42,.08);
    }
    body.template-dashboard .side-metric{
      border-color:#e2e8f0;
      background:#f8fafc;
    }
    body.template-dashboard .side-metric span{
      color:#64748b;
    }
    body.template-dashboard .side-metric b{
      color:#0f172a;
    }
    body.template-dashboard .mini-list li{
      border-bottom:1px solid #e5e7eb;
      color:#475569;
    }
    body.template-dashboard .mini-links a{
      border-color:#e2e8f0;
      background:#f8fafc;
      color:#334155;
      font-family:'Plus Jakarta Sans',sans-serif;
      font-weight:600;
    }
    body.template-dashboard .mini-links a:hover{
      border-color:#cbd5e1;
      background:#ffffff;
      color:#1d4ed8;
      box-shadow:0 10px 20px rgba(15,23,42,.08);
    }
    body.template-dashboard .announcement-aside details,
    body.template-dashboard .announcement-item,
    body.template-dashboard .announcement-focus{
      background:#f8fafc;
      border-color:#e2e8f0;
      color:#475569;
    }
    body.template-dashboard .announcement-severity{
      border-color:#cbd5e1;
      background:#ffffff;
      color:#334155;
    }
    body.template-dashboard .announcement-severity.info{
      background:#eff6ff;
      border-color:#bfdbfe;
      color:#1d4ed8;
    }
    body.template-dashboard .announcement-severity.warning{
      background:#fffbeb;
      border-color:#fcd34d;
      color:#92400e;
    }
    body.template-dashboard .announcement-severity.critical{
      background:#fef2f2;
      border-color:#fecaca;
      color:#b91c1c;
    }
    body.template-dashboard .ad-slot{
      background:#f8fafc;
      border-color:#cbd5e1;
      color:#64748b;
    }
    body.template-dashboard .internal-ad-card{
      border-color:#e2e8f0;
      background:#ffffff;
    }
    body.template-dashboard .internal-ad-kicker{
      color:#64748b;
    }
    body.template-dashboard .internal-ad-card b{
      color:#0f172a;
    }
    body.template-dashboard .internal-ad-card p{
      color:#64748b;
    }
    body.template-dashboard .geo-map{
      background:#f1f5f9;
      border-color:#e2e8f0;
    }
    body.template-dashboard .geo-chip{
      border-color:#e2e8f0;
      background:#ffffff;
      color:#334155;
    }
    body.template-dashboard .analytics-table-wrap,
    body.template-dashboard .geo-table-wrap{
      border-color:#e2e8f0;
      background:#ffffff;
    }
    body.template-dashboard table{
      background:#ffffff;
      color:#0f172a;
    }
    body.template-dashboard th{
      background:#f1f5f9;
      color:#475569;
    }
    body.template-dashboard td{
      border-color:#e2e8f0;
    }
    body.template-dashboard .display-settings-panel{
      background:#ffffff;
      border:1px solid #e2e8f0;
      box-shadow:0 18px 40px rgba(15,23,42,.18);
      color:#0f172a;
    }
    body.template-dashboard .display-settings-header .nav-btn{
      background:#f8fafc;
      border-color:#e2e8f0;
      color:#1f2937;
    }
    body.template-dashboard .display-settings-header h4{
      color:#0f172a;
      font-family:'Plus Jakarta Sans',sans-serif;
    }
    body.template-dashboard .display-toggle{
      background:#f8fafc;
      border-color:#e2e8f0;
    }
    body.template-dashboard .display-toggle .label{
      color:#475569;
    }
    body.template-dashboard .switch-track{
      border-color:#cbd5e1;
      background:#e2e8f0;
    }
    body.template-dashboard .switch-thumb{
      background:#ffffff;
      box-shadow:0 2px 6px rgba(15,23,42,.18);
    }
    body.template-dashboard .switch input:checked + .switch-track{
      background:#c7d2fe;
      border-color:#a5b4fc;
    }
    body.template-dashboard .switch input:checked + .switch-track .switch-thumb{
      background:#1d4ed8;
    }
    body.template-dashboard .preset-btn,
    body.template-dashboard .font-btn{
      background:#f8fafc;
      border-color:#e2e8f0;
      color:#0f172a;
    }
    body.template-dashboard .font-btn span{
      color:#64748b;
    }
    .hero{display:grid;grid-template-columns:2fr .9fr;gap:8px;margin-bottom:8px}
    .hero-main,.hero-side{padding:11px}
    .hero-main h1{margin:0 0 6px;font-size:clamp(1.14rem,1.45vw,1.48rem);line-height:1.15}
    .hero-main p{margin:0;color:#c8d9ef}
    .hero-kicker{
      display:inline-block;
      padding:5px 9px;
      border-radius:999px;
      border:1px solid #3f739a;
      background:#11314a;
      font:.69rem 'JetBrains Mono',monospace;
      color:#d0ebff;
      margin-bottom:6px;
    }
    .role-chip{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:5px 10px;
      border-radius:999px;
      font:.7rem 'JetBrains Mono',monospace;
      border:1px solid #2ca97788;
      background:#10382d;
      color:#bff8df;
    }
    .role-chip.guest{border-color:#927138;background:#3d2f12;color:#ffe8ac}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-bottom:8px}
    .card{
      padding:10px;
      position:relative;
      overflow:hidden;
      transition:border-color .2s ease,box-shadow .22s ease,transform .22s ease;
    }
    .card:hover{
      border-color:rgba(125,193,255,.62);
      box-shadow:0 22px 46px rgba(8,20,40,.44);
    }
    h2{margin:0 0 7px;font:700 .92rem 'Sora',sans-serif;letter-spacing:.14px}
    h3{margin:0 0 6px;font:600 .8rem 'Sora',sans-serif}
    p{line-height:1.35}
    ul{margin:0;padding-left:18px}
    li{margin:4px 0}
    .kpi{
      background:linear-gradient(155deg,rgba(21,45,73,.86),rgba(14,31,51,.86));
      border-color:rgba(88,141,197,.56);
    }
    .kpi-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
    .kpi-icon{
      width:28px;
      height:28px;
      border-radius:10px;
      border:1px solid #4a749a;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      background:linear-gradient(145deg,#12324f,#0d243a);
      box-shadow:0 8px 18px rgba(0,0,0,.24);
      flex-shrink:0;
    }
    .kpi-icon svg{width:15px;height:15px;stroke:#c8ebff;stroke-width:1.8;fill:none;stroke-linecap:round;stroke-linejoin:round}
    .kpi b{font-size:1.34rem;font-family:'Sora',sans-serif;font-weight:700;line-height:1.1}
    .viz-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px}
    .mini-chart{display:grid;gap:6px}
    .mini-row{display:grid;grid-template-columns:140px 1fr 52px;align-items:center;gap:8px}
    .mini-bar{height:8px;border-radius:999px;background:#183f5d;overflow:hidden}
    .mini-bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#56d4ff,#33d17a)}
    .mini-score{font:.72rem 'JetBrains Mono',monospace;color:#a8cae8;text-align:right}
    .pred-badge{padding:2px 7px;border-radius:999px;border:1px solid #3f6c95;background:#13324f;font:.66rem 'JetBrains Mono',monospace}
    .pred-badge.malicious{border-color:#a94a56;color:#ffd2d8;background:#3f1822}
    .pred-badge.suspicious{border-color:#8f7844;color:#ffebbe;background:#3e3014}
    .pred-badge.low_risk{border-color:#2b7c5f;color:#c7ffe8;background:#10352b}
    .mut{color:var(--mut)}
    .user-link{color:#9de9ff;text-decoration:none;border-bottom:1px dashed #4ea7d8}
    .user-link:hover{color:#d5f4ff;border-bottom-color:#8fe4ff}
    .flash{
      border:1px solid #2b8466;
      background:linear-gradient(150deg,rgba(31,101,81,.48),rgba(17,62,52,.45));
      padding:8px 10px;
      border-radius:11px;
      margin-bottom:8px;
      box-shadow:0 10px 20px rgba(0,0,0,.2) inset;
    }
    .access-layout{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:16px;
      align-items:start;
    }
    .access-card{
      width:100%;
      max-width:none;
      margin:0;
      min-width:0;
    }
    .access-card--full{
      grid-column:1 / -1;
    }
    .access-card .card,
    .access-card .card-body{
      height:100%;
    }
    @media (max-width: 1080px){
      .access-layout{
        grid-template-columns:1fr;
      }
      .access-card--full{
        grid-column:auto;
      }
    }
    /* Avoid overriding Bootstrap .row globally */
    .stack{display:grid;gap:8px}
    table{
      width:100%;
      border-collapse:separate;
      border-spacing:0;
      font-size:.79rem;
      background:rgba(9,18,32,.45);
      border:1px solid rgba(55,87,130,.45);
      border-radius:14px;
      overflow:hidden;
    }
    th,td{
      padding:7px 8px;
      border-bottom:1px solid rgba(58,96,132,.3);
      text-align:left;
      vertical-align:top;
    }
    th{
      font:700 .68rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      color:#c7ddf4;
      letter-spacing:.28px;
      background:linear-gradient(120deg,rgba(24,49,75,.75),rgba(12,28,46,.6));
      border-bottom:1px solid rgba(90,130,178,.55);
    }
    th.sortable{
      cursor:pointer;
      user-select:none;
      position:relative;
      padding-right:18px;
    }
    th.sortable::after{
      content:'â†•';
      position:absolute;
      right:7px;
      top:50%;
      transform:translateY(-50%);
      font-size:.68rem;
      color:#84a6c8;
      opacity:.9;
    }
    th.sortable.sort-asc::after{content:'â†‘';color:#d8efff}
    th.sortable.sort-desc::after{content:'â†“';color:#d8efff}
    tr:hover td{background:rgba(16,32,55,.58)}
    input,select,textarea,button{
      width:100%;
      padding:9px 11px;
      border-radius:12px;
      border:1px solid rgba(70,104,150,.7);
      background:
        linear-gradient(150deg,rgba(20,39,64,.95),rgba(10,24,42,.95));
      color:var(--txt);
      font:600 .84rem 'Manrope',sans-serif;
      transition:border-color .2s ease,box-shadow .2s ease,transform .18s ease,background .2s ease;
      box-shadow:0 6px 16px rgba(3,10,22,.2);
    }
    select{
      appearance:none;
      background-image:
        linear-gradient(45deg,transparent 50%,rgba(180,210,255,.9) 50%),
        linear-gradient(135deg,rgba(180,210,255,.9) 50%,transparent 50%),
        linear-gradient(to right,transparent,transparent);
      background-position:
        calc(100% - 16px) calc(50% - 2px),
        calc(100% - 10px) calc(50% - 2px),
        calc(100% - 26px) 0.55rem;
      background-size:6px 6px,6px 6px,1px 1.4rem;
      background-repeat:no-repeat;
      padding-right:30px;
    }
    select:focus{background-image:
        linear-gradient(45deg,transparent 50%,rgba(255,255,255,.95) 50%),
        linear-gradient(135deg,rgba(255,255,255,.95) 50%,transparent 50%),
        linear-gradient(to right,transparent,transparent);}
    input[type=checkbox],input[type=radio]{width:auto;padding:0;transform:translateY(1px)}
    label{display:flex;gap:8px;align-items:center}
    input:focus,select:focus,textarea:focus{
      outline:none;
      border-color:#9af1ff;
      box-shadow:0 0 0 3px rgba(111,246,255,.18),0 10px 24px rgba(3,12,22,.25);
      background:linear-gradient(160deg,rgba(24,49,78,.95),rgba(10,24,42,.95));
    }
    textarea{min-height:76px}
    .btn{
      background:
        linear-gradient(120deg,var(--brand),var(--brand-2));
      border:none;
      color:#041322;
      font-weight:800;
      letter-spacing:.2px;
      text-transform:uppercase;
      font-size:.73rem;
      cursor:pointer;
      box-shadow:0 14px 30px rgba(86,210,255,.3),var(--glow);
      transition:.2s ease;
    }
    .btn:hover{
      transform:translateY(-1px);
      box-shadow:0 18px 36px rgba(86,210,255,.4),0 0 20px rgba(127,255,191,.25);
    }
    .btn:active{transform:translateY(0);box-shadow:0 10px 20px rgba(86,210,255,.26)}
    .split{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
    .badge{padding:4px 8px;border-radius:999px;border:1px solid #5b7f9f;font:.68rem 'JetBrains Mono',monospace}
    .pending{color:var(--warn)}.accepted{color:var(--ok)}.rejected{color:var(--danger)}.allowlisted{color:#67d4ff}
    .bulk-review-toolbar{
      margin:8px 0;
      display:flex;
      flex-wrap:wrap;
      align-items:center;
      gap:8px;
      border:1px solid rgba(68,112,150,.58);
      border-radius:12px;
      background:linear-gradient(150deg,#10253d,#0d2135);
      padding:8px;
    }
    .bulk-review-toolbar .bulk-review-count{
      font:.73rem 'JetBrains Mono',monospace;
      color:#bcd6ef;
      min-width:120px;
    }
    .bulk-review-toolbar .bulk-review-actions{
      display:flex;
      gap:6px;
      flex-wrap:wrap;
      margin-left:auto;
    }
    .bulk-review-toolbar .bulk-review-actions button{
      width:auto;
      min-width:0;
      padding:6px 10px;
      font:.72rem 'JetBrains Mono',monospace;
    }
    .bulk-review-toolbar .bulk-review-actions button.secondary{
      background:linear-gradient(145deg,#16324e,#102742);
      border:1px solid #486e95;
      color:#d8edff;
      box-shadow:none;
    }
    .bulk-review-select-cell{
      width:38px;
      text-align:center;
    }
    .bulk-review-select-cell input[type=checkbox]{
      width:16px;
      height:16px;
      margin:0;
      transform:none;
      cursor:pointer;
    }
    .event-workbench{
      --event-feed-width: clamp(300px, 24vw, 360px);
      display:grid !important;
      grid-template-columns:minmax(300px, var(--event-feed-width)) minmax(0, 1fr) !important;
      gap:20px !important;
      margin-top:6px;
      align-items:start;
      width:100% !important;
      min-width:0 !important;
    }
    .event-feed{
      display:flex;
      flex-direction:column;
      gap:10px;
      max-height:calc(100vh - 220px);
      overflow:auto;
      padding-right:6px;
      position:sticky;
      top:calc(var(--sticky-header-offset) + 8px);
      align-self:start;
      width:100%;
      min-width:0;
    }
    .event-group{display:flex;flex-direction:column;gap:6px}
    .event-group-head{display:grid;grid-template-columns:1fr;gap:8px;align-items:start}
    .event-group-toggle{
      border:1px solid #2d5377;border-radius:10px;background:#0b1b2b;color:#9cc6ff;
      padding:6px 10px;font-size:.7rem;cursor:pointer;white-space:nowrap;
      justify-self:start;
      width:auto;
    }
    .event-group-toggle:hover{border-color:#67edc1;color:#e6fbff}
    .event-group-items{display:flex;flex-direction:column;gap:6px;padding-left:12px}
    .event-feed-item{
      display:flex;align-items:flex-start;gap:8px;text-align:left;
      border:1px solid #315a80;border-radius:11px;background:#0c2137;padding:7px 9px;
      color:var(--txt);cursor:pointer;transition:.14s ease;
    }
    .event-feed-item--child{
      background:#0b1b2b;border-color:#203d5b;opacity:0.92;
    }
    .event-feed-count{
      display:inline-flex;align-items:center;justify-content:center;
      padding:2px 6px;border-radius:999px;border:1px solid #365f85;
      font:.68rem 'JetBrains Mono',monospace;color:#c7e5ff;background:#10273f;margin-top:4px;
      width:max-content;
    }
    .event-feed-item:hover{border-color:#73d4ff;background:#143150}
    .event-feed-item.is-active{border-color:#67edc1;background:linear-gradient(145deg,#194867,#143d59)}
    .event-feed-item.is-blocked{
      border-color:#ff6d7d;
      background:linear-gradient(145deg,#3f1c29,#2b1520);
      box-shadow:inset 0 0 0 1px #ff899766;
    }
    .event-feed-item.is-blocked:hover{
      border-color:#ffa8b3;
      background:linear-gradient(145deg,#4a1f2d,#341724);
    }
    .event-feed-item.is-active.is-blocked{
      border-color:#ff8f9f;
      background:linear-gradient(145deg,#5a2435,#3d1b27);
      box-shadow:inset 0 0 0 1px #ffb2bc88;
    }
    .scan-capture-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .scan-title{margin:0;font-size:1.05rem}
    .scan-capture-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:10px}
    .scan-card{border:1px solid #2b4f73;border-radius:14px;background:#0d2136;padding:12px;display:flex;flex-direction:column;gap:10px}
    .scan-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px}
    .scan-card-media{border:1px solid #2b4f73;border-radius:12px;overflow:hidden;background:#0a1c2b}
    .scan-card-media img{width:100%;height:220px;object-fit:cover;display:block}
    .scan-placeholder{padding:28px;text-align:center;color:#9bb9d6}
    .scan-card-actions{display:flex;flex-wrap:wrap;gap:8px}
    .scan-inline-form{display:flex;gap:6px;align-items:center}
    .scan-gallery{margin-top:16px}
    .scan-subtitle{margin:0 0 10px;font-size:.95rem}
    .scan-gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px}
    .scan-thumb-card{border:1px solid #2b4f73;border-radius:12px;background:#0b1b2b;padding:10px;display:flex;flex-direction:column;gap:8px}
    .scan-thumb-head{display:flex;align-items:center;justify-content:space-between;gap:6px}
    .scan-thumb-media{border:1px solid #243f5f;border-radius:10px;overflow:hidden;background:#0a1c2b}
    .scan-thumb-media img{width:100%;height:120px;object-fit:cover;display:block}
    .scan-thumb-meta{display:flex;flex-direction:column;gap:2px}
    .scan-thumb-actions{display:flex;flex-wrap:wrap;gap:6px}
    .analytics-hub{background:linear-gradient(145deg,#0d1b2d,#0a1523);border:1px solid #243a56}
    .analytics-hub-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:14px}
    .analytics-hub-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .range-chips{display:flex;gap:6px}
    .chip{border:1px solid #2a4b6c;border-radius:999px;background:#0f2135;color:#9fc6f5;padding:4px 10px;font-size:.72rem}
    .chip.is-active{background:#1e3a5b;color:#e9f2ff;border-color:#3d6a97}
    .kpi-pill{border:1px solid #2b4f73;border-radius:12px;background:#0c2034;color:#cfe6ff;padding:6px 10px;font-size:.75rem}
    .kpi-pill b{color:#fff}
    .analytics-hub-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .analytics-panel{border:1px solid #2b4f73;border-radius:14px;background:#0b1b2b;padding:12px;display:flex;flex-direction:column;gap:10px}
    .analytics-panel--wide{grid-column:1 / -1}
    .panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
    .panel-head h3{margin:0;font-size:1rem}
    .panel-tags{display:flex;gap:6px;flex-wrap:wrap}
    .tag{font-size:.68rem;border:1px solid #2b4f73;border-radius:999px;padding:2px 8px}
    .tag.blue{color:#7dc8ff;border-color:#2f5d85}
    .tag.green{color:#7ee4a8;border-color:#2a5a46}
    .tag.amber{color:#ffd166;border-color:#7a6223}
    .panel-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .panel-kpis div{display:flex;flex-direction:column;gap:4px;padding:8px;border-radius:10px;background:#0a1c2b;border:1px solid #203d5b}
    .panel-kpis b{font-size:1.05rem}
    .panel-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .panel-metric{padding:8px;border-radius:10px;background:#0a1c2b;border:1px solid #203d5b}
    .panel-metric b{font-size:.95rem}
    .analytics-table-card{margin-top:12px;border:1px solid #2b4f73;border-radius:14px;background:#0b1b2b;padding:12px}
    .analytics-shell{display:flex;flex-direction:column;gap:14px}
    .analytics-header{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap}
    .analytics-header h1{margin:0;font-size:1.4rem;letter-spacing:.01em}
    .analytics-header-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .analytics-kpi-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .analytics-kpi{border:1px solid #2b4f73;border-radius:12px;background:#0b1b2b;padding:10px}
    .analytics-kpi .k{font-size:.72rem;color:#9bb9d6;text-transform:uppercase;letter-spacing:.06em}
    .analytics-kpi .v{font-size:1.15rem;font-weight:700;color:#fff;margin-top:4px}
    .chart-stack{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .chart-grid-advanced{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}
    .chart-card{border:1px solid #2b4f73;border-radius:14px;background:#0b1b2b;padding:12px}
    .chart-title{margin:0 0 6px;font-weight:700}
    .analytics-search{width:100%;max-width:520px;padding:8px 10px;border-radius:10px;border:1px solid #2b4f73;background:#0b1b2b;color:#e6f4ff}
    .analytics-table-wrap{border:1px solid #2b4f73;border-radius:12px;background:#0a1c2b;overflow:auto;max-height:520px}
    .analytics-table-wrap .compact-table thead th{position:sticky;top:0;background:#0f2841;z-index:1}
    .viz-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .ops-grid{
      display:grid;
      grid-template-columns:minmax(0,1fr) !important;
      gap:14px;
    }
    .ops-main{
      display:flex;
      flex-direction:column;
      gap:14px;
      width:100%;
      min-width:0;
    }
    .ops-main .event-workbench{margin-top:0}
    .ops-side{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:12px;
      width:100%;
      min-width:0;
    }
    .ops-side > .card{
      min-width:0;
    }
    .ops-side-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:12px;
      grid-column:1 / -1;
    }
    .ops-panel{border:1px solid #2b4f73;border-radius:16px;background:linear-gradient(160deg,#0b1a2a,#0c2236)}
    .ops-panel .card-body{display:flex;flex-direction:column;gap:12px}
    .ops-panel-head{display:flex;justify-content:space-between;align-items:center}
    .ops-panel-head h3{margin:0;font-size:1rem}
    .ops-kpi-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .ops-kpi{padding:8px;border-radius:12px;background:#0a1c2b;border:1px solid #203d5b}
    .ops-kpi span{font-size:.7rem;color:#9bb9d6;text-transform:uppercase;letter-spacing:.06em}
    .ops-kpi strong{display:block;font-size:1.05rem;margin-top:4px}
    .ops-mini{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .ops-mini div{padding:8px;border-radius:12px;border:1px solid #203d5b;background:#0a1c2b;font-size:.78rem}
    .ops-actions{display:flex;flex-wrap:wrap;gap:6px}
    .ops-sev{display:grid;gap:6px}
    .ops-sev-row{display:flex;justify-content:space-between;align-items:center;padding:6px 10px;border-radius:10px;border:1px solid #243f5f;background:#0a1c2b}
    .ops-sev-row.high{border-color:#ff6b7a66;color:#ffd7dd}
    .ops-sev-row.med{border-color:#ffd16666;color:#ffe9b8}
    .ops-sev-row.low{border-color:#7ee4a866;color:#dfffee}
    .ops-sev-filter{display:flex;flex-wrap:wrap;gap:6px}
    .ops-sev-filter-btn{
      border:1px solid #2b4f73;
      background:#0a1c2b;
      color:#cfe7ff;
      border-radius:999px;
      padding:5px 10px;
      font:.72rem 'JetBrains Mono',monospace;
      cursor:pointer;
    }
    .ops-sev-filter-btn.is-active{
      background:linear-gradient(135deg,#1d79ff,#31d0aa);
      border-color:transparent;
      color:#fff;
      box-shadow:0 10px 22px -16px rgba(49,208,170,0.75);
    }
    .ops-domain-list{display:grid;gap:6px}
    .ops-domain-item{display:flex;justify-content:space-between;align-items:center;padding:6px 10px;border-radius:10px;border:1px solid #203d5b;background:#0a1c2b}
    .ops-domain-item .mut{flex:1;min-width:0;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ops-lower-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}
    .ops-lower-grid .legacy-card{height:100%}
    .legacy-card{
      border:1px solid #2a4866;border-radius:16px;padding:10px 12px;background:#0d2136;margin-top:12px;
    }
    .legacy-card summary{
      list-style:none;cursor:pointer;font-weight:700;color:#e6f4ff;display:flex;align-items:center;gap:10px;
    }
    .legacy-card summary::-webkit-details-marker{display:none}
    .legacy-card summary::before{
      content:"";width:12px;height:12px;border-radius:50%;
      background:linear-gradient(140deg,#5b8bff,#37e3a7);
      box-shadow:0 0 10px rgba(91,139,255,0.5);
    }
    .legacy-table-wrap{
      margin-top:10px;border:1px solid #2b4f73;border-radius:12px;overflow:auto;max-height:520px;
      background:#0a1c2b;
    }
    .legacy-table{table-layout:fixed;width:100%;min-width:920px}
    .legacy-table thead th{
      position:sticky;top:0;background:#0f2841;border-bottom:1px solid #2b4f73;z-index:2;
      font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#a8c6e4;
    }
    .legacy-table td{vertical-align:top}
    .legacy-table .message-cell{
      max-width:520px;white-space:pre-wrap;word-break:break-word;line-height:1.35;
      display:-webkit-box;-webkit-line-clamp:5;-webkit-box-orient:vertical;overflow:hidden;
    }
    .legacy-table tr:hover .message-cell{
      -webkit-line-clamp:unset;display:block;
    }
    .event-feed-item.is-blocked .event-feed-host{color:#ffdce1}
    .event-feed-item.is-blocked .event-feed-meta{color:#f2c2cb}
    .event-feed-main{display:flex;flex-direction:column;gap:2px;min-width:0}
    .event-feed-host{font-weight:700;word-break:break-word;overflow-wrap:anywhere;line-height:1.25}
    .event-feed-meta{font:.72rem 'JetBrains Mono',monospace;color:var(--mut);word-break:break-word;overflow-wrap:anywhere;line-height:1.35}
    .event-feed-reason{font-size:.77rem;color:#d7e7fb;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .event-feed-flag{
      display:inline-flex;align-items:center;gap:4px;margin-top:4px;width:max-content;
      padding:2px 7px;border-radius:999px;border:1px solid #ff7a8f;background:#5f1b2a;color:#ffd9df;
      font:.66rem 'JetBrains Mono',monospace;letter-spacing:.2px
    }
    .event-feed-sev{width:8px;height:8px;border-radius:999px;margin-top:5px;flex-shrink:0;background:#4de09f}
    .event-feed-sev.low{background:#4de09f}.event-feed-sev.medium{background:#f5c14b}.event-feed-sev.high{background:#ff8f3f}.event-feed-sev.critical{background:#ff6d7d}
    .event-detail-shell{
      border:1px solid #3d638b;
      border-radius:14px;
      padding:14px 16px;
      background:linear-gradient(150deg,#102741,#0b2137);
      min-width:0;
      width:100% !important;
      max-width:none !important;
      justify-self:stretch;
      align-self:stretch;
    }
    #event-detail{
      display:grid;
      gap:14px;
    }
    #event-detail > .event-topline{grid-column:1 / -1}
    .event-ai-summary{
      display:grid;
      gap:10px;
      border:1px solid #2f5a80;
      border-radius:14px;
      padding:14px;
      background:
        radial-gradient(circle at top right,rgba(97,196,255,.12),transparent 26%),
        linear-gradient(160deg,#102a42,#0c2134);
      box-shadow:inset 0 1px 0 rgba(255,255,255,.04);
    }
    .event-ai-summary-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
    }
    .event-ai-summary-head h3{
      margin:0;
      font-size:.95rem;
      color:#edf7ff;
    }
    .event-ai-summary-tag{
      display:inline-flex;
      align-items:center;
      padding:4px 9px;
      border-radius:999px;
      border:1px solid #3d6f97;
      background:#13314b;
      color:#bfe4ff;
      font:.66rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.12em;
    }
    .event-ai-summary-text{
      margin:0;
      color:#e7f4ff;
      font-size:.94rem;
      line-height:1.55;
      max-width:110ch;
    }
    .event-ai-summary-points{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
    }
    .event-ai-point{
      display:inline-flex;
      align-items:center;
      max-width:100%;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid #2e5d85;
      background:#0c2337;
      color:#d4ebff;
      font:.72rem 'JetBrains Mono',monospace;
      white-space:normal;
      word-break:break-word;
    }
    #event-detail > .event-grid:first-of-type,
    #event-detail > .event-ai-summary,
    #event-detail > .event-columns,
    #event-detail > .event-related,
    #event-detail > .mitre-blueprint,
    #event-detail > .event-context,
    #event-detail > .event-ioc,
    #event-detail > .event-raw{
      border:1px solid #2f567b;
      border-radius:14px;
      background:linear-gradient(160deg,#0d2439,#0a1d2f);
      padding:12px;
      box-shadow:inset 0 1px 0 rgba(255,255,255,.03);
    }
    #event-detail > .event-grid:first-of-type{
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:8px;
      margin:0;
      position:relative;
    }
    #event-detail > .event-grid:first-of-type::before{
      content:"Resumen operativo";
      grid-column:1 / -1;
      display:block;
      margin-bottom:2px;
      color:#d9ecff;
      font:700 .88rem 'Sora',sans-serif;
      letter-spacing:.01em;
    }
    #event-detail > .event-grid:first-of-type::after{
      content:"timeline / location / navigation";
      position:absolute;
      right:12px;
      top:12px;
      color:#89b8de;
      font:.64rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.14em;
    }
    .event-empty{color:var(--mut);font-size:.88rem}
    .event-title{
      margin:0;
      font-size:1.18rem;
      line-height:1.15;
      letter-spacing:.01em;
    }
    .event-topline{
      display:flex;
      justify-content:space-between;
      gap:12px;
      align-items:flex-start;
      flex-wrap:wrap;
      padding:0 2px 2px;
    }
    .event-badges{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
    .event-chip{
      padding:4px 9px;
      border-radius:999px;
      border:1px solid #365f85;
      background:#12314b;
      font:.68rem 'JetBrains Mono',monospace;
      color:#d8ecff;
    }
    .event-grid{display:grid !important;grid-template-columns:repeat(2,minmax(0,1fr)) !important;gap:10px !important;margin:8px 0}
    .event-kv{
      padding:9px 10px;
      border:1px solid #2e5478;
      border-radius:11px;
      background:linear-gradient(160deg,#102a42,#0d2135);
      min-height:62px;
    }
    .event-kv b{
      display:block;
      font-size:.66rem;
      color:#9ec4e7;
      font-family:'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.12em;
      margin-bottom:6px;
    }
    .event-kv span{
      display:block;
      font-size:.86rem;
      line-height:1.4;
      word-break:break-word;
      overflow-wrap:anywhere;
      color:#edf6ff;
    }
    .event-columns{
      display:grid !important;
      grid-template-columns:repeat(2,minmax(320px,1fr)) !important;
      gap:12px !important;
      margin-top:0;
      align-items:start;
      width:100% !important;
    }
    #event-evidence{
      grid-template-columns:repeat(2,minmax(280px,1fr)) !important;
    }
    .event-columns > div{
      border:1px solid #274766;
      border-radius:12px;
      background:linear-gradient(160deg,#0f2539,#0b1d2d);
      padding:12px;
      min-height:100%;
      min-width:0;
      overflow:hidden;
    }
    .event-columns > div > h3{
      margin:0 0 10px;
      font-size:.9rem;
      color:#edf7ff;
    }
    .event-related{
      margin-top:0;
      border:1px solid #2f5579;
      border-radius:11px;
      background:#0c2238;
      padding:12px;
    }
    .event-related-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      flex-wrap:wrap;
      margin-bottom:6px;
    }
    .event-related-note{font:.74rem 'JetBrains Mono',monospace;color:#a8c6e4}
    .event-related-table-wrap{overflow:auto;margin-top:6px}
    .event-related-link{
      color:#9de9ff;
      text-decoration:none;
      border-bottom:1px dashed #58bde4;
    }
    .event-related-link:hover{color:#d5f4ff;border-bottom-color:#9de9ff}
    .event-scope-picker{
      display:grid;
      gap:8px;
      margin-bottom:10px;
      padding:10px 12px;
      border:1px solid #2d557a;
      border-radius:12px;
      background:linear-gradient(160deg,#10253a,#0c1f31);
    }
    .event-scope-label{
      color:#cfe6ff;
      font:.72rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.1em;
    }
    .event-scope-toggle{
      display:inline-flex;
      flex-wrap:wrap;
      gap:6px;
    }
    .event-scope-toggle button{
      border:1px solid #365f85;
      border-radius:999px;
      background:#0f2740;
      color:#bfe0ff;
      padding:6px 10px;
      font:.72rem 'JetBrains Mono',monospace;
      cursor:pointer;
      transition:background .16s ease,border-color .16s ease,color .16s ease;
    }
    .event-scope-toggle button.is-active{
      background:#183a59;
      border-color:#6cc9ff;
      color:#f4fbff;
      box-shadow:inset 0 0 0 1px rgba(108,201,255,.18);
    }
    .event-scope-note{
      color:#9ec4e7;
      font-size:.78rem;
      line-height:1.4;
    }
    .event-list{margin:4px 0 0 16px;padding:0}
    .event-list li{margin:5px 0;color:#c8e0f7;line-height:1.4}
    .event-snippet{
      padding:9px 10px;
      border-radius:10px;
      border:1px solid #2f597f;
      background:#13304a;
      font:.73rem 'JetBrains Mono',monospace;
      white-space:pre-wrap;
      word-break:break-word;
      line-height:1.45;
    }
    .score-detail-shell{
      display:grid;
      gap:12px;
    }
    .score-detail-toolbar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
    }
    .score-detail-total{
      display:flex;
      align-items:baseline;
      gap:8px;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid #315a80;
      background:linear-gradient(160deg,#143252,#102740);
      color:#edf7ff;
    }
    .score-detail-total strong{
      font-size:1.35rem;
      line-height:1;
    }
    .score-detail-total span{
      font:.72rem 'JetBrains Mono',monospace;
      color:#9ec4e7;
      text-transform:uppercase;
      letter-spacing:.1em;
    }
    .score-detail-toggle{
      display:inline-flex;
      gap:6px;
      padding:4px;
      border-radius:999px;
      border:1px solid #2f597f;
      background:#0d2236;
    }
    .score-detail-toggle button{
      border:0;
      border-radius:999px;
      padding:6px 10px;
      background:transparent;
      color:#9ec4e7;
      font:.7rem 'JetBrains Mono',monospace;
      cursor:pointer;
      transition:background .16s ease,color .16s ease;
    }
    .score-detail-toggle button.is-active{
      background:#163755;
      color:#f5fbff;
      box-shadow:inset 0 0 0 1px #4b86b6;
    }
    .score-detail-panels{
      display:grid;
      gap:12px;
    }
    .score-detail-panels [hidden]{
      display:none !important;
    }
    .score-detail-visual{
      display:grid;
      gap:12px;
    }
    .score-component-list{
      display:grid;
      gap:10px;
    }
    .score-component-card{
      display:grid;
      gap:10px;
      padding:12px;
      border-radius:12px;
      border:1px solid #2f597f;
      background:linear-gradient(160deg,#102a42,#0d2135);
    }
    .score-component-head{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
    }
    .score-component-title{
      display:grid;
      gap:4px;
    }
    .score-component-title strong{
      color:#edf7ff;
      font-size:.92rem;
    }
    .score-component-meta{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      font:.7rem 'JetBrains Mono',monospace;
      color:#9ec4e7;
    }
    .score-component-pill{
      display:inline-flex;
      align-items:center;
      padding:4px 8px;
      border-radius:999px;
      border:1px solid #365f85;
      background:#12314b;
    }
    .score-component-bar{
      position:relative;
      height:10px;
      border-radius:999px;
      overflow:hidden;
      background:#0a1826;
      border:1px solid #264767;
    }
    .score-component-bar > span{
      display:block;
      height:100%;
      border-radius:999px;
      background:linear-gradient(90deg,#5bc0ff,#69f0c2);
    }
    .score-contrib-list{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
      gap:8px;
    }
    .score-contrib{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:10px;
      padding:8px 10px;
      border-radius:10px;
      border:1px solid #284d70;
      background:#0c2236;
    }
    .score-contrib-label{
      color:#dceefe;
      font-size:.8rem;
      line-height:1.35;
    }
    .score-contrib-points{
      flex-shrink:0;
      color:#7fe6b4;
      font:700 .76rem 'JetBrains Mono',monospace;
    }
    .score-empty-note{
      padding:9px 10px;
      border-radius:10px;
      border:1px dashed #315a80;
      color:#9ec4e7;
      font-size:.8rem;
      background:#0c2236;
    }
    .score-detail-json pre{
      margin:0;
      max-height:420px;
      overflow:auto;
    }
    .event-context{margin-top:0}
    .event-context h3{margin:0 0 10px;font-size:.92rem}
    .event-context pre{margin:0;padding:10px;border-radius:10px;border:1px solid #2d5377;background:#081929;white-space:pre-wrap;font:.74rem 'JetBrains Mono',monospace;line-height:1.45;overflow-wrap:anywhere}
    .event-context.event-context-exact pre{max-height:none}
    .event-context mark{background:#ffd26666;color:#fff;border-radius:3px;padding:0 2px}
    .event-raw{margin-top:0}
    .event-raw summary{
      cursor:pointer;
      color:#dff1ff;
      font:700 .82rem 'Sora',sans-serif;
      list-style:none;
    }
    .event-raw summary::-webkit-details-marker{display:none}
    .event-raw pre{margin:8px 0 0;padding:10px;border:1px solid #2d5377;border-radius:10px;max-height:420px;overflow:auto;background:#081929;font:.73rem 'JetBrains Mono',monospace;white-space:pre-wrap;line-height:1.45}
    body.template-corona .event-workbench{
      --event-feed-width: clamp(300px, 24vw, 360px);
      display:grid !important;
      grid-template-columns:minmax(300px, var(--event-feed-width)) minmax(0, 1fr) !important;
      gap:20px !important;
      align-items:start !important;
      width:100% !important;
    }
    body.template-corona .event-workbench > *{
      min-width:0 !important;
      flex:none !important;
    }
    body.template-corona #event-detail{
      display:grid !important;
      width:100% !important;
    }
    .ops-main,
    .ops-main > .card,
    #event-workbench{
      width:100% !important;
      min-width:0 !important;
      max-width:none !important;
    }
    body.template-corona .event-grid{
      display:grid !important;
    }
    body.template-corona .event-columns{
      display:grid !important;
      grid-template-columns:repeat(2,minmax(320px,1fr)) !important;
      gap:12px !important;
      width:100% !important;
    }
    body.template-corona #event-evidence{
      grid-template-columns:repeat(2,minmax(280px,1fr)) !important;
    }
    body.template-corona .event-columns > *{
      min-width:0 !important;
      flex:none !important;
    }
    @media (min-width: 1280px){
      .event-workbench,
      body.template-corona .event-workbench{
        --event-feed-width: clamp(320px, 23vw, 380px);
        grid-template-columns:minmax(320px, var(--event-feed-width)) minmax(0, 1fr) !important;
      }
      #event-detail{
        grid-template-columns:minmax(0,1fr) !important;
        align-items:start;
      }
      #event-detail > *{
        grid-column:1 / -1 !important;
      }
      #event-detail > .event-grid:first-of-type{
        grid-template-columns:repeat(4,minmax(180px,1fr)) !important;
      }
      #event-detail > .event-columns{
        grid-template-columns:repeat(2,minmax(320px,1fr)) !important;
      }
      #event-detail > #event-evidence{
        grid-template-columns:repeat(2,minmax(320px,1fr)) !important;
      }
      .event-context pre{
        min-height:320px;
      }
    }
    @media (min-width: 1500px){
      .event-workbench{
        --event-feed-width: clamp(330px, 22vw, 400px);
        grid-template-columns:minmax(330px, var(--event-feed-width)) minmax(0, 1fr) !important;
      }
      body.template-corona .event-workbench{
        --event-feed-width: clamp(330px, 22vw, 400px);
        grid-template-columns:minmax(330px, var(--event-feed-width)) minmax(0, 1fr) !important;
      }
      #event-detail > .event-grid:first-of-type{
        grid-template-columns:repeat(4,minmax(220px,1fr)) !important;
      }
      #event-detail > .event-columns{
        grid-template-columns:repeat(2,minmax(360px,1fr)) !important;
      }
      #event-detail > #event-evidence{
        grid-template-columns:repeat(2,minmax(380px,1fr)) !important;
      }
      .event-columns > div{
        min-height:260px;
      }
    }
    @media (max-width: 980px){
      .event-workbench{
        grid-template-columns:1fr;
      }
      body.template-corona .event-workbench{
        grid-template-columns:1fr !important;
      }
      .event-feed{
        position:static;
        top:auto;
        max-height:420px;
      }
      .ops-side,
      .ops-side-grid{
        grid-template-columns:1fr;
      }
      #event-detail > .event-grid:first-of-type,
      .event-grid,
      .event-columns{
        grid-template-columns:1fr;
      }
      #event-detail > .event-grid:first-of-type::after{
        position:static;
        grid-column:1 / -1;
        margin-top:-2px;
        margin-bottom:4px;
      }
      .event-badges{justify-content:flex-start}
    }
    .session-timeout-modal{position:fixed;inset:0;background:rgba(5,12,22,.78);display:flex;align-items:center;justify-content:center;z-index:9999}
    .session-timeout-modal[hidden]{display:none}
    .session-timeout-card{max-width:420px;width:92%;border-radius:14px;padding:16px;border:1px solid #2d557a;background:linear-gradient(160deg,#0c2236,#0b1c2c);box-shadow:0 20px 50px rgba(0,0,0,.45)}
    .session-timeout-card h3{margin:0 0 6px 0}
    .session-timeout-card .mut{font-size:.86rem}
    .legacy-events{margin-top:8px}
    .legacy-events summary{cursor:pointer;color:#cbeefd}
    .mitre-blueprint{margin-top:8px;border:1px solid #2d557a;border-radius:12px;padding:10px;background:linear-gradient(160deg,#0c2236,#0b1c2c)}
    .mitre-blueprint h4{margin:0 0 6px 0;font-size:.9rem}
    .mitre-blueprint-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px}
    .mitre-tactic{border:1px solid #2a4b69;border-radius:10px;padding:8px;background:#0e2337;display:grid;gap:6px}
    .mitre-tactic-title{font:.7rem 'JetBrains Mono',monospace;color:#9dc3e1;text-transform:uppercase;letter-spacing:.12em}
    .mitre-tech-list{display:flex;flex-wrap:wrap;gap:6px}
    .mitre-tech{padding:4px 6px;border-radius:8px;border:1px solid #3c6a8f;background:#10304b;font:.7rem 'JetBrains Mono',monospace;color:#d7ecff}
    .mitre-tech b{display:block;font-size:.68rem;color:#87c7ff}
    .mitre-empty{color:var(--mut);font-size:.8rem}
    .intel-shell{display:grid;gap:12px}
    .intel-topbar{
      border:1px solid #2b5378;
      border-radius:14px;
      padding:12px;
      background:linear-gradient(155deg,#0f2842,#0c2136 62%,#0a1c2d);
      display:grid;
      gap:10px;
    }
    .intel-topline{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:10px;
      flex-wrap:wrap;
    }
    .intel-title-wrap h2{margin:0}
    .intel-title-wrap p{margin:4px 0 0;color:#b5d1ea}
    .intel-chip-row{display:flex;flex-wrap:wrap;gap:7px}
    .intel-chip{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:3px 9px;
      border-radius:999px;
      border:1px solid #39658e;
      background:#12304a;
      font:.69rem 'JetBrains Mono',monospace;
      color:#d1e8ff;
    }
    .intel-chip.critical{border-color:#8e3f51;background:#3a1d28;color:#ffd7dd}
    .intel-chip.ok{border-color:#3f7d66;background:#17392f;color:#c9ffea}
    .intel-chip.warn{border-color:#80643b;background:#392f1b;color:#ffeecb}
    .intel-kpi-grid{
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:8px;
    }
    .intel-kpi{
      border:1px solid #2e557b;
      border-radius:11px;
      background:#0f2740;
      padding:9px;
    }
    .intel-kpi b{
      display:block;
      font:.7rem 'JetBrains Mono',monospace;
      color:#a8c6e4;
      margin-bottom:5px;
      letter-spacing:.2px;
      text-transform:uppercase;
    }
    .intel-kpi span{
      font:700 .95rem 'Manrope',sans-serif;
      color:#ecf7ff;
      word-break:break-word;
    }
    .intel-stage-bar{
      display:grid;
      grid-template-columns:repeat(5,minmax(0,1fr));
      gap:8px;
    }
    .intel-stage{
      border:1px solid #2f567d;
      border-radius:10px;
      background:#0d2439;
      padding:8px;
      text-align:center;
      font:.72rem 'JetBrains Mono',monospace;
      color:#c5def5;
    }
    .intel-stage.active{
      border-color:#4de09f99;
      background:#154159;
      color:#e8fff6;
      font-weight:700;
    }
    .intel-layout{display:grid;grid-template-columns:minmax(280px,340px) minmax(0,1fr);gap:12px}
    .intel-layout.workspace-only{grid-template-columns:minmax(0,1fr)}
    .intel-selector-shell{
      display:grid;
      gap:14px;
      border:1px solid #2b5378;
      border-radius:16px;
      background:linear-gradient(155deg,#0a1f31,#0b2438);
      padding:16px;
      box-shadow:0 24px 60px -44px #000;
    }
    .intel-selector-head{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:14px;
      flex-wrap:wrap;
    }
    .intel-selector-head h3{margin:0 0 6px}
    .intel-selector-head p{margin:0;max-width:720px}
    .intel-selector-grid{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:12px;
      align-items:start;
    }
    .intel-selector-column{
      display:grid;
      gap:10px;
      border:1px solid #294c6d;
      border-radius:14px;
      background:linear-gradient(160deg,#0d253a,#0c2235);
      padding:12px;
      min-height:100%;
    }
    .intel-selector-column h4{margin:0}
    .intel-selector-column .mut{margin:0}
    .intel-picker-stack{display:grid;gap:10px}
    .intel-picker-card{
      display:grid;
      gap:8px;
      padding:12px;
      border:1px solid #2f567d;
      border-radius:12px;
      background:#10283f;
      box-shadow:inset 0 1px 0 #ffffff08;
    }
    .intel-picker-card strong{display:block;font-size:.96rem;color:#f5fbff}
    .intel-picker-card .mono{font-size:.72rem}
    .intel-picker-card .summary{
      font-size:.82rem;
      color:#d4e9fa;
      display:-webkit-box;
      -webkit-line-clamp:3;
      -webkit-box-orient:vertical;
      overflow:hidden;
    }
    .intel-picker-actions{display:flex;gap:8px;flex-wrap:wrap}
    .intel-picker-actions .btn{width:auto}
    .intel-empty-state{
      padding:18px 14px;
      border:1px dashed #3c668c;
      border-radius:12px;
      background:#0d2438;
      color:#bedbf0;
    }
    .intel-selector-v2{
      border-color:#2f5b84;
      background:
        radial-gradient(circle at top left, rgba(68,151,218,.12), transparent 55%),
        linear-gradient(155deg,#0a1f31,#0b2236 60%,#0a1f31);
      padding:18px;
    }
    .intel-focus-hero{
      display:grid;
      grid-template-columns:minmax(0,1fr) minmax(220px,260px);
      gap:16px;
      align-items:start;
    }
    .intel-focus-eyebrow{
      font-size:.7rem;
      letter-spacing:.16em;
      text-transform:uppercase;
      color:#6db3ff;
      margin-bottom:6px;
    }
    .intel-focus-steps{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      margin-top:10px;
    }
    .intel-step{
      padding:4px 10px;
      border-radius:999px;
      border:1px solid #295d84;
      font-size:.72rem;
      color:#b7d8f7;
      background:#0f2840;
    }
    .intel-step.active{
      border-color:#5ad0ff;
      color:#e7f7ff;
      box-shadow:0 0 0 1px #5ad0ff33 inset;
    }
    .intel-focus-meta{
      display:grid;
      gap:10px;
      border:1px solid #2b567d;
      border-radius:14px;
      padding:12px;
      background:#0f2740;
    }
    .intel-stat{
      display:flex;
      align-items:baseline;
      justify-content:space-between;
      gap:8px;
      padding:6px 8px;
      border-radius:10px;
      background:#0b2135;
      border:1px solid #234d72;
    }
    .intel-stat .k{font-size:.7rem;color:#9fbfe0;text-transform:uppercase;letter-spacing:.12em}
    .intel-stat .v{font-size:1.15rem;font-weight:700;color:#f5fbff}
    .intel-focus-search{
      display:grid;
      gap:6px;
      font-size:.72rem;
      color:#b8d5ef;
    }
    .intel-focus-search input{
      width:100%;
      padding:9px 10px;
      border-radius:10px;
      border:1px solid #2a587f;
      background:#0a1b2a;
      color:#e6f4ff;
    }
    .intel-focus-tabs{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      margin-top:10px;
    }
    .intel-tab{
      border:1px solid #2b567d;
      background:#0c2236;
      color:#c7def4;
      padding:8px 12px;
      border-radius:10px;
      font-weight:600;
      cursor:pointer;
    }
    .intel-tab.is-active{
      background:#14324c;
      border-color:#64b5ff;
      color:#f2f9ff;
      box-shadow:0 0 0 1px #64b5ff33 inset;
    }
    .intel-focus-panels{margin-top:10px;display:grid;gap:12px}
    .intel-panel-head{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:10px;
      flex-wrap:wrap;
    }
    .intel-panel-head h4{margin:0}
    .intel-panel-chip{
      padding:6px 10px;
      border-radius:999px;
      font-size:.72rem;
      border:1px solid #2b5a82;
      background:#0c2338;
      color:#b9d6f0;
    }
    .intel-focus-list{display:grid;gap:10px}
    .intel-focus-card{
      display:grid;
      gap:10px;
      padding:14px;
      border-radius:14px;
      border:1px solid #2f567d;
      background:#0f263c;
      box-shadow:inset 0 1px 0 #ffffff08;
    }
    .intel-focus-card.hero{
      background:linear-gradient(145deg,#102a42,#0c2032);
      border-color:#3b6e9a;
    }
    .intel-focus-main strong{
      display:block;
      font-size:1rem;
      color:#f5fbff;
      margin-bottom:6px;
    }
    .intel-meta-row{
      display:flex;
      gap:6px;
      flex-wrap:wrap;
      align-items:center;
      font-size:.72rem;
      color:#b9d7ef;
    }
    .intel-badge{
      padding:3px 8px;
      border-radius:999px;
      border:1px solid #2a587f;
      background:#0b2135;
      color:#c4dcf2;
      text-transform:lowercase;
    }
    .intel-badge.score{border-color:#4aa6ff;color:#e6f4ff;background:#103251}
    .intel-badge.soft{opacity:.9}
    .intel-badge.critical{border-color:#a94a56;color:#ffd2d8;background:#3f1822}
    .intel-focus-summary .summary{
      font-size:.82rem;
      color:#d4e9fa;
      display:-webkit-box;
      -webkit-line-clamp:3;
      -webkit-box-orient:vertical;
      overflow:hidden;
    }
    .intel-summary-details{
      margin-top:6px;
      font-size:.75rem;
      color:#b8d5ef;
    }
    .intel-summary-details summary{
      cursor:pointer;
      color:#8ac5ff;
    }
    .intel-focus-actions{display:flex;gap:8px;flex-wrap:wrap}
    .intel-focus-card.is-hidden{display:none}
    .intel-focus-bar{
      position:sticky;
      top:calc(var(--sticky-header-offset) + 8px);
      z-index:18;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      margin:12px 0;
      padding:11px 12px;
      border:1px solid #315c82;
      border-radius:13px;
      background:linear-gradient(155deg,rgba(14,38,58,.96),rgba(11,31,49,.96));
      backdrop-filter:blur(14px);
      box-shadow:0 18px 40px -34px #000;
    }
    .intel-focus-main{display:grid;gap:4px}
    .intel-focus-main strong{font-size:1rem;color:#f6fbff}
    .intel-focus-main span{color:#bddcff;font:.76rem 'JetBrains Mono',monospace}
    .intel-focus-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .intel-focus-actions .btn{width:auto}
    .intel-cockpit{
      display:grid;
      grid-template-columns:repeat(6,minmax(0,1fr));
      gap:10px;
      margin:0 0 12px;
    }
    .intel-cockpit-card{
      border:1px solid #2c5478;
      border-radius:14px;
      background:linear-gradient(160deg,#0f2a42,#0a1f31);
      padding:12px;
      display:grid;
      gap:6px;
      min-height:108px;
      box-shadow:0 16px 32px -28px #000;
    }
    .intel-cockpit-card .k{
      font:.68rem 'JetBrains Mono',monospace;
      color:#9fc2de;
      text-transform:uppercase;
      letter-spacing:.04em;
    }
    .intel-cockpit-card .v{
      font:700 1.08rem 'Sora',sans-serif;
      color:#f2faff;
      word-break:break-word;
    }
    .intel-cockpit-card .mut{margin:0}
    .intel-cockpit-card.actions{
      grid-column:span 2;
      align-content:start;
    }
    .intel-quick-grid{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
    }
    .intel-quick-grid .btn{width:auto}
    .intel-workspace-nav{
      position:sticky;
      top:calc(var(--sticky-header-offset) + 8px);
      z-index:17;
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      margin:0 0 12px;
      padding:10px;
      border:1px solid #315a7f;
      border-radius:13px;
      background:linear-gradient(155deg,rgba(14,36,55,.94),rgba(10,27,43,.94));
      backdrop-filter:blur(14px);
      box-shadow:0 16px 38px -34px #000;
    }
    .intel-workspace-nav .btn{
      width:auto;
      min-width:0;
      padding:8px 12px;
      border-radius:999px;
    }
    .intel-workspace-nav .btn.active{
      border-color:#4de09f99;
      background:#154159;
      color:#effff7;
    }
    .intel-section-head{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      margin-bottom:10px;
    }
    .intel-section-head h3{margin:0}
    .intel-section-head p{margin:4px 0 0}
    .intel-section-kicker{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:4px 9px;
      border:1px solid #3d6b93;
      border-radius:999px;
      background:#12314a;
      color:#d5eeff;
      font:.68rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.04em;
    }
    .intel-list{
      display:flex;
      flex-direction:column;
      gap:8px;
      max-height:840px;
      overflow:auto;
      padding-right:4px;
      border:1px solid #2b5378;
      border-radius:12px;
      background:#0a1d2f;
      padding:10px;
    }
    .intel-list-head{
      position:sticky;
      top:0;
      z-index:2;
      border:1px solid #2f567d;
      border-radius:10px;
      background:linear-gradient(160deg,#11314c,#0e273e);
      padding:8px 9px;
      margin-bottom:2px;
    }
    .intel-list-head b{display:block}
    .intel-list-head span{color:#b8d4ec;font-size:.78rem}
    .intel-item{
      display:block;
      text-decoration:none;
      color:var(--txt);
      padding:10px 11px;
      border:1px solid #2f567d;
      border-radius:11px;
      background:#0d243a;
      transition:.14s ease;
    }
    .intel-item:hover{border-color:#53a8db;background:#12304a}
    .intel-item.active{border-color:#4de09f99;background:#154159}
    .intel-item b{display:block}
    .intel-item .meta{font:.7rem 'JetBrains Mono',monospace;color:var(--mut);margin-top:3px}
    .intel-item .summary{font-size:.8rem;color:#d9eafc;margin-top:5px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .intel-editor{
      border:1px solid #2b5378;
      border-radius:12px;
      padding:12px;
      background:linear-gradient(160deg,#0b2135,#091b2b);
    }
    .intel-editor h2{margin-bottom:8px}
    .intel-editor-section{
      border:1px solid #2d5277;
      border-radius:11px;
      background:#0f2740;
      padding:10px;
      margin-bottom:10px;
    }
    [data-intel-section]{scroll-margin-top:calc(var(--sticky-header-offset) + 110px)}
    .intel-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .intel-grid-full{grid-column:1 / -1}
    .intel-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}
    .intel-toolbar button{width:auto}
    .intel-workbench-panel{
      display:grid;
      gap:12px;
      margin:14px 0 18px;
      padding:12px;
      border:1px solid #2f5579;
      border-radius:14px;
      background:linear-gradient(160deg,#0d243a,#0b1d2f);
    }
    .intel-workbench-grid{
      display:grid;
      grid-template-columns:minmax(0,1.3fr) minmax(260px,.9fr);
      gap:12px;
      align-items:start;
    }
    .intel-workbench-side{display:grid;gap:8px}
    .intel-workbench-note{
      margin:0;
      color:#b6d2eb;
      font-size:.78rem;
      line-height:1.45;
    }
    .intel-workbench-provider-list{display:flex;flex-wrap:wrap;gap:8px}
    .intel-workbench-provider-list label{
      gap:6px;
      padding:7px 10px;
      border-radius:10px;
      border:1px solid #335d83;
      background:#102741;
      font:.72rem 'JetBrains Mono',monospace;
      color:#d3e9ff;
    }
    .intel-workbench-provider-list input[type=checkbox]{accent-color:#5fd8ff}
    .intel-workbench-decode{
      border:1px solid #2f5579;
      border-radius:10px;
      background:#0d2237;
      padding:8px;
      display:grid;
      gap:8px;
    }
    .intel-workbench-decode summary{
      list-style:none;
      cursor:pointer;
      font:700 .82rem 'Manrope',sans-serif;
      color:#d8ecff;
    }
    .intel-workbench-decode summary::-webkit-details-marker{display:none}
    .intel-workbench-decode[open] summary{
      border-bottom:1px solid #2f5579;
      padding-bottom:6px;
    }
    .intel-workbench-suggestions{
      display:grid;
      gap:6px;
    }
    .intel-workbench-kpis{
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:8px;
    }
    .intel-workbench-kpi{
      padding:8px 9px;
      border:1px solid #30577b;
      border-radius:11px;
      background:#0f2740;
      min-height:72px;
    }
    .intel-workbench-kpi b{
      display:block;
      margin-bottom:5px;
      font:.66rem 'JetBrains Mono',monospace;
      color:#a8c6e4;
      text-transform:uppercase;
      letter-spacing:.05em;
    }
    .intel-workbench-kpi span{
      display:block;
      font:700 1.05rem 'Sora',sans-serif;
      color:#edf7ff;
    }
    .intel-artifact-grid,
    .intel-batch-grid,
    .intel-decode-grid{display:grid;gap:8px}
    .intel-artifact-grid{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    .intel-artifact-card,
    .intel-batch-card,
    .intel-decode-card{
      border:1px solid #30577b;
      border-radius:12px;
      background:#0f2740;
      padding:10px;
      display:grid;
      gap:6px;
    }
    .intel-artifact-type{
      display:inline-flex;
      align-items:center;
      width:max-content;
      padding:2px 8px;
      border-radius:999px;
      border:1px solid #47719a;
      background:#14324d;
      font:.66rem 'JetBrains Mono',monospace;
      color:#d3ebff;
      text-transform:uppercase;
    }
    .intel-artifact-value{
      margin:0;
      color:#f3fbff;
      font:600 .8rem 'JetBrains Mono',monospace;
      word-break:break-word;
    }
    .intel-decode-card pre,
    .intel-batch-card pre{
      margin:0;
      padding:8px;
      border-radius:10px;
      border:1px solid #284a69;
      background:#091a2a;
      max-height:180px;
      overflow:auto;
      white-space:pre-wrap;
      font:.72rem 'JetBrains Mono',monospace;
    }
    .intel-batch-head{
      display:flex;
      justify-content:space-between;
      gap:8px;
      align-items:flex-start;
      flex-wrap:wrap;
    }
    .intel-batch-status{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:3px 8px;
      border-radius:999px;
      border:1px solid #3b6e96;
      background:#13304a;
      font:.67rem 'JetBrains Mono',monospace;
      color:#d9efff;
    }
    .intel-batch-status.ok{border-color:#3f7d66;background:#17392f;color:#cbffea}
    .intel-batch-status.ko{border-color:#8e3f51;background:#3a1d28;color:#ffd7dd}
    .intel-map-shell{display:grid;gap:10px}
    .intel-map-toolbar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      padding:8px 10px;
      border:1px solid #315c82;
      border-radius:11px;
      background:linear-gradient(155deg,#0d253a,#102e49);
    }
    .intel-map-toolbar-group{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .intel-map-toolbar label{margin:0;font:.72rem 'JetBrains Mono',monospace;color:#bddcff}
    .intel-map-toolbar select{width:auto;min-width:180px;max-width:240px}
    .intel-map-toolbar button{width:auto;min-width:0}
    .intel-map-toolbar .map-stat{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:5px 10px;
      border-radius:999px;
      border:1px solid #3e6e98;
      background:#12314a;
      color:#d5eeff;
      font:.7rem 'JetBrains Mono',monospace;
    }
    .intel-canvas-wrap{position:relative;height:470px;border:1px solid #2f577f;border-radius:11px;background:radial-gradient(circle at 18% 12%,#214865 0,#0d2438 42%,#091728 100%);overflow:hidden;touch-action:none}
    .intel-canvas-wrap svg{position:absolute;inset:0;width:100%;height:100%}
    .intel-node-layer{position:absolute;inset:0;transform-origin:0 0}
    .intel-canvas-wrap.is-panning{cursor:grabbing}
    .intel-canvas-wrap.is-fullscreen{
      height:100vh;
      border-radius:0;
      border-color:#4a7aa1;
    }
    .intel-canvas-dock{
      position:absolute;
      right:14px;
      bottom:14px;
      z-index:6;
      display:grid;
      gap:8px;
      width:min(100%,420px);
      padding:10px;
      border:1px solid #335d83;
      border-radius:14px;
      background:linear-gradient(160deg,rgba(9,23,36,.9),rgba(13,36,56,.88));
      backdrop-filter:blur(14px);
      box-shadow:0 20px 44px -30px #000;
    }
    .intel-canvas-dock-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
    }
    .intel-canvas-dock-title{
      display:grid;
      gap:3px;
    }
    .intel-canvas-dock-title b{font-size:.9rem}
    .intel-canvas-dock-title span{font:.68rem 'JetBrains Mono',monospace;color:#b9d9ee}
    .intel-canvas-dock-actions,
    .intel-canvas-dock-tools{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:center;
    }
    .intel-canvas-dock .btn{
      width:auto;
      min-width:0;
      padding:8px 11px;
    }
    .intel-node{position:absolute;transform:translate(-50%,-50%);min-width:96px;max-width:200px;padding:8px 10px;border-radius:12px;color:#fff;font:700 .74rem 'Manrope',sans-serif;border:1px solid #ffffff55;cursor:move;user-select:none;box-shadow:0 8px 18px -12px #000a;word-break:break-word}
    .intel-node.active{outline:2px solid #ffd266;z-index:3}
    .intel-node .intel-node-label{font:700 .74rem 'Manrope',sans-serif}
    .intel-node .intel-node-meta{display:flex;gap:4px;flex-wrap:wrap;margin-top:4px}
    .intel-node .node-chip{font:.56rem 'JetBrains Mono',monospace;letter-spacing:.08em;text-transform:uppercase;padding:2px 6px;border-radius:999px;border:1px solid #ffffff44;background:#10263b;color:#d7e7fb}
    .intel-node .node-chip.source{border-color:#7bb9ff;background:#0f2b47;color:#cfe4ff}
    .intel-node .node-chip.hash{border-color:#d9b45a;background:#2d2412;color:#ffe7b6}
    .intel-node .node-chip.ioc{border-color:#6fe0a6;background:#103427;color:#d4ffe8}
    .intel-node .node-chip.artifact{border-color:#ff9f6b;background:#3a1d10;color:#ffe0cf}
    .intel-node .node-chip.vt{border-color:#ff9f43;background:#3a2412;color:#ffe2c2}
    .intel-edge-label{position:absolute;transform:translate(-50%,-50%);font:.68rem 'JetBrains Mono',monospace;color:#d6ebf8;background:#10314a;border:1px solid #3d6991;padding:2px 5px;border-radius:6px;pointer-events:none}
    .intel-side{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:10px}
    .intel-side h3{margin:.2rem 0}
    .intel-side .card-box{padding:8px;border:1px solid #2d5277;border-radius:10px;background:#0f2740}
    .intel-side .card-box button{margin-top:6px}
    .intel-share{margin-top:10px;padding:8px;border:1px solid #2ea579;border-radius:10px;background:#103b3f}
    .intel-share a{color:#9de9ff;word-break:break-all}
    .intel-api-map-insights{
      border:1px solid #2e557c;
      border-radius:10px;
      background:linear-gradient(155deg,#102940,#0c2438);
      padding:8px;
      margin:6px 0 10px;
      display:grid;
      gap:7px;
    }
    .intel-api-map-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      flex-wrap:wrap;
      font:.72rem 'JetBrains Mono',monospace;
      color:#cbe7ff;
    }
    .intel-api-keywords{
      display:flex;
      gap:6px;
      flex-wrap:wrap;
      align-items:center;
      min-height:28px;
    }
    .intel-api-keyword-chip{
      display:inline-flex;
      align-items:center;
      gap:6px;
      border:1px solid #44729b;
      border-radius:999px;
      padding:3px 10px;
      background:#12314a;
      color:#dcf2ff;
      font:.68rem 'JetBrains Mono',monospace;
      line-height:1;
    }
    .intel-api-keyword-chip b{
      font:700 .67rem 'JetBrains Mono',monospace;
      color:#ffd98e;
    }
    .api-key-row-form{
      display:flex;
      gap:6px;
      align-items:center;
    }
    .api-key-row-form input[type="password"],
    .api-key-row-form input[type="text"]{
      min-width:220px;
    }
    .api-key-toggle{
      width:auto;
      padding:7px 9px;
      border-radius:10px;
      border:1px solid #3a5f88;
      background:linear-gradient(150deg,#102742,#0d2137);
      color:var(--txt);
      font:600 .72rem 'JetBrains Mono',monospace;
      cursor:pointer;
    }
    .api-key-delete-form{display:inline-flex}
    .api-result{
      margin-top:8px;
      border:1px solid #2f5579;
      border-radius:10px;
      background:#0d2237;
      padding:8px;
    }
    .api-result pre{
      margin:6px 0 0;
      max-height:230px;
      overflow:auto;
      border:1px solid #2f5579;
      border-radius:8px;
      background:#091828;
      padding:8px;
      font:.72rem 'JetBrains Mono',monospace;
      white-space:pre-wrap;
      word-break:break-word;
    }
    .vt-summary{
      margin-top:8px;
      border:1px solid #2f5579;
      border-radius:8px;
      background:#0a1d2f;
      padding:8px;
      display:grid;
      gap:8px;
    }
    .vt-summary-head{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      align-items:center;
      justify-content:space-between;
      font:.72rem 'JetBrains Mono',monospace;
      color:#c8e5ff;
    }
    .vt-stat-grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(120px,1fr));
      gap:6px;
    }
    .vt-stat-chip{
      border:1px solid #345f84;
      border-radius:10px;
      padding:7px;
      display:grid;
      gap:4px;
      background:#10273d;
      font:.72rem 'JetBrains Mono',monospace;
    }
    .vt-stat-chip span{opacity:.95}
    .vt-stat-chip b{font:800 .94rem 'Sora',sans-serif}
    .vt-stat-malicious{border-color:#8e3444;background:#351422;color:#ffd4dc}
    .vt-stat-suspicious{border-color:#8c7639;background:#332812;color:#ffe9be}
    .vt-stat-harmless{border-color:#2f7a58;background:#123427;color:#c9ffe3}
    .vt-stat-undetected{border-color:#3a5f86;background:#10263b;color:#cde6ff}
    .api-lookup-history{
      margin-top:10px;
      border:1px solid #2f5579;
      border-radius:10px;
      background:#0d2237;
      display:grid;
      gap:8px;
    }
    .api-lookup-history summary{
      list-style:none;
      cursor:pointer;
      margin:0;
      padding:8px;
      font:700 .86rem 'Manrope',sans-serif;
      color:#d8ecff;
    }
    .api-lookup-history summary::-webkit-details-marker{display:none}
    .api-lookup-history[open] summary{
      border-bottom:1px solid #2f5579;
    }
    .api-lookup-history > *:not(summary){
      padding:0 8px 8px 8px;
    }
    .api-lookup-item{
      border:1px solid #2f5579;
      border-radius:8px;
      background:#0a1d2e;
      overflow:hidden;
    }
    .api-lookup-item summary{
      list-style:none;
      cursor:pointer;
      padding:8px;
      display:grid;
      gap:4px;
    }
    .api-lookup-item summary::-webkit-details-marker{display:none}
    .api-lookup-meta{
      font:.72rem 'JetBrains Mono',monospace;
      color:#b4d9f7;
      word-break:break-word;
    }
    .api-lookup-status-ok{color:#89e6b7}
    .api-lookup-status-ko{color:#ffb0b0}
    .api-lookup-body{
      border-top:1px solid #2f5579;
      padding:8px;
      display:grid;
      gap:8px;
    }
    .api-lookup-body pre{
      margin:0;
      max-height:260px;
      overflow:auto;
      border:1px solid #2f5579;
      border-radius:8px;
      background:#091828;
      padding:8px;
      font:.72rem 'JetBrains Mono',monospace;
      white-space:pre-wrap;
      word-break:break-word;
    }
    .intel-accordion{
      border:1px solid #284968;
      border-radius:12px;
      background:#0a1926;
      padding:8px 12px;
      margin-top:10px;
    }
    .intel-accordion > summary{
      cursor:pointer;
      font:700 .8rem 'Manrope',sans-serif;
      color:#dcecff;
      list-style:none;
    }
    .intel-accordion > summary::-webkit-details-marker{display:none}
    .intel-accordion[open]{
      box-shadow:0 10px 24px -18px #000a;
    }
    .vt-reported-wrap{
      margin-top:10px;
      border:1px solid #2f5579;
      border-radius:10px;
      background:#0d2237;
      padding:8px;
      display:grid;
      gap:8px;
    }
    .vt-reported-kpis{
      display:grid;
      gap:6px;
      grid-template-columns:repeat(auto-fit,minmax(130px,1fr));
    }
    .vt-reported-kpi{
      border:1px solid #325b80;
      border-radius:9px;
      background:#102a43;
      padding:7px;
      display:grid;
      gap:3px;
    }
    .vt-reported-kpi .k{
      font:.66rem 'JetBrains Mono',monospace;
      color:#a9cbe7;
      text-transform:uppercase;
    }
    .vt-reported-kpi .v{
      font:800 1.02rem 'Sora',sans-serif;
      color:#e8f6ff;
    }
    .vt-reported-domain-table{
      max-height:260px;
      overflow:auto;
      border:1px solid #2f5579;
      border-radius:8px;
    }
    .vt-domain-badge{
      display:inline-flex;
      align-items:center;
      gap:4px;
      padding:2px 8px;
      border-radius:999px;
      border:1px solid #3f688c;
      background:#10263b;
      font:700 .66rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.2px;
    }
    .vt-domain-badge.malicious{border-color:#a94a56;color:#ffd2d8;background:#3f1822}
    .vt-domain-badge.suspicious{border-color:#8f7844;color:#ffebbe;background:#3e3014}
    .vt-domain-badge.harmless_or_undetected{border-color:#377a57;color:#d7ffea;background:#123626}
    .intel-timeline{
      border:1px solid #2d5277;
      border-radius:11px;
      background:#0f2740;
      padding:10px;
      margin-top:10px;
    }
    .intel-timeline h3{margin:0 0 8px}
    .intel-timeline-list{display:grid;gap:8px;max-height:380px;overflow:auto;padding-right:2px}
    .intel-event-card{
      border:1px solid #2e557b;
      border-radius:10px;
      background:#0b2135;
      padding:8px;
      display:grid;
      gap:5px;
    }
    .intel-event-head{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:8px;
      flex-wrap:wrap;
      font:.72rem 'JetBrains Mono',monospace;
      color:#b7d3eb;
    }
    .intel-event-action{
      display:inline-flex;
      align-items:center;
      gap:4px;
      padding:2px 7px;
      border:1px solid #3a648b;
      border-radius:999px;
      background:#12304a;
      color:#d6ecff;
      font:.67rem 'JetBrains Mono',monospace;
    }
    .intel-event-detail{
      font:.74rem 'JetBrains Mono',monospace;
      color:#dceeff;
      line-height:1.35;
      white-space:pre-wrap;
      word-break:break-word;
    }
    .intel-public{max-width:1760px;margin:18px auto;padding:0 12px}
    .intel-public .card{margin-bottom:10px}
    .intel-public-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
    .intel-public-meta .m{padding:8px;border:1px solid #2e5378;border-radius:9px;background:#102842}
    .rbac{margin-top:8px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
    .rbac .item{padding:8px;border-radius:10px;border:1px solid #2f5579;background:#102842}
    .rbac .item b{display:block;font-size:.82rem}
    .rbac .item span{color:#bcdced;font-size:.78rem}
    .geo-map{height:360px;border:1px solid #2e5378;border-radius:10px;overflow:hidden;background:#0f2236}
    .geo-map-legend{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 0}
    .geo-chip{padding:3px 8px;border-radius:999px;border:1px solid #355b82;background:#102742;font:.7rem 'JetBrains Mono',monospace}
    .geo-chip b{color:#fff}
    .geo-subtitle{margin:0 0 8px;color:#b8d1ea}
    .geo-table-wrap{max-height:none;overflow:visible;margin-top:8px}
    .extensions-group-grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
      gap:12px;
      margin:0 0 14px;
      align-items:start;
    }
    .extensions-group-card{
      border:1px solid #2b4f73;
      border-radius:14px;
      background:#0f2236;
      padding:12px;
      min-width:0;
      align-self:start;
    }
    .extensions-group-head{
      display:flex;
      justify-content:space-between;
      gap:10px;
      align-items:flex-start;
      margin-bottom:10px;
      flex-wrap:wrap;
    }
    .extensions-group-head h3{margin:0;font-size:.95rem}
    .extensions-group-list{display:grid;gap:8px}
    .extensions-group-item{
      display:flex;
      justify-content:space-between;
      gap:12px;
      align-items:flex-start;
      border:1px solid #284865;
      border-radius:12px;
      padding:10px 12px;
      background:#10253b;
      min-width:0;
    }
    .extensions-group-item .mut{margin-top:4px;word-break:break-word}
    .extensions-group-metrics{
      display:grid;
      gap:4px;
      text-align:right;
      white-space:nowrap;
    }
    .extensions-table-wrap{overflow:auto}
    .extensions-clients-table{min-width:1120px}
    .trend-bar{height:8px;border-radius:999px;background:#183f5d;overflow:hidden}
    .trend-bar > span{display:block;height:100%;border-radius:999px}
    .trend-bar.alerts > span{background:#14b8ff}
    .trend-bar.blocks > span{background:#38d17a}
    .mut-mini{font-size:.75rem;color:#9fb6d1}
    .chart-stack{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:8px 0 10px}
    .chart-card{
      border:1px solid #2f5579;
      border-radius:10px;
      background:linear-gradient(160deg,#0f2a43,#0b2035);
      padding:8px;
    }
    .analytics-kpi-grid{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:8px;
      margin:8px 0;
    }
    .analytics-kpi{
      border:1px solid #2f5579;
      border-radius:10px;
      background:linear-gradient(160deg,#102941,#0c2237);
      padding:8px;
    }
    .analytics-kpi .k{font:.66rem 'JetBrains Mono',monospace;color:#a7c6e3}
    .analytics-kpi .v{font:700 1.05rem 'Sora',sans-serif;color:#e6f3ff;margin-top:3px}
    .analytics-search{
      max-width:320px;
      margin:6px 0 8px;
    }
    .analytics-table-wrap{
      max-height:290px;
      overflow:auto;
      border:1px solid #2f5579;
      border-radius:10px;
      background:#0b2033;
    }
    .analytics-table-wrap table{margin:0}
    .analytics-table-wrap thead th{
      position:sticky;
      top:0;
      z-index:2;
      background:linear-gradient(120deg,rgba(24,49,75,.95),rgba(17,34,54,.95));
    }
    .exposure-grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
      gap:8px;
      margin-top:8px;
    }
    .exposure-kpi-grid{
      grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
    }
    .exposure-note{
      margin:6px 0 0;
      color:#a9c8e6;
      font:.74rem 'JetBrains Mono',monospace;
      line-height:1.45;
    }
    .exposure-flag-wrap{
      display:flex;
      flex-wrap:wrap;
      gap:5px;
    }
    .exposure-flag{
      display:inline-flex;
      align-items:center;
      gap:4px;
      padding:2px 8px;
      border-radius:999px;
      border:1px solid #40688e;
      background:#102842;
      color:#dcefff;
      font:.66rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.03em;
    }
    .exposure-flag.referrer_match{border-color:#7d5df6;color:#eadfff;background:#211642}
    .exposure-flag.direct_ip_overlap{border-color:#c95f5f;color:#ffd8d8;background:#3a171e}
    .exposure-flag.network_overlap{border-color:#d49a43;color:#ffe9bf;background:#3a2a14}
    .exposure-flag.proxy,.exposure-flag.hosting{border-color:#b04f6a;color:#ffd8e4;background:#391524}
    .exposure-flag.mobile{border-color:#3d7db4;color:#d2edff;background:#10293f}
    .exposure-domains{
      font:.68rem 'JetBrains Mono',monospace;
      color:#bcdced;
      line-height:1.4;
      word-break:break-word;
    }
    .compact-table th,.compact-table td{padding:5px 7px;font-size:.74rem}
    .chart-card .chart-title{margin:0 0 6px;font:.72rem 'JetBrains Mono',monospace;color:#bcd8f2}
    .chart-canvas{width:100%;height:180px;display:block;border-radius:8px;background:#0a1d30}
    .chart-legend{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
    .chart-legend .dot{width:8px;height:8px;border-radius:999px;display:inline-block;margin-right:5px}
    .chart-legend span{font:.68rem 'JetBrains Mono',monospace;color:#a7c6e3}
    .profile-shell{display:grid;grid-template-columns:minmax(280px,340px) minmax(0,1fr);gap:10px}
    .profile-card{padding:12px;border:1px solid #355a82;border-radius:12px;background:#0c253d}
    .profile-avatar{width:78px;height:78px;border-radius:999px;display:grid;place-items:center;font:800 1.5rem 'Sora',sans-serif;background:linear-gradient(145deg,#63d9ff,#57f0be);color:#032640;margin-bottom:10px}
    .profile-avatar.has-image{background:#0d2036;padding:0;overflow:hidden}
    .profile-avatar.has-image img{width:100%;height:100%;object-fit:cover;display:block}
    .profile-name{margin:0;font-size:1.15rem}
    .profile-nick{font:.8rem 'JetBrains Mono',monospace;color:#9cc6e5}
    .profile-meta{display:grid;gap:6px;margin-top:10px}
    .profile-meta .rowx{display:flex;justify-content:space-between;gap:8px;border:1px solid #2f557a;border-radius:9px;padding:6px 8px;background:#112d47}
    .profile-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
    .profile-tab{padding:6px 10px;border-radius:10px;border:1px solid #335a80;background:#112b44;color:#d9ecff;text-decoration:none;font:.78rem 'JetBrains Mono',monospace}
    .profile-tab.active{border-color:#58dcaf;background:#12483d;color:#d9fff3}
    hr{border:none;border-top:1px solid #2a4b6d;margin:10px 0}
    pre{white-space:pre-wrap;word-break:break-word}
    input,select,textarea,button{max-width:100%}
    @media(min-width:1700px){
      .grid{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
      .kpi{min-height:84px}
      .kpi .mut{font-size:.67rem}
    }
    @media(min-width:1900px){
      .workspace{grid-template-columns:minmax(0,1fr) clamp(320px,18vw,420px)}
      .dashboard-shell-row{grid-template-columns:minmax(0,1.5fr) clamp(340px, 26vw, 560px)}
    }
    @media(max-width:1680px){
      .dashboard-shell-row{grid-template-columns:minmax(0,1.45fr) clamp(320px, 28vw, 500px)}
      .side-card-grid--ops{grid-template-columns:1fr}
    }
    @media(max-width:1440px){
      .dashboard-shell-row{grid-template-columns:minmax(0,1.35fr) clamp(300px, 30vw, 420px)}
      .side-card-grid{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    }
    @media(max-width:1320px){
      .workspace{grid-template-columns:1fr}
      .side-column{position:static}
      .dashboard-shell-row{grid-template-columns:1fr}
    }
    @media(max-width:920px){
      .wrap{
        width:min(100%,99vw);
        padding:
          calc(8px + env(safe-area-inset-top))
          max(6px, env(safe-area-inset-right))
          calc(16px + env(safe-area-inset-bottom))
          max(6px, env(safe-area-inset-left));
      }
      .top{
        top:max(6px, env(safe-area-inset-top));
        padding:12px;
      }
      .app-header-main{
        gap:10px;
        align-items:center;
      }
      .app-header-brand,
      .top-status{
        min-width:0;
        width:100%;
        flex-basis:100%;
      }
      .app-header-brand{
        gap:4px;
      }
      .app-header-title{
        gap:8px;
      }
      .app-header-title b{
        font-size:.96rem;
      }
      .app-header-subline{
        display:none;
      }
      .top-status{
        justify-content:flex-start;
        flex-wrap:nowrap;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
        scrollbar-width:none;
        padding-bottom:2px;
      }
      .top-status::-webkit-scrollbar{
        display:none;
      }
      .status-chip,
      .module-chip{
        flex:0 0 auto;
        white-space:nowrap;
      }
      .nav-toggle{
        display:inline-flex;
        margin-left:auto;
      }
      .app-header-navrow{
        display:none;
        width:100%;
        padding-top:8px;
        margin-top:2px;
        max-height:calc(100dvh - 132px - env(safe-area-inset-top));
        overflow:auto;
        -webkit-overflow-scrolling:touch;
      }
      .top.is-nav-open .app-header-navrow{
        display:grid;
        gap:10px;
      }
      .header-nav{
        overflow:visible;
        flex:0 1 auto;
        width:100%;
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:8px;
        padding-bottom:0;
      }
      .header-nav a,
      .nav-actions .nav-btn,
      .module-chip,
      .status-chip{
        min-height:44px;
      }
      .header-nav a{
        width:100%;
        justify-content:center;
      }
      .nav-actions{
        width:100%;
        justify-content:flex-start;
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:8px;
      }
      .nav-actions form{
        margin:0;
        display:contents;
      }
      .nav-actions .nav-btn{
        width:100%;
        justify-content:center;
      }
      input,
      select,
      textarea,
      button{
        font-size:16px;
        min-height:44px;
      }
      textarea{min-height:110px}
      .hero-main,
      .hero-side,
      .card,
      .side-card,
      .intel-editor-section,
      .intel-selector-shell{
        padding-left:10px;
        padding-right:10px;
      }
      .geo-map{
        height:300px !important;
      }
      .chart-canvas{
        height:200px;
      }
    }
    @media(max-width:1140px){
      .grid{grid-template-columns:repeat(2,minmax(0,1fr))}
      .hero,.row,.split,.event-workbench,.event-columns,.event-grid,.intel-layout,.intel-grid,.intel-side,.intel-public-meta,.rbac,.viz-grid,.profile-shell,.chart-stack,.analytics-kpi-grid,.intel-kpi-grid,.intel-stage-bar,.intel-selector-grid,.intel-cockpit,.intel-workbench-grid,.intel-workbench-kpis{grid-template-columns:1fr}
      .app-header-navrow{align-items:flex-start}
      .nav-actions{width:100%;justify-content:flex-start}
      .intel-focus-hero{grid-template-columns:1fr}
    }
    @media(max-width:700px){
      :root{--sticky-header-offset:12px}
      .wrap{
        width:min(100%,98vw);
        padding:
          max(8px, env(safe-area-inset-top))
          max(4px, env(safe-area-inset-right))
          calc(18px + env(safe-area-inset-bottom))
          max(4px, env(safe-area-inset-left));
      }
      body{overflow-x:hidden}
      .top{
        top:max(6px, env(safe-area-inset-top));
        padding:12px;
        position:static;
      }
      .side-column{
        position:static;
      }
      .grid{grid-template-columns:1fr}
      .top-status{justify-content:flex-start}
      .nav-actions{justify-content:flex-start}
      .top,
      .card,
      .profile-card,
      .intel-editor-section,
      .event-detail-shell,
      .intel-editor,
      .intel-selector-shell,
      .intel-cockpit-card,
      .intel-topbar,
      .intel-list,
      .analytics-table-wrap{
        border-radius:14px;
      }
      .intel-focus-tabs{gap:6px}
      .intel-tab{flex:1 1 100%;text-align:center}
      .intel-focus-meta{padding:10px}
      .top,
      .card{
        padding-left:10px;
        padding-right:10px;
      }
      .app-header-navrow{
        gap:6px;
      }
      .header-nav{
        grid-template-columns:1fr;
      }
      .header-nav a,
      .nav-actions .nav-btn{
        min-height:44px;
      }
      .intel-focus-bar,
      .intel-workspace-nav{
        position:static;
        top:auto;
      }
      .nav-actions{
        width:100%;
        gap:6px;
        grid-template-columns:1fr;
      }
      .nav-actions .nav-btn,
      .bulk-review-toolbar .bulk-review-actions button,
      .intel-toolbar button,
      .intel-toolbar .btn,
      .intel-map-toolbar button,
      .intel-focus-actions .btn,
      .intel-canvas-dock .btn{
        min-height:44px;
      }
      input,
      select,
      textarea,
      button{
        font-size:16px;
        min-height:44px;
      }
      textarea{min-height:110px}
      .app-header-title b{font-size:.92rem}
      .module-chip,
      .status-chip,
      .badge,
      .event-chip,
      .intel-chip{
        font-size:.68rem;
      }
      table{
        display:block;
        width:100%;
        max-width:100%;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
      }
      table thead,
      table tbody{
        width:max-content;
        min-width:100%;
      }
      th,
      td{
        white-space:nowrap;
        vertical-align:top;
      }
      td .btn,
      td button,
      td select,
      td input[type="text"],
      td input[type="number"],
      td input[type="file"]{
        width:100%;
        min-width:0;
      }
      td pre,
      td .mono,
      td .mut-mini,
      .event-context pre,
      .api-result pre{
        white-space:pre-wrap;
        word-break:break-word;
      }
      .event-feed{
        max-height:none;
        padding-right:0;
      }
      .event-feed-item,
      .intel-item{
        padding:10px;
      }
      .event-grid,
      .event-columns,
      .intel-grid,
      .intel-side,
      .profile-meta,
      .intel-kpi-grid,
      .intel-workbench-kpis,
      .side-metrics,
      .vt-stat-grid,
      .intel-selector-grid,
      .intel-cockpit{
        grid-template-columns:1fr;
      }
      .workspace,
      .row,
      .split,
      .event-workbench,
      .intel-layout,
      .intel-workbench-grid,
      .profile-shell,
      .chart-stack,
      .intel-selector-grid,
      .intel-cockpit{
        gap:10px;
      }
      .event-detail-shell,
      .intel-editor,
      .intel-editor-section,
      .profile-card{
        overflow:hidden;
      }
      .intel-focus-bar,
      .intel-map-toolbar,
      .intel-selector-head,
      .intel-section-head,
      .bulk-review-toolbar,
      .vt-summary-head,
      .event-topline,
      .event-related-head{
        align-items:stretch;
      }
      .intel-map-toolbar-group,
      .intel-focus-actions,
      .intel-picker-actions,
      .intel-quick-grid,
      .intel-canvas-dock-actions,
      .intel-canvas-dock-tools,
      .bulk-review-toolbar .bulk-review-actions{
        width:100%;
      }
      .intel-map-toolbar select,
      .intel-map-toolbar button,
      .intel-focus-actions .btn,
      .intel-picker-actions .btn,
      .intel-quick-grid .btn,
      .intel-workspace-nav .btn,
      .intel-canvas-dock .btn,
      .intel-toolbar form,
      .intel-toolbar a,
      .event-quick-form,
      td form,
      .api-key-row-form,
      .api-key-row-form input[type="password"],
      .api-key-row-form input[type="text"]{
        width:100%;
        min-width:0;
      }
      .event-quick-form,
      td form,
      form[style*="display:flex"]{
        display:flex !important;
        flex-direction:column !important;
        align-items:stretch !important;
        gap:8px !important;
      }
      .event-quick-form > *,
      td form > *,
      form[style*="display:flex"] > *{
        width:100% !important;
        min-width:0 !important;
      }
      .intel-canvas-dock{
        left:10px;
        right:10px;
        bottom:10px;
        width:auto;
        max-height:min(42vh, 360px);
        overflow:auto;
      }
      .api-key-row-form{
        flex-direction:column;
        align-items:stretch;
      }
      .intel-canvas-wrap{
        height:58vh;
        min-height:320px;
        max-height:520px;
      }
      .geo-map{
        height:320px !important;
      }
      .chart-canvas{
        height:210px;
      }
      .profile-avatar{
        width:64px;
        height:64px;
      }
      .announcement-aside summary{
        align-items:center;
      }
    }
    @media(max-width:520px){
      .wrap{width:100%}
      .top{
        padding:10px;
        gap:10px;
      }
      .app-header-main,
      .app-header-navrow{
        gap:10px;
      }
      .top-status,
      .top .mut{
        width:100%;
      }
      .side-card,
      .profile-card,
      .intel-editor-section,
      .intel-selector-shell{
        padding:9px;
      }
      .intel-cockpit-card.actions{
        grid-column:auto;
      }
      .event-feed-host,
      .event-kv span,
      .intel-kpi span,
      .profile-name{
        word-break:break-word;
      }
      .intel-canvas-wrap{
        height:52vh;
        min-height:280px;
      }
      .intel-canvas-dock{
        max-height:min(48vh, 340px);
      }
      .geo-map{
        height:280px !important;
      }
      .compact-table th,
      .compact-table td{
        padding:6px;
        font-size:.72rem;
      }
      .chart-legend span,
      .mut,
      .event-feed-meta,
      .event-related-note{
        font-size:.72rem;
      }
    }

    .llm-chat-panel{display:flex;flex-direction:column;height:72vh;min-height:420px;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;background:var(--bg-layer)}
    .llm-chat-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:var(--bg-soft);border-bottom:1px solid var(--line);gap:12px;flex-wrap:wrap}
    .llm-chat-header strong{font-size:1rem}
    .llm-chat-header select,.llm-chat-header input{padding:6px 10px;border:1px solid var(--line);border-radius:var(--radius-sm);background:var(--bg);color:var(--txt);font-size:.8rem;min-width:120px}
    .llm-chat-header .llm-header-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .llm-chat-messages{flex:1;overflow-y:auto;padding:18px;display:flex;flex-direction:column;gap:14px}
    .llm-msg{max-width:85%;padding:12px 16px;border-radius:var(--radius-sm);line-height:1.55;font-size:.88rem;word-break:break-word}
    .llm-msg.user{align-self:flex-end;background:var(--brand);color:var(--bg);border-bottom-right-radius:6px}
    .llm-msg.assistant{align-self:flex-start;background:var(--bg-soft);color:var(--txt);border-bottom-left-radius:6px;border:1px solid var(--line)}
    .llm-msg .msg-meta{font-size:.68rem;opacity:.6;margin-bottom:4px}
    .llm-msg pre{background:var(--bg);padding:8px 12px;border-radius:8px;overflow-x:auto;font-size:.76rem;margin:8px 0 0}
    .llm-msg code{background:var(--bg);padding:2px 6px;border-radius:4px;font-size:.78rem}
    .llm-chat-input{display:flex;gap:10px;padding:14px 18px;border-top:1px solid var(--line);background:var(--bg-soft)}
    .llm-chat-input textarea{flex:1;resize:none;padding:10px 14px;border:1px solid var(--line);border-radius:var(--radius-sm);background:var(--bg);color:var(--txt);font-family:inherit;font-size:.84rem;min-height:48px;max-height:160px}
    .llm-chat-input button{padding:8px 20px;background:var(--brand);color:var(--bg);border:none;border-radius:var(--radius-sm);font-weight:600;cursor:pointer;font-size:.82rem;white-space:nowrap}
    .llm-chat-input button:disabled{opacity:.5;cursor:not-allowed}
    .llm-typing{display:flex;gap:6px;padding:8px 14px;align-self:flex-start}
    .llm-typing span{width:8px;height:8px;background:var(--mut);border-radius:50%;animation:llm-bounce 1.2s infinite}
    .llm-typing span:nth-child(2){animation-delay:.2s}
    .llm-typing span:nth-child(3){animation-delay:.4s}
    @keyframes llm-bounce{0%,80%,100%{transform:translateY(0);opacity:.4}40%{transform:translateY(-6px);opacity:1}}

    .auto-inv-panel{display:flex;flex-direction:column;gap:18px}
    .auto-inv-status-bar{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:var(--bg-soft);border:1px solid var(--line);border-radius:var(--radius-sm);gap:12px;flex-wrap:wrap}
    .auto-inv-status-bar .status-dot{width:12px;height:12px;border-radius:50%;display:inline-block}
    .auto-inv-status-bar .status-dot.on{background:var(--ok);box-shadow:0 0 8px var(--ok)}
    .auto-inv-status-bar .status-dot.off{background:var(--danger)}
    .auto-inv-controls{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .auto-inv-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
    .auto-inv-stat{padding:14px 16px;background:var(--bg-layer);border:1px solid var(--line);border-radius:var(--radius-sm)}
    .auto-inv-stat .k{font-size:.72rem;color:var(--mut);text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px}
    .auto-inv-stat .v{font-size:1.3rem;font-weight:700;color:var(--brand)}
    .auto-inv-jobs-list{display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto}
    .auto-inv-job{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg-layer);border:1px solid var(--line);border-radius:var(--radius-sm);gap:12px}
    .auto-inv-job .job-id{font-weight:600;color:var(--brand);min-width:70px}
    .auto-inv-job .job-title{flex:1;font-size:.84rem}
    .auto-inv-job .job-stage{font-size:.72rem;padding:4px 8px;border-radius:12px;background:var(--bg-soft);color:var(--mut)}
    .auto-inv-job .job-stage.running{background:rgba(111,246,255,.15);color:var(--brand)}
    .auto-inv-job .job-stage.completed{background:rgba(89,242,181,.15);color:var(--ok)}
    .auto-inv-job .job-stage.failed{background:rgba(255,111,146,.15);color:var(--danger)}
    .auto-inv-job .job-score{font-size:.76rem;color:var(--warn)}

    .blog-feed-panel{display:flex;flex-direction:column;gap:16px}
    .blog-feed-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
    .blog-feed-card{padding:16px;background:var(--bg-layer);border:1px solid var(--line);border-radius:var(--radius-sm);transition:border-color .2s}
    .blog-feed-card:hover{border-color:var(--brand)}
    .blog-feed-card .source{font-size:.68rem;color:var(--mut);text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px}
    .blog-feed-card h4{font-size:.92rem;margin:0 0 8px;line-height:1.35}
    .blog-feed-card h4 a{color:var(--txt);text-decoration:none}
    .blog-feed-card h4 a:hover{color:var(--brand)}
    .blog-feed-card p{font-size:.78rem;color:var(--mut);margin:0 0 10px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
    .blog-feed-card .meta{font-size:.7rem;color:var(--mut);display:flex;gap:12px}
    .blog-feed-crosslinks{border-top:1px solid var(--line);padding-top:14px;margin-top:6px}
    .blog-feed-crosslinks h4{font-size:.88rem;margin:0 0 10px;color:var(--brand)}
    .crosslink-item{display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--bg-soft);border-radius:var(--radius-sm);margin-bottom:6px}
    .crosslink-item .relevance{font-size:.68rem;padding:3px 7px;border-radius:10px;background:var(--brand);color:var(--bg);font-weight:600}
    .crosslink-item a{font-size:.8rem;color:var(--txt);text-decoration:none;flex:1}
    .crosslink-item a:hover{color:var(--brand)}

    .verdict-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:.78rem;font-weight:700;letter-spacing:.02em;text-transform:uppercase}
    .verdict-badge.confirmed_malicious{background:linear-gradient(135deg,rgba(255,80,80,.25),rgba(220,40,40,.15));color:#ff6060;border:1px solid rgba(255,80,80,.4);box-shadow:0 0 20px rgba(255,60,60,.2)}
    .verdict-badge.suspicious{background:linear-gradient(135deg,rgba(255,180,50,.25),rgba(220,140,20,.15));color:#ffb840;border:1px solid rgba(255,170,40,.4);box-shadow:0 0 20px rgba(255,160,30,.15)}
    .verdict-badge.investigating{background:linear-gradient(135deg,rgba(100,180,255,.25),rgba(60,140,240,.15));color:#6ab4ff;border:1px solid rgba(80,160,255,.4)}
    .verdict-badge.false_positive{background:linear-gradient(135deg,rgba(80,220,120,.2),rgba(40,180,80,.12));color:#5ce68c;border:1px solid rgba(60,200,100,.35)}
    .verdict-badge.unknown{background:linear-gradient(135deg,rgba(160,170,190,.15),rgba(120,130,150,.1));color:#a0aab8;border:1px solid rgba(140,150,170,.3)}
    .verdict-badge .verdict-icon{font-size:1rem}

    .card{backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid var(--line);transition:border-color .25s,box-shadow .25s,transform .2s}
    .card:hover{border-color:rgba(111,246,255,.2);box-shadow:0 8px 40px rgba(0,0,0,.35),0 0 40px rgba(111,246,255,.06)}
    .intel-cockpit-card{border-radius:var(--radius-sm);padding:14px 16px;background:linear-gradient(145deg,var(--bg-soft),var(--bg-layer));border:1px solid var(--line);transition:border-color .2s,transform .15s}
    .intel-cockpit-card:hover{border-color:var(--brand);transform:translateY(-1px)}
    .intel-cockpit-card .k{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--mut);margin-bottom:4px}
    .intel-cockpit-card .v{font-size:1.15rem;font-weight:700;color:var(--brand)}
    .intel-cockpit-card.actions .k{color:var(--accent)}

    .btn{font-weight:600;letter-spacing:.01em;transition:all .2s;border-radius:10px;padding:8px 18px;font-size:.82rem}
    .btn:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(0,0,0,.25)}
    .btn-primary{background:linear-gradient(135deg,var(--brand),color-mix(in srgb,var(--brand) 70%,var(--accent)));border:none;color:#050d18;font-weight:700}
    .btn-primary:hover{box-shadow:0 6px 22px rgba(111,246,255,.25)}
    .btn-outline-light{border:1px solid var(--line);color:var(--mut);background:transparent}
    .btn-outline-light:hover{color:var(--txt);border-color:var(--brand);background:rgba(111,246,255,.06)}

    table{width:100%;border-collapse:collapse}
    th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--mut);padding:10px 12px;border-bottom:2px solid var(--line);text-align:left;font-weight:600}
    td{padding:10px 12px;border-bottom:1px solid var(--line-soft);font-size:.82rem;vertical-align:middle}
    tr:hover td{background:rgba(111,246,255,.03)}
    .mono{font-family:'JetBrains Mono','Cascadia Code',monospace;font-size:.78rem}

    .sidebar{background:linear-gradient(180deg,var(--bg),var(--bg-layer) 60%);border-right:1px solid var(--line)}
    .sidebar .nav-link{transition:all .2s;border-radius:10px;margin:2px 8px;font-weight:500;font-size:.84rem}
    .sidebar .nav-link.active{background:linear-gradient(135deg,rgba(111,246,255,.12),rgba(127,255,191,.06));color:var(--brand);border-left:3px solid var(--brand)}
    .sidebar .nav-link:hover{background:rgba(111,246,255,.05);color:var(--txt)}
    .sidebar .nav-category .nav-link{font-size:.68rem;text-transform:uppercase;letter-spacing:.08em;color:var(--mut);font-weight:700}
    .sidebar .sub-menu .nav-link{font-size:.78rem;padding:7px 16px 7px 36px}

    .intel-shell{background:linear-gradient(160deg,var(--bg),var(--bg-layer) 40%,var(--bg-soft));border:1px solid var(--line);border-radius:var(--radius);overflow:hidden}
    .intel-stage-bar{border-radius:12px;overflow:hidden;background:var(--bg);border:1px solid var(--line)}
    .intel-stage{padding:7px 14px;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.03em;transition:all .3s}
    .intel-stage.active{background:linear-gradient(135deg,var(--brand),color-mix(in srgb,var(--brand) 60%,var(--accent)));color:var(--bg);font-weight:700}

    .badge{padding:4px 10px;border-radius:12px;font-size:.7rem;font-weight:600;letter-spacing:.02em}
    .badge.pending{background:rgba(255,208,112,.12);color:var(--warn);border:1px solid rgba(255,208,112,.3)}
    .badge.accepted{background:rgba(89,242,181,.12);color:var(--ok);border:1px solid rgba(89,242,181,.3)}
    .badge.rejected,.badge.blocked{background:rgba(255,111,146,.12);color:var(--danger);border:1px solid rgba(255,111,146,.3)}
    .badge.allowlisted{background:rgba(154,164,255,.12);color:var(--accent);border:1px solid rgba(154,164,255,.3)}

    .intel-kpi{padding:12px 16px;background:linear-gradient(145deg,var(--bg-soft),var(--bg));border-radius:var(--radius-sm);border:1px solid var(--line)}
    .intel-kpi b{display:block;font-size:.68rem;text-transform:uppercase;color:var(--mut);letter-spacing:.04em;margin-bottom:4px}
    .intel-kpi span{font-size:1.1rem;font-weight:700;color:var(--brand)}

    .event-feed-item{padding:12px 14px;border-radius:12px;border:1px solid var(--line-soft);margin:4px 0;background:var(--bg-layer);cursor:pointer;transition:all .2s}
    .event-feed-item:hover{border-color:var(--brand);box-shadow:0 4px 16px rgba(111,246,255,.08);transform:translateX(2px)}
    .event-feed-item.active{border-color:var(--brand);background:linear-gradient(135deg,rgba(111,246,255,.06),rgba(127,255,191,.03))}
    .event-feed-item .score-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px}
    .event-feed-item .score-dot.high{background:var(--danger);box-shadow:0 0 8px var(--danger)}
    .event-feed-item .score-dot.med{background:var(--warn);box-shadow:0 0 8px var(--warn)}
    .event-feed-item .score-dot.low{background:var(--ok)}

    .intel-focus-card{padding:16px;border-radius:var(--radius-sm);border:1px solid var(--line-soft);background:var(--bg-layer);transition:all .25s;margin-bottom:8px}
    .intel-focus-card:hover{border-color:var(--brand);box-shadow:0 8px 28px rgba(0,0,0,.3);transform:translateY(-2px)}
    .intel-focus-card strong{font-size:.95rem;color:var(--txt)}
    .intel-focus-card .summary{color:var(--mut);font-size:.78rem;margin-top:4px;line-height:1.45}

    .ops-lower-grid{display:grid;gap:14px}

    @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
    .intel-cockpit{animation:fadeIn .4s ease-out}
    .intel-focus-card{animation:fadeIn .35s ease-out}

    .navbar{background:linear-gradient(180deg,rgba(10,18,34,.95),rgba(10,18,34,.85));backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-bottom:1px solid var(--line)}
    .settings-card{background:linear-gradient(160deg,var(--bg-layer),var(--bg-soft));border-radius:var(--radius);border:1px solid var(--line)}
    .settings-pill{font-size:.68rem;padding:4px 12px;border-radius:16px;background:var(--brand);color:var(--bg);font-weight:700;text-transform:uppercase;letter-spacing:.04em}

    .profile-name h5{font-weight:700;font-size:.92rem;color:var(--txt)}
    .profile-name span{font-size:.72rem;color:var(--mut)}

    .intel-chip{padding:4px 10px;border-radius:14px;font-size:.68rem;font-weight:600;background:var(--bg-soft);color:var(--mut);border:1px solid var(--line)}
    .intel-chip.ok{background:rgba(89,242,181,.1);color:var(--ok);border-color:rgba(89,242,181,.3)}
    .intel-chip.warn{background:rgba(255,208,112,.1);color:var(--warn);border-color:rgba(255,208,112,.3)}
    .intel-chip.critical{background:rgba(255,111,146,.1);color:var(--danger);border-color:rgba(255,111,146,.3)}

    ::-webkit-scrollbar{width:6px;height:6px}
    ::-webkit-scrollbar-track{background:var(--bg)}
    ::-webkit-scrollbar-thumb{background:var(--line);border-radius:3px}
    ::-webkit-scrollbar-thumb:hover{background:var(--brand)}
  </style>
