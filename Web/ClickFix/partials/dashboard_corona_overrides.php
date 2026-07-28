<style>
  .display-settings-panel{
    position:fixed;
    right:20px;
    top:90px;
    z-index:1200;
    display:none;
    width:min(360px,92vw);
    max-height:80vh;
    overflow:auto;
    background:#191c24;
    border:1px solid #2c2e33;
    border-radius:6px;
    padding:14px;
  }
  .fx-node-bg{
    position:fixed;
    inset:0;
    width:100%;
    height:100%;
    z-index:0;
    pointer-events:none;
    opacity:0.65;
  }
  body.ui-no-decor .fx-node-bg{display:none}
  .container-scroller{position:relative;z-index:1}
  .display-settings-panel.open{display:block}
  .display-settings-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:10px;
  }
  .display-settings-header h4{margin:0;font-size:.95rem;color:#fff}
  .display-settings-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
  }
  .display-toggle{
    border:1px solid #2c2e33;
    border-radius:6px;
    padding:10px;
    background:#111319;
  }
  .display-toggle .label{font-size:.8rem;color:#cbd5f5}
  .switch{display:inline-flex;align-items:center;gap:10px}
  .switch input{display:none}
  .switch-track{
    width:42px;height:24px;border-radius:999px;
    border:1px solid #2c2e33;background:#0f1015;position:relative;
  }
  .switch-thumb{
    position:absolute;top:2px;left:2px;width:18px;height:18px;border-radius:50%;
    background:#64748b;transition:transform .2s ease;
  }
  .switch input:checked + .switch-track{
    background:#22c55e;border-color:#22c55e;
  }
  .switch input:checked + .switch-track .switch-thumb{
    transform:translateX(18px);background:#0f172a;
  }
  .display-section{margin-top:12px}
  .display-section h5{
    margin:0 0 8px;font-size:.75rem;color:#9ca3af;
    text-transform:uppercase;letter-spacing:.08em;
  }
  .preset-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
  .preset-card,.font-btn{
    border:1px solid #2c2e33;border-radius:10px;background:#111319;color:#e5e7eb;
    padding:8px 10px;cursor:pointer;display:flex;align-items:center;gap:10px;
    text-align:left;min-height:66px;transition:border-color .2s ease,transform .2s ease;
  }
  .preset-card:hover{border-color:#3b82f6;transform:translateY(-1px)}
  .preset-thumb{
    width:64px;height:42px;border-radius:10px;background:#0f1015;
    border:1px solid #2c2e33;position:relative;overflow:hidden;
    box-shadow:inset 0 0 0 1px rgba(255,255,255,0.02);
  }
  .preset-thumb::before,
  .preset-thumb::after{
    content:"";position:absolute;border-radius:4px;background:#1d2230;
    box-shadow:inset 0 0 0 1px rgba(255,255,255,0.06);
  }
  .preset-thumb--integrated::before{left:6px;top:6px;width:14px;height:30px}
  .preset-thumb--integrated::after{left:24px;top:6px;right:6px;height:12px}
  .preset-thumb--integrated .preset-dot{right:8px;bottom:8px}
  .preset-thumb--wide::before{left:6px;top:6px;right:6px;height:10px}
  .preset-thumb--wide::after{left:6px;bottom:6px;right:6px;height:16px}
  .preset-thumb--split::before{left:6px;top:6px;width:22px;height:30px}
  .preset-thumb--split::after{right:6px;top:6px;width:22px;height:30px}
  .preset-thumb--focused::before{left:6px;top:6px;right:6px;height:12px}
  .preset-thumb--focused::after{left:10px;bottom:6px;width:36px;height:20px}
  .preset-thumb--compact::before{left:6px;top:6px;width:18px;height:30px}
  .preset-thumb--compact::after{left:28px;top:6px;right:6px;height:8px}
  .preset-thumb--minimal::before{left:10px;top:10px;width:40px;height:10px}
  .preset-thumb--minimal::after{left:10px;bottom:10px;width:28px;height:8px}
  .preset-dot{
    position:absolute;width:10px;height:10px;border-radius:50%;
    box-shadow:0 0 12px rgba(91,139,255,0.6);
  }
  .preset-meta{display:flex;flex-direction:column;gap:2px}
  .preset-name{font-size:.8rem;font-weight:600;color:#e5e7eb}
  .preset-desc{font-size:.68rem;color:#94a3b8}
  .font-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
  .font-btn span{color:#9ca3af;font-size:.7rem}
  .content-wrapper{background:#0f1015}
  .card{margin-bottom:1.5rem}
  .card{padding:1.5rem}
  .card .card-body{padding:0}
  .grid-margin{margin-bottom:1.5rem}
  .table{color:inherit}
  .form-control{background:#111319;border:1px solid #2c2e33;color:#e5e7eb}
  .form-control:focus{border-color:#22c55e;box-shadow:none}
  .sidebar .nav .nav-item.profile{margin-bottom:.5rem}
  .sidebar .nav .nav-category{margin:0.5rem 1rem .25rem}
  .sidebar .nav .nav-item.menu-items .nav-link{
    margin:0.25rem 0.75rem;
    padding:0.7rem 0.9rem;
    border-radius:12px;
    transition:background .2s ease,color .2s ease;
  }
  .sidebar .nav .nav-item.menu-items .nav-link .menu-icon{
    margin-right:.65rem;
  }
  .sidebar .nav .nav-item.menu-items .menu-icon i{
    font-size:1.45rem;
  }
  .sidebar .nav .nav-item.menu-items .nav-link.active,
  .sidebar .nav .nav-item.menu-items .nav-link:hover{
    background:rgba(255,255,255,0.08);
    color:#fff;
  }
  .sidebar .nav .nav-item.menu-items .nav-link.active .menu-icon i{
    color:#22c55e;
  }
  .sidebar .nav.sub-menu{margin-left:1.25rem}
  .sidebar .nav.sub-menu .nav-link{
    padding:.4rem .75rem;
    border-radius:8px;
  }
  .brand-lockup{display:inline-flex;align-items:center;gap:10px;min-width:0}
  .brand-lockup img{display:block;flex:0 0 auto;object-fit:contain}
  .brand-lockup-text{
    color:#eef6ff;
    font-weight:800;
    font-size:1rem;
    line-height:1.1;
    letter-spacing:.01em;
    white-space:nowrap;
  }
  .brand-lockup-mini .brand-lockup-text{font-size:.86rem}
  .sidebar .sidebar-brand-wrapper .sidebar-brand.brand-logo{
    display:flex;
    align-items:center;
    justify-content:center;
    padding-left:0;
    overflow:hidden;
  }
  .sidebar .sidebar-brand-wrapper .sidebar-brand.brand-logo img{width:34px;height:34px;margin:0}
  .sidebar .sidebar-brand-wrapper .sidebar-brand.brand-logo-mini img{max-width:38px;height:32px;object-fit:contain}
  .sidebar-icon-only .sidebar .sidebar-brand-wrapper{
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    padding:0;
  }
  .sidebar-icon-only .sidebar .sidebar-brand-wrapper .brand-logo{
    display:none !important;
  }
  .sidebar-icon-only .sidebar .sidebar-brand-wrapper .brand-logo-mini{
    display:flex !important;
    align-items:center;
    justify-content:center;
    width:100%;
    min-width:0;
    padding:0;
    margin:0 auto;
  }
  .sidebar-icon-only .sidebar .sidebar-brand-wrapper .brand-logo-mini img{
    width:30px;
    height:30px;
    max-width:none;
    margin:0 auto;
  }
  .sidebar-mini .sidebar .sidebar-brand-wrapper .brand-logo{
    overflow:hidden;
  }
  .sidebar-mini.sidebar-icon-only .sidebar .sidebar-brand-wrapper .brand-lockup,
  .sidebar-icon-only .sidebar .sidebar-brand-wrapper .brand-lockup-text{
    display:none !important;
  }
  .navbar .navbar-brand-wrapper .navbar-brand.brand-logo-mini{
    display:flex !important;
    align-items:center;
    justify-content:center;
    width:100%;
  }
  .navbar .navbar-brand-wrapper .navbar-brand.brand-logo-mini img{width:28px;height:28px;margin:0}
  .navbar-profile{
    display:flex;
    align-items:center;
    gap:.65rem;
    min-width:0;
  }
  .navbar-profile-avatar,
  .sidebar-profile-avatar{
    width:36px;
    height:36px;
    min-width:36px;
    border-radius:999px;
    object-fit:cover;
    display:grid;
    place-items:center;
    overflow:hidden;
    border:1px solid rgba(148,163,184,.28);
    background:#0f172a;
    color:#e2e8f0;
    font-weight:800;
    line-height:1;
  }
  .navbar-profile-avatar--placeholder,
  .sidebar-profile-avatar--placeholder{
    font-size:.88rem;
  }
  .sidebar .nav .nav-item.profile .profile-pic{
    align-items:center;
    gap:.85rem;
  }
  .sidebar .nav .nav-item.profile .profile-name{
    min-width:0;
  }
  .sidebar .nav .nav-item.profile .profile-name h5,
  .sidebar .nav .nav-item.profile .profile-name span{
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  @media (min-width: 992px){
    .navbar-profile-avatar{
      display:none;
    }
  }
  @media (max-width: 991.98px){
    .sidebar .nav .nav-item.profile{
      display:none;
    }
    .navbar-profile-avatar{
      display:grid;
    }
  }
  .side-card--geo{padding:12px}
  .side-card-head--geo{margin-bottom:10px}
  .side-card-subtitle{margin:4px 0 0;font-size:.78rem;line-height:1.4}
  .side-card-grid--ops .side-card--geo{grid-column:1 / -1}
  .side-geo-layout{
    display:grid;
    grid-template-columns:minmax(340px,1.55fr) minmax(260px,1fr);
    gap:16px;
    align-items:start;
  }
  .geo-mini-wrap{
    margin-top:0;
    padding:12px;
    border:1px solid #27435f;
    border-radius:14px;
    background:linear-gradient(180deg,#0d1622,#0b1a2a);
  }
  .geo-map-meta{
    display:grid;
    grid-template-columns:repeat(2,minmax(160px,1fr));
    gap:10px;
    margin-bottom:12px;
  }
  .geo-map-stat{
    padding:10px 12px;
    border-radius:12px;
    border:1px solid #294867;
    background:linear-gradient(160deg,#102337,#0e1e31);
    min-width:0;
  }
  .geo-map-stat .label{
    display:block;
    color:#8fb0ce;
    font-size:.68rem;
    text-transform:uppercase;
    letter-spacing:.08em;
    margin-bottom:4px;
  }
  .geo-map-stat b{
    display:block;
    color:#eef7ff;
    font-size:1rem;
    line-height:1.25;
    word-break:break-word;
    overflow-wrap:anywhere;
  }
  .geo-mini-map{
    width:100%;
    height:320px;
    border-radius:14px;
    border:1px solid #325676;
    background:
      radial-gradient(circle at top left,rgba(94,205,255,.12),transparent 34%),
      linear-gradient(180deg,#08101a,#0b1320);
    overflow:hidden;
  }
  .geo-mini-scale{
    display:flex;
    align-items:center;
    gap:8px;
    margin-top:10px;
    color:#99b9d5;
    font-size:.72rem;
  }
  .geo-mini-scale i{
    flex:1;
    height:8px;
    border-radius:999px;
    border:1px solid #2a4863;
    background:linear-gradient(90deg,#133654,#1fb6ff,#22c55e,#ffd166,#ff6b6b);
  }
  .geo-mini-legend{margin-top:7px;font-size:.75rem;color:#9ca3af}
  .side-country-ranking{
    padding:10px 12px;
    border:1px solid #27435f;
    border-radius:14px;
    background:linear-gradient(180deg,#0b1a2a,#0a1724);
    min-width:0;
  }
  .mini-list--countries{display:grid;gap:8px}
  .mini-list--countries li{
    padding:0 0 8px;
    border-bottom:1px solid rgba(106,140,176,.16);
  }
  .country-row{display:grid;grid-template-columns:1fr auto;gap:6px;align-items:center;position:relative;padding-bottom:6px}
  .country-label{display:flex;align-items:center;gap:6px;min-width:0}
  .country-label .mono{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .country-flag{width:18px;height:12px;border-radius:2px;box-shadow:0 0 0 1px rgba(255,255,255,0.15)}
  .country-bar{grid-column:1 / -1;height:5px;background:rgba(255,255,255,0.08);border-radius:999px;overflow:hidden}
  .country-bar span{display:block;height:100%;background:linear-gradient(90deg,#1fb6ff,#22c55e,#ffd166,#ff6b6b)}
  .leaflet-container{background:#0f1015}
  .home-feature-icon{font-size:1.7rem}
  .kpi-icon{font-size:1.9rem}
  .message-cell{white-space:pre-wrap;word-break:break-word;line-height:1.35}
  body.page-home .dashboard-main-col,
  body.page-home .dashboard-side-col,
  body.page-search .dashboard-main-col,
  body.page-search .dashboard-side-col,
  body.page-coverage .dashboard-main-col,
  body.page-coverage .dashboard-side-col,
  body.page-about .dashboard-main-col,
  body.page-about .dashboard-side-col,
  body.page-access .dashboard-main-col,
  body.page-access .dashboard-side-col,
  body.page-analytics .dashboard-main-col,
  body.page-analytics .dashboard-side-col,
  body.page-intel_stats .dashboard-main-col,
  body.page-intel_stats .dashboard-side-col,
  body.page-community .dashboard-main-col,
  body.page-community .dashboard-side-col,
  body.page-extensions .dashboard-main-col,
  body.page-extensions .dashboard-side-col,
  body.page-lists .dashboard-main-col,
  body.page-lists .dashboard-side-col,
  body.page-requests .dashboard-main-col,
  body.page-requests .dashboard-side-col,
  body.page-messaging .dashboard-main-col,
  body.page-messaging .dashboard-side-col,
  body.page-data_center .dashboard-main-col,
  body.page-data_center .dashboard-side-col,
  body.page-reports .dashboard-main-col,
  body.page-reports .dashboard-side-col,
  body.page-users .dashboard-main-col,
  body.page-users .dashboard-side-col,
  body.page-settings .dashboard-main-col,
  body.page-settings .dashboard-side-col,
  body.page-ops .dashboard-main-col,
  body.page-ops .dashboard-side-col{
    grid-column:1 / -1;
  }
  body.page-home .side-column,
  body.page-search .side-column{
    position:static;
    top:auto;
    max-height:none;
    overflow:visible;
  }
  body.page-coverage .side-column,
  body.page-about .side-column,
  body.page-access .side-column,
  body.page-analytics .side-column,
  body.page-intel_stats .side-column,
  body.page-community .side-column,
  body.page-extensions .side-column,
  body.page-lists .side-column,
  body.page-requests .side-column,
  body.page-messaging .side-column,
  body.page-data_center .side-column,
  body.page-reports .side-column,
  body.page-users .side-column,
  body.page-settings .side-column,
  body.page-ops .side-column{
    position:static;
    top:auto;
    max-height:none;
    overflow:visible;
  }
  body.page-home .dashboard-side-col,
  body.page-search .dashboard-side-col,
  body.page-coverage .dashboard-side-col,
  body.page-about .dashboard-side-col,
  body.page-access .dashboard-side-col,
  body.page-analytics .dashboard-side-col,
  body.page-intel_stats .dashboard-side-col,
  body.page-community .dashboard-side-col,
  body.page-extensions .dashboard-side-col,
  body.page-lists .dashboard-side-col,
  body.page-requests .dashboard-side-col,
  body.page-messaging .dashboard-side-col,
  body.page-data_center .dashboard-side-col,
  body.page-reports .dashboard-side-col,
  body.page-users .dashboard-side-col,
  body.page-settings .dashboard-side-col,
  body.page-ops .dashboard-side-col{
    margin-top:14px;
  }
  .search-forensic-card{
    width:100%;
  }
  .search-forensic-form{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
    align-items:end;
  }
  .search-forensic-form > input[type="hidden"]{
    display:none;
  }
  .search-submit-btn{
    min-height:46px;
    width:100%;
  }
  .search-results-wrap{
    width:100%;
    overflow:auto;
    border:1px solid rgba(106,140,176,.16);
    border-radius:12px;
    background:rgba(7,14,24,.35);
  }
  .search-table{
    table-layout:auto;
    width:100%;
    min-width:1120px;
    margin:0;
  }
  .search-table th,
  .search-table td{
    padding-top:.85rem;
    padding-bottom:.85rem;
    vertical-align:top;
  }
  .search-table th:nth-child(1),
  .search-table td:nth-child(1){min-width:190px;white-space:nowrap}
  .search-table th:nth-child(2),
  .search-table td:nth-child(2){min-width:220px;white-space:nowrap}
  .search-table th:nth-child(3),
  .search-table td:nth-child(3){min-width:360px}
  .search-table th:nth-child(4),
  .search-table td:nth-child(4){min-width:80px;text-align:right;white-space:nowrap}
  .search-table th:nth-child(5),
  .search-table td:nth-child(5),
  .search-table th:nth-child(6),
  .search-table td:nth-child(6),
  .search-table th:nth-child(7),
  .search-table td:nth-child(7){min-width:120px;text-align:center;white-space:nowrap}
  .search-table .message-cell{
    line-height:1.45;
    white-space:pre-wrap;
    word-break:break-word;
    overflow-wrap:break-word;
  }
  @media (max-width: 1200px){
    .search-forensic-form{
      grid-template-columns:repeat(2,minmax(0,1fr));
    }
  }
  @media (max-width: 768px){
    .search-forensic-form{
      grid-template-columns:1fr;
    }
    .search-table{
      min-width:900px;
    }
  }
  .ops-header{display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;margin-bottom:16px}
  .ops-badges{display:flex;flex-wrap:wrap;gap:8px}
  .ops-table th,
  .ops-table td{
    padding-top:.85rem;
    padding-bottom:.85rem;
    vertical-align:top;
  }
  .coverage-chip-row{display:flex;flex-wrap:wrap;gap:8px}
  .coverage-list{list-style:none;padding:0;margin:0 0 10px}
  .coverage-list li{display:flex;align-items:center;gap:10px;margin-bottom:10px}
  .dot{width:10px;height:10px;border-radius:50%}
  .dot-danger{background:#ef4444}
  .dot-warning{background:#f59e0b}
  .dot-info{background:#38bdf8}
  .dot-primary{background:#6366f1}
  .dot-success{background:#22c55e}
  .coverage-flow{display:grid;gap:12px}
  .flow-step{display:flex;gap:12px;align-items:flex-start;padding:10px;border:1px solid #2c2e33;border-radius:10px;background:#111319}
  .step-index{font-weight:700;color:#22c55e}
  .table-striped > tbody > tr:nth-of-type(odd) > *{
    color:inherit;
    background-color:rgba(255,255,255,0.04);
  }
  .table td, .table th{border-top:1px solid rgba(255,255,255,0.06)}
  @font-face{
    font-family:'Assistant';
    src:url('assets/corona/fonts/Assistant/Assistant-Regular.woff2') format('woff2');
    font-weight:400;font-style:normal;font-display:swap;
  }
  @font-face{
    font-family:'Assistant';
    src:url('assets/corona/fonts/Assistant/Assistant-Bold.woff2') format('woff2');
    font-weight:700;font-style:normal;font-display:swap;
  }
  @font-face{
    font-family:'Rubik';
    src:url('assets/corona/fonts/Rubik/Rubik-Regular.ttf') format('truetype');
    font-weight:400;font-style:normal;font-display:swap;
  }
  @font-face{
    font-family:'Rubik';
    src:url('assets/corona/fonts/Rubik/Rubik-Bold.ttf') format('truetype');
    font-weight:700;font-style:normal;font-display:swap;
  }
  body.ui-font-jakarta{font-family:'Assistant','Segoe UI',Arial,sans-serif}
  body.ui-font-public{font-family:'Rubik','Segoe UI',Arial,sans-serif}
  body.ui-font-dm{font-family:'Assistant','Segoe UI',Arial,sans-serif}
  body.ui-font-nunito{font-family:'Rubik','Segoe UI',Arial,sans-serif}
  body.ui-font-sora{font-family:'Assistant','Segoe UI',Arial,sans-serif}
  body.ui-font-arial{font-family:Arial,'Helvetica Neue',Helvetica,sans-serif}
  body.ui-font-helvetica{font-family:'Helvetica Neue',Helvetica,Arial,sans-serif}
  body.ui-font-ubuntu{font-family:Ubuntu,'Segoe UI',Arial,sans-serif}
  body.ui-font-roboto{font-family:Roboto,'Segoe UI',Arial,sans-serif}
  body.ui-light{background:#f2f4f7;color:#101828}
  body.ui-light .content-wrapper{background:#f2f4f7}
  body.ui-light .card{background:#ffffff;border-color:#e4e7ec;color:#101828}
  body.ui-light .sidebar{background:#f7f8fa}
  body.ui-light .navbar{background:#ffffff}
  body.ui-light .form-control{background:#ffffff;border-color:#d0d5dd;color:#101828}
  body.ui-light .table{color:#101828}
  body.ui-light .table-striped > tbody > tr:nth-of-type(odd) > *{background-color:#f2f4f7}
  body.ui-light .event-related-link{color:#2563eb}
  body.ui-light .mut, body.ui-light .text-muted{color:#667085}
  body.ui-light .badge{color:#101828;border-color:#d0d5dd}
  body.ui-light .badge-outline-info{color:#2563eb;border-color:#93c5fd}
  body.ui-light .badge-outline-success{color:#16a34a;border-color:#86efac}
  body.ui-light .badge-outline-warning{color:#d97706;border-color:#fde68a}
  body.ui-light .badge-outline-primary{color:#4f46e5;border-color:#c7d2fe}
  body.ui-light .event-feed-item{background:#ffffff;border-color:#e4e7ec}
  body.ui-light .event-feed-item.is-active{border-color:#2563eb}
  body.ui-light .event-feed-sev{background:#2563eb}
  body.ui-light .event-feed-item--child{background:#f8fafc;border-color:#e4e7ec}
  body.ui-light .event-group-toggle{
    background:#f8fafc;border-color:#d0d5dd;color:#2563eb;
  }
  body.ui-light .event-feed-count{
    background:#eef2ff;border-color:#c7d2fe;color:#1d4ed8;
  }
  body.ui-light .legacy-card{background:#ffffff;border-color:#e4e7ec}
  body.ui-light .legacy-card summary{color:#0f172a}
  body.ui-light .legacy-table-wrap{background:#ffffff;border-color:#e4e7ec}
  body.ui-light .legacy-table thead th{background:#f8fafc;border-bottom-color:#e4e7ec;color:#475569}
  body.ui-light .scan-card,
  body.ui-light .scan-thumb-card{background:#ffffff;border-color:#e4e7ec}
  body.ui-light .scan-card-media,
  body.ui-light .scan-thumb-media{background:#f8fafc;border-color:#e4e7ec}
  body.ui-light .scan-placeholder{color:#64748b}
  body.ui-light .analytics-hub{background:#ffffff;border-color:#e4e7ec}
  body.ui-light .analytics-panel,
  body.ui-light .analytics-table-card{background:#ffffff;border-color:#e4e7ec}
  body.ui-light .chip{background:#f8fafc;border-color:#e4e7ec;color:#1f2937}
  body.ui-light .chip.is-active{background:#e0e7ff;border-color:#c7d2fe;color:#1d4ed8}
  body.ui-light .kpi-pill{background:#f8fafc;border-color:#e4e7ec;color:#1f2937}
  body.ui-light .panel-kpis div,
  body.ui-light .panel-metric{background:#f8fafc;border-color:#e4e7ec}
  body.ui-light .analytics-header h1{color:#0f172a}
  body.ui-light .analytics-kpi{background:#ffffff;border-color:#e4e7ec}
  body.ui-light .chart-card{background:#ffffff;border-color:#e4e7ec}
  body.ui-light .analytics-table-wrap{background:#ffffff;border-color:#e4e7ec}
  body.ui-light .analytics-search{background:#ffffff;border-color:#e4e7ec;color:#0f172a}
  body.ui-light .ops-panel{background:#ffffff;border-color:#e4e7ec}
  body.ui-light .ops-kpi,
  body.ui-light .ops-mini div,
  body.ui-light .ops-domain-item,
  body.ui-light .ops-sev-row{background:#f8fafc;border-color:#e4e7ec;color:#0f172a}
  body.ui-contrast .card{border-color:#ffffff}
  body.ui-contrast .table td, body.ui-contrast .table th{border-color:#ffffff}
  body.ui-compact .card{padding:1rem}
  body.ui-compact .card .card-body{padding:0}
  body.ui-compact .table td, body.ui-compact .table th{padding-top:.6rem;padding-bottom:.6rem}
  body.ui-compact .sidebar .nav .nav-item.menu-items .nav-link{padding:.55rem .75rem}
  body.ui-reduced-motion *, body.ui-reduced-motion *::before, body.ui-reduced-motion *::after{transition:none !important;animation:none !important}
  body.ui-no-decor .card{box-shadow:none}
  body.ui-no-decor .sidebar .nav .nav-item.menu-items .nav-link{background:transparent}
  body.ui-accent-blue{--cf-accent:#5b8bff}
  body.ui-accent-green{--cf-accent:#37e3a7}
  body.ui-accent-purple{--cf-accent:#a685ff}
  body.ui-accent-amber{--cf-accent:#ffb454}
  body.ui-accent-red{--cf-accent:#ff7a86}
  body.ui-accent-cyan{--cf-accent:#5fd8ff}
  .btn.btn-primary{background:var(--cf-accent, #5b8bff);border-color:var(--cf-accent, #5b8bff)}
  body.ui-layout-wide .container-fluid.page-body-wrapper{max-width:100%}
  body.ui-layout-focused .main-panel{max-width:1200px;margin:0 auto}
  body.ui-layout-compact .card{padding:.9rem}
  body.ui-layout-minimal .sidebar{width:200px}
  body:not(.template-corona) .card{border-radius:8px;background:#0b0f16}
  body:not(.template-corona) .sidebar, body:not(.template-corona) .navbar{background:#0b0f16}
  .profile-page{row-gap:1.5rem}
  .profile-hero .card-body{display:flex;flex-direction:column;gap:1rem}
  .profile-hero-head{display:flex;gap:1rem;align-items:center}
  .profile-avatar-lg{width:76px;height:76px;border-radius:22px;border:1px solid rgba(148,163,184,.25);background:rgba(15,23,42,.6);display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;color:#e2e8f0;overflow:hidden}
  .profile-avatar-lg.has-image{padding:0}
  .profile-avatar-lg img{width:100%;height:100%;object-fit:cover}
  .profile-title h2{margin:0;font-size:1.45rem}
  .profile-chips{display:flex;flex-wrap:wrap;gap:.5rem}
  .profile-stat-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
  .profile-stat{background:rgba(15,23,42,.45);border:1px solid rgba(148,163,184,.2);border-radius:14px;padding:.7rem .8rem;display:flex;flex-direction:column;gap:.25rem}
  .profile-stat span{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8}
  .profile-stat strong{font-size:1.1rem}
  .profile-kv{display:flex;justify-content:space-between;gap:1rem;padding:.4rem 0;border-bottom:1px dashed rgba(148,163,184,.2)}
  .profile-kv:last-of-type{border-bottom:none}
  .profile-kv .label{color:#94a3b8;font-size:.8rem}
  .profile-actions{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:.4rem}
  .profile-tabs-body{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem}
  .profile-section-title{margin:0 0 .25rem 0;font-size:1.1rem}
  .profile-tabs{gap:.45rem}
  .profile-tabs .nav-link{border-radius:999px;background:rgba(15,23,42,.6);border:1px solid rgba(148,163,184,.2);color:#e2e8f0;padding:.35rem .9rem}
  .profile-tabs .nav-link.active{background:var(--cf-accent, #5b8bff);border-color:var(--cf-accent, #5b8bff);color:#0b0f16}
  .profile-edit-card .form-label{color:#cbd5f5;font-weight:600}
  .profile-edit-actions{display:flex;flex-wrap:wrap;gap:.7rem;margin-top:1rem}
  .profile-panel .table{margin-bottom:0}
  .profile-table td{vertical-align:top}
  .profile-table .text-wrap{white-space:normal;max-width:460px}
  @media (max-width: 575px){
    .profile-stat-grid{grid-template-columns:1fr}
    .profile-tabs-body{align-items:flex-start}
  }
  body.ui-light .profile-avatar-lg{background:#f1f5f9;border-color:#e2e8f0;color:#0f172a}
  body.ui-light .profile-stat{background:#f8fafc;border-color:#e2e8f0}
  body.ui-light .profile-stat span{color:#64748b}
  body.ui-light .profile-kv{border-color:#e2e8f0}
  body.ui-light .profile-kv .label{color:#64748b}
  body.ui-light .profile-tabs .nav-link{background:#f1f5f9;border-color:#e2e8f0;color:#0f172a}
  body.ui-light .profile-tabs .nav-link.active{color:#ffffff}
  .settings-page{row-gap:1.5rem}
  .settings-card .card-body{display:flex;flex-direction:column;gap:1rem}
  .settings-head{display:flex;align-items:center;justify-content:space-between;gap:1rem}
  .settings-head h2{margin:0}
  .settings-pill{background:rgba(99,102,241,.15);color:#c7d2fe;border:1px solid rgba(99,102,241,.35);padding:.25rem .7rem;border-radius:999px;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em}
  .settings-form .form-label{color:#cbd5f5;font-weight:600}
  .settings-actions{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:.5rem}
  .settings-avatar-actions{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-top:.6rem}
  .settings-avatar-actions .mini{font-size:.75rem;color:#94a3b8}
  .users-admin-hero .card-body{display:flex;flex-direction:column;gap:1rem}
  .users-hero-head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:1rem;align-items:center}
  .users-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}
  .users-kpi{background:rgba(15,23,42,.55);border:1px solid rgba(148,163,184,.2);border-radius:14px;padding:.7rem .8rem;display:flex;flex-direction:column;gap:.2rem}
  .users-kpi span{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8}
  .users-kpi strong{font-size:1.15rem}
  .users-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem;align-items:end}
  .users-form-grid input, .users-form-grid select{width:100%}
  .users-form-grid .btn{justify-self:start}
  .users-table-head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:1rem}
  .users-table-actions{min-width:220px}
  .users-table-wrap{max-height:560px;overflow:auto}
  .user-inline-form{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
  .user-inline-form select, .user-inline-form input{min-width:140px}
  @media (max-width: 1200px){
    .users-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .users-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  }
  @media (max-width: 768px){
    .users-kpi-grid{grid-template-columns:1fr}
    .users-form-grid{grid-template-columns:1fr}
    .user-inline-form{flex-direction:column;align-items:stretch}
  }
  body.ui-light .users-kpi{background:#f8fafc;border-color:#e2e8f0}
  body.ui-light .users-kpi span{color:#64748b}
  .settings-kv{display:flex;justify-content:space-between;gap:1rem;padding:.55rem 0;border-bottom:1px dashed rgba(148,163,184,.2)}
  .settings-kv:last-of-type{border-bottom:none}
  .settings-api-card .card-body{display:flex;flex-direction:column;gap:1rem}
  .settings-api-card h3{margin:0.5rem 0 0}
  .settings-table td{vertical-align:top}
  .api-key-row-form{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
  .api-key-row-form .form-control{min-width:220px;max-width:360px}
  .api-key-row-form .btn{white-space:nowrap}
  .api-result pre{max-height:220px;overflow:auto}
  body.ui-light .settings-pill{background:#e0e7ff;color:#1d4ed8;border-color:#c7d2fe}
  body.ui-light .settings-kv{border-color:#e2e8f0}
  body.ui-light .settings-form .form-label{color:#334155}
  .ops-grid{display:grid;grid-template-columns:minmax(0,1fr) !important;gap:1.2rem;align-items:start}
  .ops-main{display:flex;flex-direction:column;gap:1rem;width:100%;min-width:0}
  .ops-side{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;width:100%;min-width:0}
  .ops-side-grid{grid-column:1 / -1}
  .ops-panel .card-body{display:flex;flex-direction:column;gap:.9rem}
  .ops-panel-head{display:flex;align-items:center;justify-content:space-between}
  .ops-panel-head h3{margin:0;font-size:1rem}
  .ops-kpi-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}
  .ops-kpi{background:rgba(15,23,42,.55);border:1px solid rgba(148,163,184,.2);border-radius:12px;padding:.6rem .7rem;display:flex;flex-direction:column;gap:.25rem}
  .ops-kpi span{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8}
  .ops-kpi strong{font-size:1.05rem}
  .ops-mini{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem}
  .ops-mini div{display:flex;flex-direction:column;gap:.2rem;color:#cbd5f5}
  .ops-mini span{font-size:.72rem;color:#94a3b8}
  .ops-actions{display:flex;flex-wrap:wrap;gap:.5rem}
  .ops-sev{display:flex;flex-direction:column;gap:.4rem}
  .ops-sev-row{display:flex;justify-content:space-between;align-items:center;padding:.45rem .6rem;border-radius:10px;border:1px solid rgba(148,163,184,.2)}
  .ops-sev-row.high{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.4)}
  .ops-sev-row.med{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.35)}
  .ops-sev-row.low{background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.35)}
  .ops-domain-list{display:flex;flex-direction:column;gap:.5rem}
  .ops-domain-item{display:flex;justify-content:space-between;gap:.8rem;padding:.45rem .6rem;border-radius:10px;border:1px solid rgba(148,163,184,.2);background:rgba(15,23,42,.55)}
  @media (max-width: 1400px){
    .side-geo-layout{grid-template-columns:1fr}
  }
  @media (max-width: 1200px){
    .ops-grid{grid-template-columns:1fr !important}
    .ops-side{grid-template-columns:1fr}
    .side-geo-layout{grid-template-columns:1fr}
    .geo-mini-map{height:220px}
  }
  body.ui-light .ops-kpi, body.ui-light .ops-domain-item{background:#f8fafc;border-color:#e2e8f0}
  body.ui-light .ops-kpi span, body.ui-light .ops-mini span{color:#64748b}
  body.ui-light .ops-sev-row{border-color:#e2e8f0}
  .intel-public-grid,
  .intel-workspace-grid,
  .community-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:1.2rem;align-items:start}
  .intel-public-main,
  .intel-workspace-main,
  .community-main{display:flex;flex-direction:column;gap:1rem;min-width:0}
  .intel-public-side,
  .intel-workspace-side,
  .community-side{display:flex;flex-direction:column;gap:1rem}
  .intel-side-card .card-body{display:flex;flex-direction:column;gap:.6rem}
  .intel-side-card h3{margin:0;font-size:1rem}
  .intel-side-kv{display:flex;justify-content:space-between;gap:.7rem;padding:.35rem 0;border-bottom:1px dashed rgba(148,163,184,.2);font-size:.92rem}
  .intel-side-kv:last-of-type{border-bottom:none}
  .intel-side-kv span{color:#94a3b8;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em}
  .intel-side-kpi-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem}
  .intel-side-kpi-grid div{display:flex;flex-direction:column;gap:.2rem;padding:.5rem .6rem;border-radius:12px;border:1px solid rgba(148,163,184,.18);background:rgba(15,23,42,.55)}
  .intel-side-kpi-grid span{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8}
  .intel-side-kpi-grid b{font-size:1rem}
  .intel-side-actions{display:flex;flex-wrap:wrap;gap:.5rem}
  .intel-side-note{margin-top:.6rem;padding:.6rem .7rem;border-radius:12px;background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.18);max-height:180px;overflow:auto;white-space:pre-wrap}
  @media (max-width: 1200px){
    .intel-public-grid,
    .intel-workspace-grid,
    .community-grid{grid-template-columns:1fr}
  }
  body.ui-light .intel-side-kv{border-color:#e2e8f0}
  body.ui-light .intel-side-kv span{color:#64748b}
  body.ui-light .intel-side-kpi-grid div{background:#f8fafc;border-color:#e2e8f0}
  body.ui-light .intel-side-note{background:#f8fafc;border-color:#e2e8f0}
  .chart-grid-advanced{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-top:1rem}
  @media (max-width: 1000px){
    .chart-grid-advanced{grid-template-columns:1fr}
  }
</style>
