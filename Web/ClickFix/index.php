<?php
  // Static landing page for ClickFix Mitigator.
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ClickFix Mitigator | Defense-first anti ClickFix</title>
  <meta name="description" content="ClickFix Mitigator: extension defense-first para frenar ClickFix. Detecta, interrumpe y registra intentos de ingenieria social basados en ejecucion de comandos." />
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap');

    :root {
      color-scheme: dark;
      --bg: #05070b;
      --bg-2: #0c121a;
      --ink: #e6f2ef;
      --muted: #8aa0a4;
      --accent: #59f08d;
      --accent-2: #18e5ff;
      --accent-3: #f2ff7a;
      --stroke: rgba(24, 229, 255, 0.18);
      --glass: rgba(8, 12, 18, 0.75);
      --shadow: 0 30px 80px rgba(2, 6, 12, 0.7);
      --glow: 0 0 40px rgba(24, 229, 255, 0.2);
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'JetBrains Mono', 'Consolas', monospace;
      color: var(--ink);
      background:
        radial-gradient(circle at 10% 10%, rgba(24, 229, 255, 0.2), transparent 40%),
        radial-gradient(circle at 90% 15%, rgba(89, 240, 141, 0.18), transparent 42%),
        radial-gradient(circle at 70% 85%, rgba(242, 255, 122, 0.12), transparent 38%),
        repeating-linear-gradient(90deg, rgba(230, 242, 239, 0.03) 0 1px, transparent 1px 80px),
        repeating-linear-gradient(0deg, rgba(230, 242, 239, 0.02) 0 1px, transparent 1px 80px),
        linear-gradient(140deg, var(--bg), var(--bg-2));
      position: relative;
      overflow-x: hidden;
    }

    body::before,
    body::after {
      content: "";
      position: fixed;
      inset: -20% auto auto -10%;
      width: 520px;
      height: 520px;
      background: conic-gradient(from 120deg, rgba(24, 229, 255, 0.35), rgba(89, 240, 141, 0.18), rgba(242, 255, 122, 0.18));
      filter: blur(90px);
      opacity: 0.5;
      z-index: 0;
      animation: drift 22s ease-in-out infinite;
      pointer-events: none;
    }

    body::after {
      inset: auto -15% -25% auto;
      width: 440px;
      height: 440px;
      animation-delay: -8s;
    }

    @keyframes drift {
      0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
      50% { transform: translate3d(40px, -20px, 0) scale(1.05); }
    }

    a { color: inherit; text-decoration: none; }

    .page {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      gap: 72px;
      padding: 32px 6vw 90px;
      max-width: 1200px;
      margin: 0 auto;
    }

    .nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      padding: 14px 18px;
      border-radius: 20px;
      background: rgba(7, 12, 18, 0.78);
      border: 1px solid rgba(24, 229, 255, 0.25);
      box-shadow: var(--glow);
      backdrop-filter: blur(14px);
      position: sticky;
      top: 16px;
      z-index: 2;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 16px;
      font-family: 'Sora', sans-serif;
      letter-spacing: 0.4px;
    }

    .brand-mark {
      width: 50px;
      height: 50px;
      border-radius: 16px;
      background: rgba(12, 18, 26, 0.7);
      border: 1px solid rgba(24, 229, 255, 0.5);
      box-shadow: 0 0 18px rgba(24, 229, 255, 0.35);
      display: grid;
      place-items: center;
      padding: 6px;
    }

    .brand-mark img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      filter: drop-shadow(0 0 10px rgba(24, 229, 255, 0.45));
    }

    .lang {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }

    .lang-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.2em;
      color: var(--muted);
    }

    .lang select {
      background: rgba(7, 12, 18, 0.85);
      color: var(--ink);
      border: 1px solid rgba(24, 229, 255, 0.3);
      border-radius: 12px;
      padding: 8px 12px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 12px;
      cursor: pointer;
      outline: none;
      box-shadow: inset 0 0 0 1px rgba(24, 229, 255, 0.12);
    }

    .hero {
      display: grid;
      gap: 24px;
    }

    .hero-grid {
      display: grid;
      gap: 28px;
      grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
      align-items: center;
    }

    .hero-copy {
      display: grid;
      gap: 18px;
    }

    .eyebrow {
      text-transform: uppercase;
      font-family: 'Sora', sans-serif;
      letter-spacing: 2.4px;
      color: var(--accent-2);
      font-size: 12px;
    }

    h1 {
      font-family: 'Sora', sans-serif;
      font-size: clamp(34px, 5vw, 66px);
      line-height: 1.05;
      margin: 0;
    }

    .hero p {
      font-size: clamp(17px, 2vw, 21px);
      color: var(--muted);
      margin: 0;
      max-width: 720px;
    }

    .hero-tags {
      margin-top: 6px;
      gap: 12px;
    }

    .cta {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
    }

    .cta-note {
      font-family: 'Sora', sans-serif;
      font-size: 13px;
      color: var(--muted);
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }

    .cta-note a {
      color: var(--accent-2);
      text-decoration: underline;
      text-underline-offset: 3px;
    }

    .button {
      padding: 12px 22px;
      border-radius: 999px;
      font-family: 'Sora', sans-serif;
      font-weight: 600;
      letter-spacing: 0.4px;
      border: 1px solid transparent;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .button.primary {
      background: linear-gradient(135deg, rgba(24, 229, 255, 0.9), rgba(89, 240, 141, 0.9));
      color: #05070b;
      box-shadow: 0 18px 36px rgba(24, 229, 255, 0.22);
    }

    .button.secondary {
      background: rgba(7, 12, 18, 0.7);
      border-color: rgba(24, 229, 255, 0.2);
      color: var(--ink);
    }

    .button:hover {
      transform: translateY(-2px);
      box-shadow: 0 20px 40px rgba(3, 10, 30, 0.35);
    }

    .grid {
      display: grid;
      gap: 22px;
    }

    .grid.two { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .grid.three { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }

    .card {
      background: var(--glass);
      border: 1px solid rgba(24, 229, 255, 0.18);
      border-radius: 22px;
      padding: 24px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(16px);
    }

    .card h3 {
      font-family: 'Sora', sans-serif;
      margin: 0 0 10px;
      font-size: 20px;
    }

    .card p {
      color: var(--muted);
      margin: 0;
      line-height: 1.6;
    }

    .tags {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .tag {
      padding: 8px 12px;
      border-radius: 999px;
      border: 1px solid rgba(24, 229, 255, 0.2);
      color: var(--muted);
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      background: rgba(7, 12, 18, 0.6);
    }

    .timeline {
      display: grid;
      gap: 18px;
    }

    .step {
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 14px;
      align-items: start;
    }

    .step-number {
      width: 34px;
      height: 34px;
      border-radius: 12px;
      background: rgba(24, 229, 255, 0.12);
      border: 1px solid rgba(24, 229, 255, 0.35);
      display: grid;
      place-items: center;
      font-family: 'Sora', sans-serif;
      font-weight: 600;
      color: var(--accent-3);
    }

    .section-title {
      font-family: 'Sora', sans-serif;
      font-size: 28px;
      margin: 0 0 12px;
    }

    .section-sub {
      color: var(--muted);
      margin: 0 0 24px;
      max-width: 720px;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      border-radius: 999px;
      padding: 8px 14px;
      background: rgba(7, 12, 18, 0.7);
      border: 1px solid rgba(24, 229, 255, 0.2);
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      letter-spacing: 0.6px;
      text-transform: uppercase;
      color: var(--accent-2);
    }

    .hero-panel {
      background: rgba(7, 12, 18, 0.85);
      border: 1px solid rgba(24, 229, 255, 0.22);
      border-radius: 24px;
      padding: 22px;
      box-shadow: 0 30px 70px rgba(2, 6, 12, 0.6);
      display: grid;
      gap: 18px;
      position: relative;
      overflow: hidden;
    }

    .hero-panel::before {
      content: "";
      position: absolute;
      inset: -40% -10% auto auto;
      width: 220px;
      height: 220px;
      background: radial-gradient(circle, rgba(24, 229, 255, 0.22), transparent 65%);
      opacity: 0.8;
    }

    .hero-panel-header {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.18em;
      color: var(--muted);
    }

    .status-dot {
      width: 10px;
      height: 10px;
      border-radius: 999px;
      background: var(--accent);
      box-shadow: 0 0 12px rgba(89, 240, 141, 0.6);
    }

    .hero-panel-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }

    .hero-metric {
      padding: 12px;
      border-radius: 14px;
      border: 1px solid rgba(24, 229, 255, 0.2);
      background: rgba(7, 12, 18, 0.7);
      display: grid;
      gap: 6px;
    }

    .hero-metric span {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--muted);
    }

    .hero-metric strong {
      font-family: 'Sora', sans-serif;
      font-size: 20px;
      color: var(--ink);
    }

    .hero-flow {
      display: grid;
      gap: 10px;
    }

    .hero-flow-step {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 12px;
      border-radius: 12px;
      background: rgba(7, 12, 18, 0.7);
      border: 1px dashed rgba(24, 229, 255, 0.35);
      font-size: 13px;
      color: var(--muted);
    }

    .footer {
      border-top: 1px solid var(--stroke);
      padding-top: 28px;
      color: var(--muted);
      display: flex;
      flex-wrap: wrap;
      gap: 18px;
      justify-content: space-between;
      font-size: 14px;
    }

    .lang-block { display: none; }
    .lang-inline { display: none; }

    body.lang-ar [data-lang="ar"].lang-block,
    body.lang-ca [data-lang="ca"].lang-block,
    body.lang-de [data-lang="de"].lang-block,
    body.lang-en [data-lang="en"].lang-block,
    body.lang-es [data-lang="es"].lang-block,
    body.lang-fr [data-lang="fr"].lang-block,
    body.lang-he [data-lang="he"].lang-block,
    body.lang-hi [data-lang="hi"].lang-block,
    body.lang-ja [data-lang="ja"].lang-block,
    body.lang-ko [data-lang="ko"].lang-block,
    body.lang-nl [data-lang="nl"].lang-block,
    body.lang-pt [data-lang="pt"].lang-block,
    body.lang-ru [data-lang="ru"].lang-block,
    body.lang-zh [data-lang="zh"].lang-block {
      display: block;
    }

    body.lang-ar [data-lang="ar"].lang-inline,
    body.lang-ca [data-lang="ca"].lang-inline,
    body.lang-de [data-lang="de"].lang-inline,
    body.lang-en [data-lang="en"].lang-inline,
    body.lang-es [data-lang="es"].lang-inline,
    body.lang-fr [data-lang="fr"].lang-inline,
    body.lang-he [data-lang="he"].lang-inline,
    body.lang-hi [data-lang="hi"].lang-inline,
    body.lang-ja [data-lang="ja"].lang-inline,
    body.lang-ko [data-lang="ko"].lang-inline,
    body.lang-nl [data-lang="nl"].lang-inline,
    body.lang-pt [data-lang="pt"].lang-inline,
    body.lang-ru [data-lang="ru"].lang-inline,
    body.lang-zh [data-lang="zh"].lang-inline {
      display: inline;
    }

    body.lang-ar,
    body.lang-he {
      direction: rtl;
    }

    body.lang-ar .nav,
    body.lang-he .nav {
      flex-direction: row-reverse;
    }

    .reveal {
      opacity: 0;
      transform: translateY(12px);
      animation: rise 0.8s ease forwards;
    }

    .reveal.delay-1 { animation-delay: 0.1s; }
    .reveal.delay-2 { animation-delay: 0.2s; }
    .reveal.delay-3 { animation-delay: 0.3s; }

    @keyframes rise {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 720px) {
      .nav { flex-direction: column; align-items: flex-start; }
      .page { padding: 24px 6vw 64px; }
      .hero-grid { grid-template-columns: 1fr; }
      .cta { width: 100%; }
      .button { width: 100%; justify-content: center; }
      .step { grid-template-columns: 1fr; }
      .step-number { margin-bottom: 4px; }
    }
  </style>
</head>
<body class="lang-es">
  <div class="page">
    <header class="nav">
      <div class="brand">
        <div class="brand-mark">
          <img src="favicon.ico" alt="ClickFix icon" />
        </div>
        <div>
          <div class="pill">
            <span class="lang-inline" data-lang="es">Defensa primero</span>
            <span class="lang-inline" data-lang="en">Defense-first</span>
            <span class="lang-inline" data-lang="pt">Defesa primeiro</span>
            <span class="lang-inline" data-lang="fr">Defense d abord</span>
            <span class="lang-inline" data-lang="de">Defense zuerst</span>
            <span class="lang-inline" data-lang="nl">Defense eerst</span>
            <span class="lang-inline" data-lang="ca">Defensa primer</span>
            <span class="lang-inline" data-lang="ru">Защита прежде всего</span>
            <span class="lang-inline" data-lang="ja">防御優先</span>
            <span class="lang-inline" data-lang="ko">방어 우선</span>
            <span class="lang-inline" data-lang="zh">防御优先</span>
            <span class="lang-inline" data-lang="hi">डिफेंस फर्स्ट</span>
            <span class="lang-inline" data-lang="ar">الدفاع اولا</span>
            <span class="lang-inline" data-lang="he">הגנה תחילה</span>
          </div>
          <div style="font-family: 'Sora', sans-serif; font-size: 18px;">ClickFix Mitigator</div>
        </div>
      </div>
      <div class="lang" aria-label="Language selector">
        <span class="lang-label">Language</span>
        <select id="lang-select" aria-label="Select language">
          <option value="es">ES - Español</option>
          <option value="en">EN - English</option>
          <option value="pt">PT - Português</option>
          <option value="fr">FR - Français</option>
          <option value="de">DE - Deutsch</option>
          <option value="nl">NL - Nederlands</option>
          <option value="ca">CA - Català</option>
          <option value="ru">RU - Русский</option>
          <option value="ja">JA - 日本語</option>
          <option value="ko">KO - 한국어</option>
          <option value="zh">ZH - 中文</option>
          <option value="hi">HI - हिंदी</option>
          <option value="ar">AR - العربية</option>
          <option value="he">HE - עברית</option>
        </select>
      </div>
    </header>

    <section class="hero">
      <div class="hero-grid">
        <div class="hero-copy">
      <div class="eyebrow">
        <span class="lang-inline" data-lang="es">Extension lista para instalar</span>
        <span class="lang-inline" data-lang="en">Install-ready extension</span>
        <span class="lang-inline" data-lang="pt">Extensao pronta para instalar</span>
        <span class="lang-inline" data-lang="fr">Extension prete a installer</span>
        <span class="lang-inline" data-lang="de">Installationsbereite Erweiterung</span>
        <span class="lang-inline" data-lang="nl">Installatieklare extensie</span>
        <span class="lang-inline" data-lang="ca">Extensio llesta per instalar</span>
        <span class="lang-inline" data-lang="ru">Расширение готово к установке</span>
        <span class="lang-inline" data-lang="ja">すぐにインストール可能な拡張機能</span>
        <span class="lang-inline" data-lang="ko">바로 설치 가능한 확장 프로그램</span>
        <span class="lang-inline" data-lang="zh">可直接安装的扩展</span>
        <span class="lang-inline" data-lang="hi">इंस्टॉल के लिए तैयार एक्सटेंशन</span>
        <span class="lang-inline" data-lang="ar">امتداد جاهز للتثبيت</span>
        <span class="lang-inline" data-lang="he">תוסף מוכן להתקנה</span>
      </div>
      <h1 class="reveal">
        <span class="lang-inline" data-lang="es">Instala la extension que corta ClickFix antes del ultimo click.</span>
        <span class="lang-inline" data-lang="en">Install the extension that cuts ClickFix before the final click.</span>
        <span class="lang-inline" data-lang="pt">Instale a extensao que corta o ClickFix antes do clique final.</span>
        <span class="lang-inline" data-lang="fr">Installez l extension qui coupe ClickFix avant le clic final.</span>
        <span class="lang-inline" data-lang="de">Installiere die Erweiterung, die ClickFix vor dem letzten Klick stoppt.</span>
        <span class="lang-inline" data-lang="nl">Installeer de extensie die ClickFix stopt voor de laatste klik.</span>
        <span class="lang-inline" data-lang="ca">Instal la extensio que talla ClickFix abans de l ultim clic.</span>
        <span class="lang-inline" data-lang="ru">Установите расширение, которое останавливает ClickFix до последнего клика.</span>
        <span class="lang-inline" data-lang="ja">最後のクリックの前にClickFixを止める拡張機能をインストール。</span>
        <span class="lang-inline" data-lang="ko">마지막 클릭 전에 ClickFix를 차단하는 확장 프로그램을 설치하세요.</span>
        <span class="lang-inline" data-lang="zh">安装在最后一次点击前阻止 ClickFix 的扩展。</span>
        <span class="lang-inline" data-lang="hi">वह एक्सटेंशन इंस्टॉल करें जो आखिरी क्लिक से पहले ClickFix रोक देता है।</span>
        <span class="lang-inline" data-lang="ar">ثبّت الامتداد الذي يوقف ClickFix قبل النقرة الأخيرة.</span>
        <span class="lang-inline" data-lang="he">התקן את התוסף שעוצר את ClickFix לפני הלחיצה האחרונה.</span>
      </h1>
      <p class="lang-block reveal delay-1" data-lang="es">
        ClickFix Mitigator blinda la navegacion con deteccion contextual, bloqueo inteligente y evidencia clara para el equipo
        de seguridad.
      </p>
      <p class="lang-block reveal delay-1" data-lang="en">
        ClickFix Mitigator hardens browsing with contextual detection, smart blocking, and clear evidence for security teams.
      </p>
      <p class="lang-block reveal delay-1" data-lang="pt">
        ClickFix Mitigator reforca a navegacao com deteccao contextual, bloqueio inteligente e evidencia clara para times de
        seguranca.
      </p>
      <p class="lang-block reveal delay-1" data-lang="fr">
        ClickFix Mitigator renforce la navigation avec detection contextuelle, blocage intelligent et preuves claires pour les
        equipes de securite.
      </p>
      <p class="lang-block reveal delay-1" data-lang="de">
        ClickFix Mitigator sichert das Browsen mit kontextueller Erkennung, intelligentem Blocking und klaren Nachweisen fuer
        Sicherheitsteams.
      </p>
      <p class="lang-block reveal delay-1" data-lang="nl">
        ClickFix Mitigator versterkt het browsen met contextdetectie, slimme blokkering en duidelijke bewijslast voor
        securityteams.
      </p>
      <p class="lang-block reveal delay-1" data-lang="ca">
        ClickFix Mitigator blinda la navegacio amb deteccio contextual, bloqueig intel·ligent i evidencies clares per als equips
        de seguretat.
      </p>
      <p class="lang-block reveal delay-1" data-lang="ru">
        ClickFix Mitigator усиливает браузинг за счет контекстного обнаружения, умного блокирования и четких доказательств для
        команд безопасности.
      </p>
      <p class="lang-block reveal delay-1" data-lang="ja">
        ClickFix Mitigator は、文脈検知、スマートなブロック、セキュリティチーム向けの明確な証跡でブラウジングを強化します。
      </p>
      <p class="lang-block reveal delay-1" data-lang="ko">
        ClickFix Mitigator는 문맥 기반 탐지, 스마트 차단, 보안 팀을 위한 명확한 증거로 브라우징을 강화합니다.
      </p>
      <p class="lang-block reveal delay-1" data-lang="zh">
        ClickFix Mitigator 通过情境检测、智能阻断和清晰证据来强化浏览安全。
      </p>
      <p class="lang-block reveal delay-1" data-lang="hi">
        ClickFix Mitigator संदर्भ-आधारित डिटेक्शन, स्मार्ट ब्लॉकिंग और सुरक्षा टीमों के लिए स्पष्ट प्रमाण के साथ ब्राउज़िंग को
        मजबूत करता है।
      </p>
      <p class="lang-block reveal delay-1" data-lang="ar">
        يعزز ClickFix Mitigator التصفح عبر كشف سياقي وحظر ذكي وأدلة واضحة لفرق الامن.
      </p>
      <p class="lang-block reveal delay-1" data-lang="he">
        ClickFix Mitigator מחזק את הגלישה באמצעות זיהוי הקשרי, חסימה חכמה וראיות ברורות לצוותי האבטחה.
      </p>
      <div class="tags hero-tags reveal delay-1">
        <span class="tag lang-inline" data-lang="es">Extension MV3</span>
        <span class="tag lang-inline" data-lang="es">Escudo de portapapeles</span>
        <span class="tag lang-inline" data-lang="es">Alertas en tiempo real</span>
        <span class="tag lang-inline" data-lang="en">MV3 Extension</span>
        <span class="tag lang-inline" data-lang="en">Clipboard Shield</span>
        <span class="tag lang-inline" data-lang="en">Real-time Alerts</span>
        <span class="tag lang-inline" data-lang="pt">Extensao MV3</span>
        <span class="tag lang-inline" data-lang="pt">Escudo de clipboard</span>
        <span class="tag lang-inline" data-lang="pt">Alertas em tempo real</span>
        <span class="tag lang-inline" data-lang="fr">Extension MV3</span>
        <span class="tag lang-inline" data-lang="fr">Bouclier presse-papiers</span>
        <span class="tag lang-inline" data-lang="fr">Alertes temps reel</span>
        <span class="tag lang-inline" data-lang="de">MV3-Erweiterung</span>
        <span class="tag lang-inline" data-lang="de">Zwischenablage-Schutz</span>
        <span class="tag lang-inline" data-lang="de">Echtzeitwarnungen</span>
        <span class="tag lang-inline" data-lang="nl">MV3-extensie</span>
        <span class="tag lang-inline" data-lang="nl">Klembordbescherming</span>
        <span class="tag lang-inline" data-lang="nl">Realtime waarschuwingen</span>
        <span class="tag lang-inline" data-lang="ca">Extensio MV3</span>
        <span class="tag lang-inline" data-lang="ca">Escut del porta-retalls</span>
        <span class="tag lang-inline" data-lang="ca">Alertes en temps real</span>
        <span class="tag lang-inline" data-lang="ru">Расширение MV3</span>
        <span class="tag lang-inline" data-lang="ru">Защита буфера обмена</span>
        <span class="tag lang-inline" data-lang="ru">Оповещения в реальном времени</span>
        <span class="tag lang-inline" data-lang="ja">MV3拡張</span>
        <span class="tag lang-inline" data-lang="ja">クリップボード保護</span>
        <span class="tag lang-inline" data-lang="ja">リアルタイム警告</span>
        <span class="tag lang-inline" data-lang="ko">MV3 확장</span>
        <span class="tag lang-inline" data-lang="ko">클립보드 보호</span>
        <span class="tag lang-inline" data-lang="ko">실시간 경고</span>
        <span class="tag lang-inline" data-lang="zh">MV3 扩展</span>
        <span class="tag lang-inline" data-lang="zh">剪贴板防护</span>
        <span class="tag lang-inline" data-lang="zh">实时警报</span>
        <span class="tag lang-inline" data-lang="hi">MV3 एक्सटेंशन</span>
        <span class="tag lang-inline" data-lang="hi">क्लिपबोर्ड सुरक्षा</span>
        <span class="tag lang-inline" data-lang="hi">रीयल-टाइम अलर्ट</span>
        <span class="tag lang-inline" data-lang="ar">امتداد MV3</span>
        <span class="tag lang-inline" data-lang="ar">حماية الحافظة</span>
        <span class="tag lang-inline" data-lang="ar">تنبيهات فورية</span>
        <span class="tag lang-inline" data-lang="he">הרחבת MV3</span>
        <span class="tag lang-inline" data-lang="he">הגנת לוח גזירים</span>
        <span class="tag lang-inline" data-lang="he">התראות בזמן אמת</span>
      </div>
      <div class="cta reveal delay-2">
        <a class="button primary" href="https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa" target="_blank" rel="noopener">
          <span class="lang-inline" data-lang="es">Instalar extension</span>
          <span class="lang-inline" data-lang="en">Install Extension</span>
          <span class="lang-inline" data-lang="pt">Instalar extensao</span>
          <span class="lang-inline" data-lang="fr">Installer l extension</span>
          <span class="lang-inline" data-lang="de">Erweiterung installieren</span>
          <span class="lang-inline" data-lang="nl">Extensie installeren</span>
          <span class="lang-inline" data-lang="ca">Instal·lar extensio</span>
          <span class="lang-inline" data-lang="ru">Установить расширение</span>
          <span class="lang-inline" data-lang="ja">拡張機能をインストール</span>
          <span class="lang-inline" data-lang="ko">확장 프로그램 설치</span>
          <span class="lang-inline" data-lang="zh">安装扩展</span>
          <span class="lang-inline" data-lang="hi">एक्सटेंशन इंस्टॉल करें</span>
          <span class="lang-inline" data-lang="ar">تثبيت الامتداد</span>
          <span class="lang-inline" data-lang="he">התקן תוסף</span>
        </a>
        <a class="button secondary" href="#components">
          <span class="lang-inline" data-lang="es">Ver Componentes</span>
          <span class="lang-inline" data-lang="en">See Components</span>
          <span class="lang-inline" data-lang="pt">Ver Componentes</span>
          <span class="lang-inline" data-lang="fr">Voir les Composants</span>
          <span class="lang-inline" data-lang="de">Komponenten ansehen</span>
          <span class="lang-inline" data-lang="nl">Componenten bekijken</span>
          <span class="lang-inline" data-lang="ca">Veure components</span>
          <span class="lang-inline" data-lang="ru">Посмотреть компоненты</span>
          <span class="lang-inline" data-lang="ja">コンポーネントを見る</span>
          <span class="lang-inline" data-lang="ko">구성 요소 보기</span>
          <span class="lang-inline" data-lang="zh">查看组件</span>
          <span class="lang-inline" data-lang="hi">कंपोनेंट्स देखें</span>
          <span class="lang-inline" data-lang="ar">عرض المكونات</span>
          <span class="lang-inline" data-lang="he">הצג רכיבים</span>
        </a>
      </div>
      <div class="cta-note reveal delay-2">
        <span class="lang-inline" data-lang="es">Ya tienes backend?</span>
        <span class="lang-inline" data-lang="en">Already have the backend?</span>
        <span class="lang-inline" data-lang="pt">Ja tem o backend?</span>
        <span class="lang-inline" data-lang="fr">Vous avez deja le backend?</span>
        <span class="lang-inline" data-lang="de">Hast du bereits das Backend?</span>
        <span class="lang-inline" data-lang="nl">Heb je al de backend?</span>
        <span class="lang-inline" data-lang="ca">Ja tens backend?</span>
        <span class="lang-inline" data-lang="ru">У вас уже есть бэкенд?</span>
        <span class="lang-inline" data-lang="ja">すでにバックエンドがありますか？</span>
        <span class="lang-inline" data-lang="ko">이미 백엔드가 있나요?</span>
        <span class="lang-inline" data-lang="zh">已经有后端了吗？</span>
        <span class="lang-inline" data-lang="hi">क्या आपके पास पहले से backend है?</span>
        <span class="lang-inline" data-lang="ar">لديك بالفعل الواجهة الخلفية؟</span>
        <span class="lang-inline" data-lang="he">כבר יש לך backend?</span>
        <a href="dashboard.php">
          <span class="lang-inline" data-lang="es">Abrir dashboard</span>
          <span class="lang-inline" data-lang="en">Open dashboard</span>
          <span class="lang-inline" data-lang="pt">Abrir dashboard</span>
          <span class="lang-inline" data-lang="fr">Ouvrir le dashboard</span>
          <span class="lang-inline" data-lang="de">Dashboard oeffnen</span>
          <span class="lang-inline" data-lang="nl">Dashboard openen</span>
          <span class="lang-inline" data-lang="ca">Obrir dashboard</span>
          <span class="lang-inline" data-lang="ru">Открыть дашборд</span>
          <span class="lang-inline" data-lang="ja">ダッシュボードを開く</span>
          <span class="lang-inline" data-lang="ko">대시보드 열기</span>
          <span class="lang-inline" data-lang="zh">打开仪表板</span>
          <span class="lang-inline" data-lang="hi">डैशबोर्ड खोलें</span>
          <span class="lang-inline" data-lang="ar">فتح لوحة التحكم</span>
          <span class="lang-inline" data-lang="he">פתח דשבורד</span>
        </a>
      </div>
        </div>
        <div class="hero-panel reveal delay-3">
          <div class="hero-panel-header">
            <span class="status-dot"></span>
            <span>Live telemetry</span>
          </div>
          <div class="hero-panel-grid">
            <div class="hero-metric">
              <span>Signals / 24h</span>
              <strong>1.2k+</strong>
            </div>
            <div class="hero-metric">
              <span>Clipboard blocks</span>
              <strong>430+</strong>
            </div>
            <div class="hero-metric">
              <span>Risk regions</span>
              <strong>40+</strong>
            </div>
            <div class="hero-metric">
              <span>Active agents</span>
              <strong>500+</strong>
            </div>
          </div>
          <div class="hero-flow">
            <div class="hero-flow-step">
              <span>Detect contextual commands</span>
              <strong>01</strong>
            </div>
            <div class="hero-flow-step">
              <span>Interrupt & alert</span>
              <strong>02</strong>
            </div>
            <div class="hero-flow-step">
              <span>Capture evidence</span>
              <strong>03</strong>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="grid three">
      <div class="card reveal">
        <h3 class="lang-block" data-lang="es">Instalacion directa</h3>
        <h3 class="lang-block" data-lang="en">Direct install</h3>
        <h3 class="lang-block" data-lang="pt">Instalacao direta</h3>
        <h3 class="lang-block" data-lang="fr">Installation directe</h3>
        <h3 class="lang-block" data-lang="de">Direkte Installation</h3>
        <h3 class="lang-block" data-lang="nl">Directe installatie</h3>
        <h3 class="lang-block" data-lang="ca">Instal·lacio directa</h3>
        <h3 class="lang-block" data-lang="ru">Прямая установка</h3>
        <h3 class="lang-block" data-lang="ja">直接インストール</h3>
        <h3 class="lang-block" data-lang="ko">직접 설치</h3>
        <h3 class="lang-block" data-lang="zh">直接安装</h3>
        <h3 class="lang-block" data-lang="hi">सीधा इंस्टॉल</h3>
        <h3 class="lang-block" data-lang="ar">تثبيت مباشر</h3>
        <h3 class="lang-block" data-lang="he">התקנה ישירה</h3>
        <p class="lang-block" data-lang="es">Disponible en Chrome Web Store para despliegues rapidos y controlados.</p>
        <p class="lang-block" data-lang="en">Available on the Chrome Web Store for quick, controlled rollouts.</p>
        <p class="lang-block" data-lang="pt">Disponivel na Chrome Web Store para implantacoes rapidas e controladas.</p>
        <p class="lang-block" data-lang="fr">Disponible sur le Chrome Web Store pour des deploiements rapides et controles.</p>
        <p class="lang-block" data-lang="de">Im Chrome Web Store verfuegbar fuer schnelle, kontrollierte Rollouts.</p>
        <p class="lang-block" data-lang="nl">Beschikbaar in de Chrome Web Store voor snelle, gecontroleerde uitrol.</p>
        <p class="lang-block" data-lang="ca">Disponible a la Chrome Web Store per a desplegaments rapids i controlats.</p>
        <p class="lang-block" data-lang="ru">Доступно в Chrome Web Store для быстрого и контролируемого развертывания.</p>
        <p class="lang-block" data-lang="ja">Chrome Web Store で提供。迅速で管理された展開に最適。</p>
        <p class="lang-block" data-lang="ko">Chrome Web Store에서 제공되어 빠르고 통제된 배포가 가능합니다.</p>
        <p class="lang-block" data-lang="zh">在 Chrome 网上应用店提供，便于快速且可控的部署。</p>
        <p class="lang-block" data-lang="hi">Chrome Web Store पर उपलब्ध, तेज़ और नियंत्रित रोलआउट के लिए।</p>
        <p class="lang-block" data-lang="ar">متاح في متجر Chrome للتوزيعات السريعة والمضبوطة.</p>
        <p class="lang-block" data-lang="he">זמין בחנות Chrome לפריסות מהירות ומבוקרות.</p>
      </div>
      <div class="card reveal delay-1">
        <h3 class="lang-block" data-lang="es">Bloqueo con contexto</h3>
        <h3 class="lang-block" data-lang="en">Context-aware blocking</h3>
        <h3 class="lang-block" data-lang="pt">Bloqueio com contexto</h3>
        <h3 class="lang-block" data-lang="fr">Blocage contextuel</h3>
        <h3 class="lang-block" data-lang="de">Kontextbasiertes Blockieren</h3>
        <h3 class="lang-block" data-lang="nl">Contextbewuste blokkering</h3>
        <h3 class="lang-block" data-lang="ca">Bloqueig amb context</h3>
        <h3 class="lang-block" data-lang="ru">Контекстная блокировка</h3>
        <h3 class="lang-block" data-lang="ja">文脈に基づくブロック</h3>
        <h3 class="lang-block" data-lang="ko">상황 인지 차단</h3>
        <h3 class="lang-block" data-lang="zh">情境化阻断</h3>
        <h3 class="lang-block" data-lang="hi">संदर्भ-आधारित ब्लॉकिंग</h3>
        <h3 class="lang-block" data-lang="ar">حظر معتمد على السياق</h3>
        <h3 class="lang-block" data-lang="he">חסימה מודעת להקשר</h3>
        <p class="lang-block" data-lang="es">Detecta senales de ClickFix, protege el portapapeles y detiene el flujo.</p>
        <p class="lang-block" data-lang="en">Detects ClickFix signals, protects the clipboard, and stops the flow.</p>
        <p class="lang-block" data-lang="pt">Detecta sinais de ClickFix, protege o clipboard e para o fluxo.</p>
        <p class="lang-block" data-lang="fr">Detecte les signaux ClickFix, protege le presse-papiers et stoppe le flux.</p>
        <p class="lang-block" data-lang="de">Erkennt ClickFix-Signale, schuetzt die Zwischenablage und stoppt den Ablauf.</p>
        <p class="lang-block" data-lang="nl">Detecteert ClickFix-signalen, beschermt het klembord en stopt de flow.</p>
        <p class="lang-block" data-lang="ca">Detecta senyals de ClickFix, protegeix el porta-retalls i atura el flux.</p>
        <p class="lang-block" data-lang="ru">Обнаруживает сигналы ClickFix, защищает буфер обмена и останавливает поток.</p>
        <p class="lang-block" data-lang="ja">ClickFixの兆候を検知し、クリップボードを保護してフローを停止します。</p>
        <p class="lang-block" data-lang="ko">ClickFix 신호를 감지하고 클립보드를 보호하며 흐름을 차단합니다.</p>
        <p class="lang-block" data-lang="zh">检测 ClickFix 信号，保护剪贴板并阻断流程。</p>
        <p class="lang-block" data-lang="hi">ClickFix संकेतों का पता लगाता है, क्लिपबोर्ड की सुरक्षा करता है और फ्लो रोकता है।</p>
        <p class="lang-block" data-lang="ar">يرصد اشارات ClickFix، يحمي الحافظة ويوقف التدفق.</p>
        <p class="lang-block" data-lang="he">מזהה אותות ClickFix, מגן על לוח הגזירים ועוצר את הזרימה.</p>
      </div>
      <div class="card reveal delay-2">
        <h3 class="lang-block" data-lang="es">Evidencia para SOC</h3>
        <h3 class="lang-block" data-lang="en">Evidence for SOC</h3>
        <h3 class="lang-block" data-lang="pt">Evidencia para SOC</h3>
        <h3 class="lang-block" data-lang="fr">Preuves pour SOC</h3>
        <h3 class="lang-block" data-lang="de">Nachweise fuer SOC</h3>
        <h3 class="lang-block" data-lang="nl">Bewijs voor SOC</h3>
        <h3 class="lang-block" data-lang="ca">Evidencia per a SOC</h3>
        <h3 class="lang-block" data-lang="ru">Доказательства для SOC</h3>
        <h3 class="lang-block" data-lang="ja">SOC向け証拠</h3>
        <h3 class="lang-block" data-lang="ko">SOC용 증거</h3>
        <h3 class="lang-block" data-lang="zh">SOC 证据</h3>
        <h3 class="lang-block" data-lang="hi">SOC के लिए प्रमाण</h3>
        <h3 class="lang-block" data-lang="ar">ادلة لـ SOC</h3>
        <h3 class="lang-block" data-lang="he">ראיות ל‑SOC</h3>
        <p class="lang-block" data-lang="es">Reportes claros para analistas, blue teams y respuesta rapida.</p>
        <p class="lang-block" data-lang="en">Clear reports for analysts, blue teams, and rapid response.</p>
        <p class="lang-block" data-lang="pt">Relatorios claros para analistas, blue teams e resposta rapida.</p>
        <p class="lang-block" data-lang="fr">Rapports clairs pour analystes, blue teams et reponse rapide.</p>
        <p class="lang-block" data-lang="de">Klare Reports fuer Analysten, Blue Teams und schnelle Reaktion.</p>
        <p class="lang-block" data-lang="nl">Duidelijke rapporten voor analisten, blue teams en snelle respons.</p>
        <p class="lang-block" data-lang="ca">Informes clars per a analistes, blue teams i resposta rapida.</p>
        <p class="lang-block" data-lang="ru">Четкие отчеты для аналитиков, blue teams и быстрого реагирования.</p>
        <p class="lang-block" data-lang="ja">アナリストやBlue Team向けの明確なレポートで迅速対応。</p>
        <p class="lang-block" data-lang="ko">분석가와 블루팀을 위한 명확한 리포트로 신속 대응.</p>
        <p class="lang-block" data-lang="zh">为分析师和蓝队提供清晰报告，支持快速响应。</p>
        <p class="lang-block" data-lang="hi">विश्लेषकों, ब्लू टीमों और तेज़ प्रतिक्रिया के लिए स्पष्ट रिपोर्ट.</p>
        <p class="lang-block" data-lang="ar">تقارير واضحة للمحللين وفرق الدفاع والاستجابة السريعة.</p>
        <p class="lang-block" data-lang="he">דוחות ברורים לאנליסטים, צוותי כחול ותגובה מהירה.</p>
      </div>
    </section>

    <section class="grid two">
      <div class="card reveal">
        <h3 class="lang-block" data-lang="es">Que es ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="en">What ClickFix Mitigator Is</h3>
        <h3 class="lang-block" data-lang="pt">O que e ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="fr">Qu est ce que ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="de">Was ClickFix Mitigator ist</h3>
        <h3 class="lang-block" data-lang="nl">Wat ClickFix Mitigator is</h3>
        <h3 class="lang-block" data-lang="ca">Que es ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="ru">Что такое ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="ja">ClickFix Mitigator とは</h3>
        <h3 class="lang-block" data-lang="ko">ClickFix Mitigator란</h3>
        <h3 class="lang-block" data-lang="zh">什么是 ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="hi">ClickFix Mitigator क्या है</h3>
        <h3 class="lang-block" data-lang="ar">ما هو ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="he">מהו ClickFix Mitigator</h3>
        <p class="lang-block" data-lang="es">Un kit completo con extension, agente Windows y backend PHP+SQLite para frenar ataques de ingenieria social basados en comandos.</p>
        <p class="lang-block" data-lang="en">A full kit with browser extension, Windows agent, and PHP+SQLite backend to stop command-based social engineering attacks.</p>
        <p class="lang-block" data-lang="pt">Um kit completo com extensao, agente Windows e backend PHP+SQLite para frear ataques de engenharia social baseados em comandos.</p>
        <p class="lang-block" data-lang="fr">Un kit complet avec extension, agent Windows et backend PHP+SQLite pour stopper les attaques basees sur des commandes.</p>
        <p class="lang-block" data-lang="de">Ein Komplettset mit Browser-Erweiterung, Windows-Agent und PHP+SQLite-Backend, um befehlbasierte Social-Engineering-Angriffe zu stoppen.</p>
        <p class="lang-block" data-lang="nl">Een complete set met browserextensie, Windows-agent en PHP+SQLite-backend om commando-gebaseerde social-engineeringaanvallen te stoppen.</p>
        <p class="lang-block" data-lang="ca">Un kit complet amb extensio, agent Windows i backend PHP+SQLite per frenar atacs d'enginyeria social basats en comandes.</p>
        <p class="lang-block" data-lang="ru">Полный набор с расширением браузера, агентом Windows и backend на PHP+SQLite для остановки командных social engineering атак.</p>
        <p class="lang-block" data-lang="ja">ブラウザ拡張、Windowsエージェント、PHP+SQLiteバックエンドを備えた、コマンド型ソーシャルエンジニアリング攻撃を止めるフルキット。</p>
        <p class="lang-block" data-lang="ko">브라우저 확장, Windows 에이전트, PHP+SQLite 백엔드로 구성된 전체 키트로, 명령 기반 소셜 엔지니어링 공격을 차단합니다.</p>
        <p class="lang-block" data-lang="zh">包含浏览器扩展、Windows 代理和 PHP+SQLite 后端的完整套件，用于阻止基于命令的社会工程攻击。</p>
        <p class="lang-block" data-lang="hi">ब्राउज़र एक्सटेंशन, Windows एजेंट और PHP+SQLite बैकएंड वाला पूरा किट, जो कमांड-आधारित सोशल इंजीनियरिंग हमलों को रोकता है।</p>
        <p class="lang-block" data-lang="ar">مجموعة كاملة تشمل امتداد المتصفح ووكيل Windows وواجهة خلفية PHP+SQLite لإيقاف هجمات الهندسة الاجتماعية القائمة على الاوامر.</p>
        <p class="lang-block" data-lang="he">ערכה מלאה עם תוסף דפדפן, סוכן Windows ו־backend PHP+SQLite כדי לעצור מתקפות הנדסה חברתית מבוססות פקודות.</p>
      </div>
      <div class="card reveal delay-1">
        <h3 class="lang-block" data-lang="es">Su funcion principal</h3>
        <h3 class="lang-block" data-lang="en">Primary Mission</h3>
        <h3 class="lang-block" data-lang="pt">Funcao principal</h3>
        <h3 class="lang-block" data-lang="fr">Mission principale</h3>
        <h3 class="lang-block" data-lang="de">Hauptaufgabe</h3>
        <h3 class="lang-block" data-lang="nl">Hoofdfunctie</h3>
        <h3 class="lang-block" data-lang="ca">Funcio principal</h3>
        <h3 class="lang-block" data-lang="ru">Основная миссия</h3>
        <h3 class="lang-block" data-lang="ja">主な目的</h3>
        <h3 class="lang-block" data-lang="ko">핵심 목적</h3>
        <h3 class="lang-block" data-lang="zh">核心任务</h3>
        <h3 class="lang-block" data-lang="hi">मुख्य उद्देश्य</h3>
        <h3 class="lang-block" data-lang="ar">المهمة الرئيسية</h3>
        <h3 class="lang-block" data-lang="he">המשימה הראשית</h3>
        <p class="lang-block" data-lang="es">Detectar ClickFix en tiempo real, interrumpir el flujo y dejar trazabilidad para analistas y blue teams.</p>
        <p class="lang-block" data-lang="en">Detect ClickFix in real time, interrupt the flow, and provide traceability for analysts and blue teams.</p>
        <p class="lang-block" data-lang="pt">Detectar ClickFix em tempo real, interromper o fluxo e manter rastreabilidade para analistas e blue teams.</p>
        <p class="lang-block" data-lang="fr">Detecter ClickFix en temps reel, interrompre le flux et fournir une tracabilite pour les analysts et blue teams.</p>
        <p class="lang-block" data-lang="de">ClickFix in Echtzeit erkennen, den Ablauf unterbrechen und Nachvollziehbarkeit fuer Analysten und Blue Teams schaffen.</p>
        <p class="lang-block" data-lang="nl">ClickFix in realtime detecteren, de flow onderbreken en traceerbaarheid bieden voor analisten en blue teams.</p>
        <p class="lang-block" data-lang="ca">Detectar ClickFix en temps real, interrompre el flux i donar tracabilitat per a analistes i blue teams.</p>
        <p class="lang-block" data-lang="ru">В реальном времени обнаруживать ClickFix, прерывать поток и обеспечивать трассируемость для аналитиков и blue teams.</p>
        <p class="lang-block" data-lang="ja">ClickFixをリアルタイムで検知し、フローを遮断してアナリストとBlue Team向けのトレーサビリティを提供。</p>
        <p class="lang-block" data-lang="ko">ClickFix를 실시간으로 탐지하고 흐름을 중단하며 분석가와 블루팀을 위한 추적성을 제공합니다.</p>
        <p class="lang-block" data-lang="zh">实时检测 ClickFix，打断流程，并为分析师和蓝队提供可追溯性。</p>
        <p class="lang-block" data-lang="hi">ClickFix को रियल-टाइम में detect करना, फ्लो रोकना और विश्लेषकों व ब्लू टीमों के लिए ट्रेसबिलिटी देना।</p>
        <p class="lang-block" data-lang="ar">اكتشاف ClickFix في الوقت الحقيقي، قطع التدفق، وتوفير قابلية تتبع للمحللين وفرق الدفاع.</p>
        <p class="lang-block" data-lang="he">לזהות ClickFix בזמן אמת, לקטוע את הזרימה ולספק עקיבות לאנליסטים ולצוותי כחול.</p>
      </div>
    </section>

    <section class="card reveal">
      <div class="pill">
        <span class="lang-inline" data-lang="es">Inteligencia de senales</span>
        <span class="lang-inline" data-lang="en">Signal Intelligence</span>
        <span class="lang-inline" data-lang="pt">Inteligencia de sinais</span>
        <span class="lang-inline" data-lang="fr">Renseignement de signaux</span>
        <span class="lang-inline" data-lang="de">Signal-Intelligenz</span>
        <span class="lang-inline" data-lang="nl">Signaalinlichtingen</span>
        <span class="lang-inline" data-lang="ca">Intelligencia de senyals</span>
        <span class="lang-inline" data-lang="ru">Сигнальная разведка</span>
        <span class="lang-inline" data-lang="ja">シグナル・インテリジェンス</span>
        <span class="lang-inline" data-lang="ko">신호 인텔리전스</span>
        <span class="lang-inline" data-lang="zh">信号情报</span>
        <span class="lang-inline" data-lang="hi">सिग्नल इंटेलिजेंस</span>
        <span class="lang-inline" data-lang="ar">استخبارات الاشارات</span>
        <span class="lang-inline" data-lang="he">מודיעין אותות</span>
      </div>
      <h2 class="section-title lang-block" data-lang="es">Senales que monitorea</h2>
      <h2 class="section-title lang-block" data-lang="en">Signals It Watches</h2>
      <h2 class="section-title lang-block" data-lang="pt">Sinais monitorados</h2>
      <h2 class="section-title lang-block" data-lang="fr">Signaux surveilles</h2>
      <h2 class="section-title lang-block" data-lang="de">Signale, die es beobachtet</h2>
      <h2 class="section-title lang-block" data-lang="nl">Signalen die het bewaakt</h2>
      <h2 class="section-title lang-block" data-lang="ca">Senyals que monitora</h2>
      <h2 class="section-title lang-block" data-lang="ru">Сигналы, которые отслеживает</h2>
      <h2 class="section-title lang-block" data-lang="ja">監視するシグナル</h2>
      <h2 class="section-title lang-block" data-lang="ko">감시하는 신호</h2>
      <h2 class="section-title lang-block" data-lang="zh">监测的信号</h2>
      <h2 class="section-title lang-block" data-lang="hi">यह जिन संकेतों पर नजर रखता है</h2>
      <h2 class="section-title lang-block" data-lang="ar">الاشارات التي يراقبها</h2>
      <h2 class="section-title lang-block" data-lang="he">האותות שהוא מנטר</h2>
      <p class="section-sub lang-block" data-lang="es">Detecta patrones de comandos, discrepancias del portapapeles y contextos tipicos de engano (Win+R, prompts falsos, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="en">Detects command patterns, clipboard mismatches, and common deception context (Win+R, fake prompts, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="pt">Detecta padroes de comando, discrepancias do clipboard e contextos tipicos de engano (Win+R, prompts falsos, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="fr">Detecte les patterns de commande, les ecarts du presse-papiers et les contextes typiques de piege (Win+R, faux prompts, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="de">Erkennt Befehlsmuster, Zwischenablage-Abweichungen und typische Tauschungs-Kontexte (Win+R, falsche Prompts, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="nl">Detecteert commandopatronen, klembordafwijkingen en typische misleidingscontext (Win+R, nep-prompts, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="ca">Detecta patrons de comandes, discrepancies del porta-retalls i contextos tipics d'engany (Win+R, prompts falsos, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="ru">Обнаруживает шаблоны команд, несоответствия буфера обмена и типичные контексты обмана (Win+R, поддельные приглашения, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="ja">コマンドのパターン、クリップボードの不一致、典型的な詐欺文脈（Win+R、偽プロンプト、偽CAPTCHA）を検知します。</p>
      <p class="section-sub lang-block" data-lang="ko">명령 패턴, 클립보드 불일치, 전형적인 기만 맥락(Win+R, 가짜 프롬프트, 가짜 CAPTCHA)을 탐지합니다.</p>
      <p class="section-sub lang-block" data-lang="zh">检测命令模式、剪贴板不一致以及典型欺骗场景（Win+R、虚假提示、假 CAPTCHA）。</p>
      <p class="section-sub lang-block" data-lang="hi">कमांड पैटर्न, क्लिपबोर्ड मिसमैच और सामान्य धोखा संदर्भ (Win+R, नकली prompts, fake CAPTCHA) का पता लगाता है।</p>
      <p class="section-sub lang-block" data-lang="ar">يرصد انماط الاوامر، اختلافات الحافظة، وسياقات الخداع الشائعة (Win+R، مطالبات مزيفة، fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="he">מזהה דפוסי פקודות, אי-התאמות בלוח הגזירים, והקשרים אופייניים להטעיה (Win+R, פרומפטים מזויפים, fake CAPTCHA).</p>
      <div class="tags">
        <span class="tag">PowerShell</span>
        <span class="tag">cmd.exe</span>
        <span class="tag">mshta</span>
        <span class="tag">rundll32</span>
        <span class="tag">Clipboard mismatch</span>
        <span class="tag">Fullscreen trap</span>
        <span class="tag">Nested iframes</span>
        <span class="tag">Win+R guidance</span>
      </div>
    </section>

    <section class="grid two" id="components">
      <div class="card reveal">
        <div class="pill">
          <span class="lang-inline" data-lang="es">Como funciona</span>
          <span class="lang-inline" data-lang="en">How It Works</span>
          <span class="lang-inline" data-lang="pt">Como funciona</span>
          <span class="lang-inline" data-lang="fr">Comment ca marche</span>
          <span class="lang-inline" data-lang="de">So funktioniert es</span>
          <span class="lang-inline" data-lang="nl">Hoe het werkt</span>
          <span class="lang-inline" data-lang="ca">Com funciona</span>
          <span class="lang-inline" data-lang="ru">Как это работает</span>
          <span class="lang-inline" data-lang="ja">仕組み</span>
          <span class="lang-inline" data-lang="ko">작동 방식</span>
          <span class="lang-inline" data-lang="zh">工作原理</span>
          <span class="lang-inline" data-lang="hi">कैसे काम करता है</span>
          <span class="lang-inline" data-lang="ar">كيف يعمل</span>
          <span class="lang-inline" data-lang="he">איך זה עובד</span>
        </div>
        <h2 class="section-title lang-block" data-lang="es">Flujo de defensa</h2>
        <h2 class="section-title lang-block" data-lang="en">Defense Flow</h2>
        <h2 class="section-title lang-block" data-lang="pt">Fluxo de defesa</h2>
        <h2 class="section-title lang-block" data-lang="fr">Flux de defense</h2>
        <h2 class="section-title lang-block" data-lang="de">Abwehr-Flow</h2>
        <h2 class="section-title lang-block" data-lang="nl">Verdedigingsflow</h2>
        <h2 class="section-title lang-block" data-lang="ca">Flux de defensa</h2>
        <h2 class="section-title lang-block" data-lang="ru">Поток защиты</h2>
        <h2 class="section-title lang-block" data-lang="ja">防御フロー</h2>
        <h2 class="section-title lang-block" data-lang="ko">방어 흐름</h2>
        <h2 class="section-title lang-block" data-lang="zh">防御流程</h2>
        <h2 class="section-title lang-block" data-lang="hi">डिफेंस फ्लो</h2>
        <h2 class="section-title lang-block" data-lang="ar">تدفق الحماية</h2>
        <h2 class="section-title lang-block" data-lang="he">זרימת הגנה</h2>
        <div class="timeline">
          <div class="step">
            <div class="step-number">1</div>
            <div>
              <div class="lang-block" data-lang="es">Extension y agente detectan el intento.</div>
              <div class="lang-block" data-lang="en">Extension and agent detect the attempt.</div>
              <div class="lang-block" data-lang="pt">Extensao e agente detectam a tentativa.</div>
              <div class="lang-block" data-lang="fr">Extension et agent detectent la tentative.</div>
              <div class="lang-block" data-lang="de">Erweiterung und Agent erkennen den Versuch.</div>
              <div class="lang-block" data-lang="nl">Extensie en agent detecteren de poging.</div>
              <div class="lang-block" data-lang="ca">L extensio i l agent detecten l intent.</div>
              <div class="lang-block" data-lang="ru">Расширение и агент обнаруживают попытку.</div>
              <div class="lang-block" data-lang="ja">拡張機能とエージェントが試行を検知。</div>
              <div class="lang-block" data-lang="ko">확장 프로그램과 에이전트가 시도를 감지합니다.</div>
              <div class="lang-block" data-lang="zh">扩展和代理检测到尝试。</div>
              <div class="lang-block" data-lang="hi">एक्सटेंशन और एजेंट प्रयास का पता लगाते हैं।</div>
              <div class="lang-block" data-lang="ar">يرصد الامتداد والوكيل المحاولة.</div>
              <div class="lang-block" data-lang="he">התוסף והסוכן מזהים את הניסיון.</div>
            </div>
          </div>
          <div class="step">
            <div class="step-number">2</div>
            <div>
              <div class="lang-block" data-lang="es">Se bloquea el flujo o se alerta al usuario.</div>
              <div class="lang-block" data-lang="en">Flow is blocked or the user is warned.</div>
              <div class="lang-block" data-lang="pt">O fluxo e bloqueado ou o usuario e alertado.</div>
              <div class="lang-block" data-lang="fr">Le flux est bloque ou l utilisateur est alerte.</div>
              <div class="lang-block" data-lang="de">Der Ablauf wird blockiert oder der Nutzer wird gewarnt.</div>
              <div class="lang-block" data-lang="nl">De flow wordt geblokkeerd of de gebruiker wordt gewaarschuwd.</div>
              <div class="lang-block" data-lang="ca">Es bloqueja el flux o s alerta l usuari.</div>
              <div class="lang-block" data-lang="ru">Поток блокируется или пользователь получает предупреждение.</div>
              <div class="lang-block" data-lang="ja">フローがブロックされるか、ユーザーに警告します。</div>
              <div class="lang-block" data-lang="ko">흐름이 차단되거나 사용자에게 경고합니다.</div>
              <div class="lang-block" data-lang="zh">流程被阻断或向用户发出警告。</div>
              <div class="lang-block" data-lang="hi">फ्लो ब्लॉक किया जाता है या यूज़र को चेतावनी दी जाती है।</div>
              <div class="lang-block" data-lang="ar">يتم حظر التدفق او تنبيه المستخدم.</div>
              <div class="lang-block" data-lang="he">הזרימה נחסמת או שהמשתמש מקבל אזהרה.</div>
            </div>
          </div>
          <div class="step">
            <div class="step-number">3</div>
            <div>
              <div class="lang-block" data-lang="es">El backend guarda evidencia y dashboards para analisis.</div>
              <div class="lang-block" data-lang="en">Backend stores evidence and dashboards for analysis.</div>
              <div class="lang-block" data-lang="pt">O backend guarda evidencias e dashboards para analise.</div>
              <div class="lang-block" data-lang="fr">Le backend conserve les preuves et dashboards pour analyse.</div>
              <div class="lang-block" data-lang="de">Das Backend speichert Beweise und Dashboards fuer die Analyse.</div>
              <div class="lang-block" data-lang="nl">De backend slaat bewijsmateriaal en dashboards op voor analyse.</div>
              <div class="lang-block" data-lang="ca">El backend desa evidencies i dashboards per a l analisi.</div>
              <div class="lang-block" data-lang="ru">Бэкенд сохраняет доказательства и дашборды для анализа.</div>
              <div class="lang-block" data-lang="ja">バックエンドが証跡とダッシュボードを保存して分析します。</div>
              <div class="lang-block" data-lang="ko">백엔드가 증거와 대시보드를 저장해 분석합니다.</div>
              <div class="lang-block" data-lang="zh">后端保存证据和仪表板以供分析。</div>
              <div class="lang-block" data-lang="hi">बैकएंड विश्लेषण के लिए सबूत और डैशबोर्ड सहेजता है।</div>
              <div class="lang-block" data-lang="ar">يحفظ الـbackend الادلة ولوحات التحكم للتحليل.</div>
              <div class="lang-block" data-lang="he">ה־backend שומר ראיות ודשבורדים לניתוח.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="card reveal delay-1">
        <div class="pill">
          <span class="lang-inline" data-lang="es">Componentes</span>
          <span class="lang-inline" data-lang="en">Components</span>
          <span class="lang-inline" data-lang="pt">Componentes</span>
          <span class="lang-inline" data-lang="fr">Composants</span>
          <span class="lang-inline" data-lang="de">Komponenten</span>
          <span class="lang-inline" data-lang="nl">Componenten</span>
          <span class="lang-inline" data-lang="ca">Components</span>
          <span class="lang-inline" data-lang="ru">Компоненты</span>
          <span class="lang-inline" data-lang="ja">コンポーネント</span>
          <span class="lang-inline" data-lang="ko">구성 요소</span>
          <span class="lang-inline" data-lang="zh">组件</span>
          <span class="lang-inline" data-lang="hi">कंपोनेंट्स</span>
          <span class="lang-inline" data-lang="ar">المكونات</span>
          <span class="lang-inline" data-lang="he">רכיבים</span>
        </div>
        <h2 class="section-title lang-block" data-lang="es">Piezas clave</h2>
        <h2 class="section-title lang-block" data-lang="en">Core Components</h2>
        <h2 class="section-title lang-block" data-lang="pt">Componentes</h2>
        <h2 class="section-title lang-block" data-lang="fr">Composants</h2>
        <h2 class="section-title lang-block" data-lang="de">Kernkomponenten</h2>
        <h2 class="section-title lang-block" data-lang="nl">Kerncomponenten</h2>
        <h2 class="section-title lang-block" data-lang="ca">Components clau</h2>
        <h2 class="section-title lang-block" data-lang="ru">Ключевые компоненты</h2>
        <h2 class="section-title lang-block" data-lang="ja">主要コンポーネント</h2>
        <h2 class="section-title lang-block" data-lang="ko">핵심 구성 요소</h2>
        <h2 class="section-title lang-block" data-lang="zh">核心组件</h2>
        <h2 class="section-title lang-block" data-lang="hi">मुख्य घटक</h2>
        <h2 class="section-title lang-block" data-lang="ar">المكونات الاساسية</h2>
        <h2 class="section-title lang-block" data-lang="he">רכיבים מרכזיים</h2>
        <div class="grid two">
          <div>
            <div style="font-family: 'Sora', sans-serif;">
              <span class="lang-inline" data-lang="es">Extension de navegador</span>
              <span class="lang-inline" data-lang="en">Browser Extension</span>
              <span class="lang-inline" data-lang="pt">Extensao de navegador</span>
              <span class="lang-inline" data-lang="fr">Extension navigateur</span>
              <span class="lang-inline" data-lang="de">Browser-Erweiterung</span>
              <span class="lang-inline" data-lang="nl">Browserextensie</span>
              <span class="lang-inline" data-lang="ca">Extensio del navegador</span>
              <span class="lang-inline" data-lang="ru">Расширение браузера</span>
              <span class="lang-inline" data-lang="ja">ブラウザ拡張</span>
              <span class="lang-inline" data-lang="ko">브라우저 확장</span>
              <span class="lang-inline" data-lang="zh">浏览器扩展</span>
              <span class="lang-inline" data-lang="hi">ब्राउज़र एक्सटेंशन</span>
              <span class="lang-inline" data-lang="ar">امتداد المتصفح</span>
              <span class="lang-inline" data-lang="he">תוסף דפדפן</span>
            </div>
            <p class="section-sub lang-block" data-lang="es" style="margin: 6px 0 12px;">Detector MV3 y bloqueo contextual.</p>
            <p class="section-sub lang-block" data-lang="en" style="margin: 6px 0 12px;">MV3 detector and contextual blocking.</p>
            <p class="section-sub lang-block" data-lang="pt" style="margin: 6px 0 12px;">Detector MV3 e bloqueio contextual.</p>
            <p class="section-sub lang-block" data-lang="fr" style="margin: 6px 0 12px;">Detecteur MV3 et blocage contextuel.</p>
            <p class="section-sub lang-block" data-lang="de" style="margin: 6px 0 12px;">MV3-Detektor und kontextuelles Blockieren.</p>
            <p class="section-sub lang-block" data-lang="nl" style="margin: 6px 0 12px;">MV3-detector en contextuele blokkering.</p>
            <p class="section-sub lang-block" data-lang="ca" style="margin: 6px 0 12px;">Detector MV3 i bloqueig contextual.</p>
            <p class="section-sub lang-block" data-lang="ru" style="margin: 6px 0 12px;">MV3-детектор и контекстная блокировка.</p>
            <p class="section-sub lang-block" data-lang="ja" style="margin: 6px 0 12px;">MV3検知と文脈ブロック.</p>
            <p class="section-sub lang-block" data-lang="ko" style="margin: 6px 0 12px;">MV3 탐지 및 문맥 차단.</p>
            <p class="section-sub lang-block" data-lang="zh" style="margin: 6px 0 12px;">MV3 检测与情境阻断。</p>
            <p class="section-sub lang-block" data-lang="hi" style="margin: 6px 0 12px;">MV3 डिटेक्शन और संदर्भ-आधारित ब्लॉकिंग।</p>
            <p class="section-sub lang-block" data-lang="ar" style="margin: 6px 0 12px;">كشف MV3 وحظر سياقي.</p>
            <p class="section-sub lang-block" data-lang="he" style="margin: 6px 0 12px;">זיהוי MV3 וחסימה הקשרית.</p>
          </div>
          <div>
            <div style="font-family: 'Sora', sans-serif;">
              <span class="lang-inline" data-lang="es">Agente Windows</span>
              <span class="lang-inline" data-lang="en">Windows Agent</span>
              <span class="lang-inline" data-lang="pt">Agente Windows</span>
              <span class="lang-inline" data-lang="fr">Agent Windows</span>
              <span class="lang-inline" data-lang="de">Windows-Agent</span>
              <span class="lang-inline" data-lang="nl">Windows-agent</span>
              <span class="lang-inline" data-lang="ca">Agent Windows</span>
              <span class="lang-inline" data-lang="ru">Агент Windows</span>
              <span class="lang-inline" data-lang="ja">Windows エージェント</span>
              <span class="lang-inline" data-lang="ko">Windows 에이전트</span>
              <span class="lang-inline" data-lang="zh">Windows 代理</span>
              <span class="lang-inline" data-lang="hi">Windows एजेंट</span>
              <span class="lang-inline" data-lang="ar">وكيل Windows</span>
              <span class="lang-inline" data-lang="he">סוכן Windows</span>
            </div>
            <p class="section-sub lang-block" data-lang="es" style="margin: 6px 0 12px;">Observa portapapeles y ejecucion.</p>
            <p class="section-sub lang-block" data-lang="en" style="margin: 6px 0 12px;">Watches clipboard and execution.</p>
            <p class="section-sub lang-block" data-lang="pt" style="margin: 6px 0 12px;">Observa clipboard e execucao.</p>
            <p class="section-sub lang-block" data-lang="fr" style="margin: 6px 0 12px;">Surveille presse-papiers et execution.</p>
            <p class="section-sub lang-block" data-lang="de" style="margin: 6px 0 12px;">Ueberwacht Zwischenablage und Ausfuehrung.</p>
            <p class="section-sub lang-block" data-lang="nl" style="margin: 6px 0 12px;">Houdt klembord en uitvoering in de gaten.</p>
            <p class="section-sub lang-block" data-lang="ca" style="margin: 6px 0 12px;">Observa el porta-retalls i l execucio.</p>
            <p class="section-sub lang-block" data-lang="ru" style="margin: 6px 0 12px;">Отслеживает буфер обмена и выполнение.</p>
            <p class="section-sub lang-block" data-lang="ja" style="margin: 6px 0 12px;">クリップボードと実行を監視。</p>
            <p class="section-sub lang-block" data-lang="ko" style="margin: 6px 0 12px;">클립보드와 실행을 관찰합니다.</p>
            <p class="section-sub lang-block" data-lang="zh" style="margin: 6px 0 12px;">监视剪贴板和执行。</p>
            <p class="section-sub lang-block" data-lang="hi" style="margin: 6px 0 12px;">क्लिपबोर्ड और निष्पादन पर नज़र रखता है।</p>
            <p class="section-sub lang-block" data-lang="ar" style="margin: 6px 0 12px;">يراقب الحافظة والتنفيذ.</p>
            <p class="section-sub lang-block" data-lang="he" style="margin: 6px 0 12px;">מנטר לוח גזירים וביצוע.</p>
          </div>
          <div>
            <div style="font-family: 'Sora', sans-serif;">
              <span class="lang-inline" data-lang="es">PHP + SQLite</span>
              <span class="lang-inline" data-lang="en">PHP + SQLite</span>
              <span class="lang-inline" data-lang="pt">PHP + SQLite</span>
              <span class="lang-inline" data-lang="fr">PHP + SQLite</span>
              <span class="lang-inline" data-lang="de">PHP + SQLite</span>
              <span class="lang-inline" data-lang="nl">PHP + SQLite</span>
              <span class="lang-inline" data-lang="ca">PHP + SQLite</span>
              <span class="lang-inline" data-lang="ru">PHP + SQLite</span>
              <span class="lang-inline" data-lang="ja">PHP + SQLite</span>
              <span class="lang-inline" data-lang="ko">PHP + SQLite</span>
              <span class="lang-inline" data-lang="zh">PHP + SQLite</span>
              <span class="lang-inline" data-lang="hi">PHP + SQLite</span>
              <span class="lang-inline" data-lang="ar">PHP + SQLite</span>
              <span class="lang-inline" data-lang="he">PHP + SQLite</span>
            </div>
            <p class="section-sub lang-block" data-lang="es" style="margin: 6px 0 12px;">Backend para reportes y auditoria.</p>
            <p class="section-sub lang-block" data-lang="en" style="margin: 6px 0 12px;">Backend for reports and audit trails.</p>
            <p class="section-sub lang-block" data-lang="pt" style="margin: 6px 0 12px;">Backend para relatorios e auditoria.</p>
            <p class="section-sub lang-block" data-lang="fr" style="margin: 6px 0 12px;">Backend pour rapports et audit.</p>
            <p class="section-sub lang-block" data-lang="de" style="margin: 6px 0 12px;">Backend fuer Reports und Audits.</p>
            <p class="section-sub lang-block" data-lang="nl" style="margin: 6px 0 12px;">Backend voor rapporten en audits.</p>
            <p class="section-sub lang-block" data-lang="ca" style="margin: 6px 0 12px;">Backend per a informes i auditoria.</p>
            <p class="section-sub lang-block" data-lang="ru" style="margin: 6px 0 12px;">Backend для отчетов и аудита.</p>
            <p class="section-sub lang-block" data-lang="ja" style="margin: 6px 0 12px;">レポートと監査のためのバックエンド。</p>
            <p class="section-sub lang-block" data-lang="ko" style="margin: 6px 0 12px;">보고서 및 감사용 백엔드.</p>
            <p class="section-sub lang-block" data-lang="zh" style="margin: 6px 0 12px;">用于报告和审计的后端。</p>
            <p class="section-sub lang-block" data-lang="hi" style="margin: 6px 0 12px;">रिपोर्ट और ऑडिट के लिए बैकएंड।</p>
            <p class="section-sub lang-block" data-lang="ar" style="margin: 6px 0 12px;">Backend للتقارير والتدقيق.</p>
            <p class="section-sub lang-block" data-lang="he" style="margin: 6px 0 12px;">Backend לדוחות ולביקורת.</p>
          </div>
          <div>
            <div style="font-family: 'Sora', sans-serif;">
              <span class="lang-inline" data-lang="es">Dashboard de operaciones</span>
              <span class="lang-inline" data-lang="en">Ops Dashboard</span>
              <span class="lang-inline" data-lang="pt">Dashboard de operacoes</span>
              <span class="lang-inline" data-lang="fr">Dashboard ops</span>
              <span class="lang-inline" data-lang="de">Ops-Dashboard</span>
              <span class="lang-inline" data-lang="nl">Ops-dashboard</span>
              <span class="lang-inline" data-lang="ca">Dashboard d operacions</span>
              <span class="lang-inline" data-lang="ru">Ops-дэшборд</span>
              <span class="lang-inline" data-lang="ja">Ops ダッシュボード</span>
              <span class="lang-inline" data-lang="ko">운영 대시보드</span>
              <span class="lang-inline" data-lang="zh">运维仪表板</span>
              <span class="lang-inline" data-lang="hi">ऑप्स डैशबोर्ड</span>
              <span class="lang-inline" data-lang="ar">لوحة عمليات</span>
              <span class="lang-inline" data-lang="he">דשבורד תפעול</span>
            </div>
            <p class="section-sub lang-block" data-lang="es" style="margin: 6px 0 12px;">Triage, roles y analitica.</p>
            <p class="section-sub lang-block" data-lang="en" style="margin: 6px 0 12px;">Triage, roles, and analytics.</p>
            <p class="section-sub lang-block" data-lang="pt" style="margin: 6px 0 12px;">Triage, roles e analitica.</p>
            <p class="section-sub lang-block" data-lang="fr" style="margin: 6px 0 12px;">Triage, roles et analytics.</p>
            <p class="section-sub lang-block" data-lang="de" style="margin: 6px 0 12px;">Triage, Rollen und Analytics.</p>
            <p class="section-sub lang-block" data-lang="nl" style="margin: 6px 0 12px;">Triage, rollen en analytics.</p>
            <p class="section-sub lang-block" data-lang="ca" style="margin: 6px 0 12px;">Triage, rols i analitica.</p>
            <p class="section-sub lang-block" data-lang="ru" style="margin: 6px 0 12px;">Триаж, роли и аналитика.</p>
            <p class="section-sub lang-block" data-lang="ja" style="margin: 6px 0 12px;">トリアージ、役割、分析。</p>
            <p class="section-sub lang-block" data-lang="ko" style="margin: 6px 0 12px;">트리아지, 역할, 분석.</p>
            <p class="section-sub lang-block" data-lang="zh" style="margin: 6px 0 12px;">分诊、角色与分析。</p>
            <p class="section-sub lang-block" data-lang="hi" style="margin: 6px 0 12px;">ट्रायएज, रोल्स और एनालिटिक्स।</p>
            <p class="section-sub lang-block" data-lang="ar" style="margin: 6px 0 12px;">فرز، ادوار وتحليلات.</p>
            <p class="section-sub lang-block" data-lang="he" style="margin: 6px 0 12px;">טריאז', תפקידים ואנליטיקה.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="grid three">
      <div class="card reveal">
        <h3 class="lang-block" data-lang="es">Para blue teams</h3>
        <h3 class="lang-block" data-lang="en">For Blue Teams</h3>
        <h3 class="lang-block" data-lang="pt">Para blue teams</h3>
        <h3 class="lang-block" data-lang="fr">Pour les blue teams</h3>
        <h3 class="lang-block" data-lang="de">Fuer Blue Teams</h3>
        <h3 class="lang-block" data-lang="nl">Voor blue teams</h3>
        <h3 class="lang-block" data-lang="ca">Per a blue teams</h3>
        <h3 class="lang-block" data-lang="ru">Для blue teams</h3>
        <h3 class="lang-block" data-lang="ja">Blue Team向け</h3>
        <h3 class="lang-block" data-lang="ko">블루팀을 위해</h3>
        <h3 class="lang-block" data-lang="zh">面向蓝队</h3>
        <h3 class="lang-block" data-lang="hi">ब्लू टीमों के लिए</h3>
        <h3 class="lang-block" data-lang="ar">لفرق الدفاع</h3>
        <h3 class="lang-block" data-lang="he">לצוותי כחול</h3>
        <p class="lang-block" data-lang="es">Entrena, analiza y valida defensas contra flujos reales de ClickFix.</p>
        <p class="lang-block" data-lang="en">Train, analyze, and validate defenses against real ClickFix flows.</p>
        <p class="lang-block" data-lang="pt">Treine, analise e valide defesas contra fluxos ClickFix reais.</p>
        <p class="lang-block" data-lang="fr">Entrainez, analysez et validez les defenses contre des flux ClickFix reels.</p>
        <p class="lang-block" data-lang="de">Trainieren, analysieren und validieren Sie Abwehrmassnahmen gegen reale ClickFix-Flows.</p>
        <p class="lang-block" data-lang="nl">Train, analyseer en valideer verdedigingen tegen echte ClickFix-flows.</p>
        <p class="lang-block" data-lang="ca">Entrena, analitza i valida defenses contra fluxos reals de ClickFix.</p>
        <p class="lang-block" data-lang="ru">Тренируйте, анализируйте и валидируйте защиты против реальных потоков ClickFix.</p>
        <p class="lang-block" data-lang="ja">実際のClickFixフローに対する防御を訓練・分析・検証。</p>
        <p class="lang-block" data-lang="ko">실제 ClickFix 흐름에 대한 방어를 훈련, 분석, 검증.</p>
        <p class="lang-block" data-lang="zh">训练、分析并验证对真实 ClickFix 流程的防御。</p>
        <p class="lang-block" data-lang="hi">वास्तविक ClickFix फ्लो के खिलाफ सुरक्षा को ट्रेन, विश्लेषण और वैलिडेट करें।</p>
        <p class="lang-block" data-lang="ar">درّب وحلل وحقق الدفاعات ضد تدفقات ClickFix الحقيقية.</p>
        <p class="lang-block" data-lang="he">אמן, נתח ואמת הגנות מול זרימות ClickFix אמיתיות.</p>
      </div>
      <div class="card reveal delay-1">
        <h3 class="lang-block" data-lang="es">Para SOC y IR</h3>
        <h3 class="lang-block" data-lang="en">For SOC and IR</h3>
        <h3 class="lang-block" data-lang="pt">Para SOC e IR</h3>
        <h3 class="lang-block" data-lang="fr">Pour SOC et IR</h3>
        <h3 class="lang-block" data-lang="de">Fuer SOC und IR</h3>
        <h3 class="lang-block" data-lang="nl">Voor SOC en IR</h3>
        <h3 class="lang-block" data-lang="ca">Per a SOC i IR</h3>
        <h3 class="lang-block" data-lang="ru">Для SOC и IR</h3>
        <h3 class="lang-block" data-lang="ja">SOCとIR向け</h3>
        <h3 class="lang-block" data-lang="ko">SOC 및 IR용</h3>
        <h3 class="lang-block" data-lang="zh">面向 SOC 和 IR</h3>
        <h3 class="lang-block" data-lang="hi">SOC और IR के लिए</h3>
        <h3 class="lang-block" data-lang="ar">لـ SOC و IR</h3>
        <h3 class="lang-block" data-lang="he">ל‑SOC ו‑IR</h3>
        <p class="lang-block" data-lang="es">Alertas estructuradas y evidencia lista para investigacion.</p>
        <p class="lang-block" data-lang="en">Structured alerts and evidence ready for investigation.</p>
        <p class="lang-block" data-lang="pt">Alertas estruturados e evidencia pronta para investigacao.</p>
        <p class="lang-block" data-lang="fr">Alertes structurees et preuves pretes pour investigation.</p>
        <p class="lang-block" data-lang="de">Strukturierte Alerts und Beweise, bereit fuer Untersuchungen.</p>
        <p class="lang-block" data-lang="nl">Gestructureerde alerts en bewijs dat klaar is voor onderzoek.</p>
        <p class="lang-block" data-lang="ca">Alertes estructurades i evidencia llesta per a investigacio.</p>
        <p class="lang-block" data-lang="ru">Структурированные оповещения и доказательства, готовые для расследования.</p>
        <p class="lang-block" data-lang="ja">調査に使える構造化アラートと証拠。</p>
        <p class="lang-block" data-lang="ko">조사를 위한 구조화된 경고와 증거.</p>
        <p class="lang-block" data-lang="zh">结构化警报和可用于调查的证据。</p>
        <p class="lang-block" data-lang="hi">जांच के लिए तैयार संरचित अलर्ट और प्रमाण.</p>
        <p class="lang-block" data-lang="ar">تنبيهات منظمة وادلة جاهزة للتحقيق.</p>
        <p class="lang-block" data-lang="he">התראות מובנות וראיות מוכנות לחקירה.</p>
      </div>
      <div class="card reveal delay-2">
        <h3 class="lang-block" data-lang="es">Para demos seguras</h3>
        <h3 class="lang-block" data-lang="en">For Safe Demos</h3>
        <h3 class="lang-block" data-lang="pt">Para demos seguras</h3>
        <h3 class="lang-block" data-lang="fr">Pour demos securisees</h3>
        <h3 class="lang-block" data-lang="de">Fuer sichere Demos</h3>
        <h3 class="lang-block" data-lang="nl">Voor veilige demo's</h3>
        <h3 class="lang-block" data-lang="ca">Per a demos segures</h3>
        <h3 class="lang-block" data-lang="ru">Для безопасных демо</h3>
        <h3 class="lang-block" data-lang="ja">安全なデモ向け</h3>
        <h3 class="lang-block" data-lang="ko">안전한 데모용</h3>
        <h3 class="lang-block" data-lang="zh">用于安全演示</h3>
        <h3 class="lang-block" data-lang="hi">सुरक्षित डेमो के लिए</h3>
        <h3 class="lang-block" data-lang="ar">لعروض تجريبية امنة</h3>
        <h3 class="lang-block" data-lang="he">להדגמות בטוחות</h3>
        <p class="lang-block" data-lang="es">Reproduce ataques sin riesgo, con controles y telemetria.</p>
        <p class="lang-block" data-lang="en">Reproduce attacks safely with control and telemetry.</p>
        <p class="lang-block" data-lang="pt">Reproduza ataques com seguranca, controle e telemetria.</p>
        <p class="lang-block" data-lang="fr">Reproduisez des attaques en securite avec controle et telemetrie.</p>
        <p class="lang-block" data-lang="de">Reproduzieren Sie Angriffe risikofrei mit Kontrollen und Telemetrie.</p>
        <p class="lang-block" data-lang="nl">Boots aanvallen veilig na met controle en telemetrie.</p>
        <p class="lang-block" data-lang="ca">Reprodueix atacs sense risc, amb controls i telemetria.</p>
        <p class="lang-block" data-lang="ru">Воспроизводите атаки безопасно с контролем и телеметрией.</p>
        <p class="lang-block" data-lang="ja">制御とテレメトリで安全に攻撃を再現。</p>
        <p class="lang-block" data-lang="ko">통제와 텔레메트리로 안전하게 공격을 재현합니다.</p>
        <p class="lang-block" data-lang="zh">在控制和遥测下安全复现攻击。</p>
        <p class="lang-block" data-lang="hi">नियंत्रण और टेलीमेट्री के साथ सुरक्षित रूप से हमले पुन: उत्पन्न करें।</p>
        <p class="lang-block" data-lang="ar">اعادة تمثيل الهجمات بأمان مع ضوابط وتليمترى.</p>
        <p class="lang-block" data-lang="he">שחזר תקיפות בבטחה עם בקרה וטלמטריה.</p>
      </div>
    </section>

    <section class="card reveal">
      <div class="pill">
        <span class="lang-inline" data-lang="es">Nota de defensa</span>
        <span class="lang-inline" data-lang="en">Defense Note</span>
        <span class="lang-inline" data-lang="pt">Nota de defesa</span>
        <span class="lang-inline" data-lang="fr">Note de defense</span>
        <span class="lang-inline" data-lang="de">Verteidigungshinweis</span>
        <span class="lang-inline" data-lang="nl">Verdedigingsnotitie</span>
        <span class="lang-inline" data-lang="ca">Nota de defensa</span>
        <span class="lang-inline" data-lang="ru">Примечание по защите</span>
        <span class="lang-inline" data-lang="ja">防御メモ</span>
        <span class="lang-inline" data-lang="ko">방어 노트</span>
        <span class="lang-inline" data-lang="zh">防御说明</span>
        <span class="lang-inline" data-lang="hi">डिफेंस नोट</span>
        <span class="lang-inline" data-lang="ar">ملاحظة دفاع</span>
        <span class="lang-inline" data-lang="he">הערת הגנה</span>
      </div>
      <h2 class="section-title lang-block" data-lang="es">Uso responsable</h2>
      <h2 class="section-title lang-block" data-lang="en">Responsible Use</h2>
      <h2 class="section-title lang-block" data-lang="pt">Uso responsavel</h2>
      <h2 class="section-title lang-block" data-lang="fr">Usage responsable</h2>
      <h2 class="section-title lang-block" data-lang="de">Verantwortungsvoller Einsatz</h2>
      <h2 class="section-title lang-block" data-lang="nl">Verantwoord gebruik</h2>
      <h2 class="section-title lang-block" data-lang="ca">Us responsable</h2>
      <h2 class="section-title lang-block" data-lang="ru">Ответственное использование</h2>
      <h2 class="section-title lang-block" data-lang="ja">責任ある利用</h2>
      <h2 class="section-title lang-block" data-lang="ko">책임 있는 사용</h2>
      <h2 class="section-title lang-block" data-lang="zh">负责任的使用</h2>
      <h2 class="section-title lang-block" data-lang="hi">जिम्मेदार उपयोग</h2>
      <h2 class="section-title lang-block" data-lang="ar">استخدام مسؤول</h2>
      <h2 class="section-title lang-block" data-lang="he">שימוש אחראי</h2>
      <p class="section-sub lang-block" data-lang="es">Este proyecto es educativo y de referencia. Ajusta las politicas, reglas y flujo de bloqueo a tu entorno antes de desplegar.</p>
      <p class="section-sub lang-block" data-lang="en">This project is educational and reference-grade. Adapt policies, rules, and blocking flow before deploying in production.</p>
      <p class="section-sub lang-block" data-lang="pt">Este projeto e educacional e de referencia. Ajuste politicas, regras e fluxo de bloqueio antes de implantar.</p>
      <p class="section-sub lang-block" data-lang="fr">Ce projet est educatif et de reference. Ajustez les politiques, regles et flux de blocage avant de deployer.</p>
      <p class="section-sub lang-block" data-lang="de">Dieses Projekt ist lehrreich und als Referenz gedacht. Passen Sie Richtlinien, Regeln und den Blockier-Flow vor dem Einsatz an.</p>
      <p class="section-sub lang-block" data-lang="nl">Dit project is educatief en als referentie bedoeld. Pas beleid, regels en de blokkeerflow aan voordat je uitrolt.</p>
      <p class="section-sub lang-block" data-lang="ca">Aquest projecte es educatiu i de referencia. Ajusta politiques, regles i el flux de bloqueig abans de desplegar.</p>
      <p class="section-sub lang-block" data-lang="ru">Этот проект учебный и справочный. Настройте политики, правила и поток блокировок перед развертыванием.</p>
      <p class="section-sub lang-block" data-lang="ja">このプロジェクトは教育目的のリファレンスです。導入前にポリシー、ルール、ブロックフローを調整してください。</p>
      <p class="section-sub lang-block" data-lang="ko">이 프로젝트는 교육용 레퍼런스입니다. 배포 전에 정책, 규칙, 차단 흐름을 조정하세요.</p>
      <p class="section-sub lang-block" data-lang="zh">该项目是教育用途的参考实现。部署前请调整策略、规则和阻断流程。</p>
      <p class="section-sub lang-block" data-lang="hi">यह प्रोजेक्ट शैक्षिक और संदर्भ-ग्रेड है। डिप्लॉय से पहले नीतियाँ, नियम और ब्लॉकिंग फ्लो अनुकूलित करें।</p>
      <p class="section-sub lang-block" data-lang="ar">هذا المشروع تعليمي ومرجعي. عدل السياسات والقواعد وتدفق الحظر قبل النشر.</p>
      <p class="section-sub lang-block" data-lang="he">זהו פרויקט חינוכי וייחוס. התאימו מדיניות, כללים וזרימת חסימה לפני פריסה.</p>
    </section>

    <footer class="footer">
      <div>
        <div class="lang-block" data-lang="es">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="en">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="pt">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="fr">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="de">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="nl">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="ca">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="ru">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="ja">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="ko">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="zh">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="hi">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="ar">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="he">ClickFix Mitigator | Defense-first anti ClickFix</div>
      </div>
      <div>
        <a href="ClickFix/PrivacyPolicy.html">
          <span class="lang-inline" data-lang="es">Politica de privacidad</span>
          <span class="lang-inline" data-lang="en">Privacy Policy</span>
          <span class="lang-inline" data-lang="pt">Politica de privacidade</span>
          <span class="lang-inline" data-lang="fr">Politique de confidentialite</span>
          <span class="lang-inline" data-lang="de">Datenschutzerklaerung</span>
          <span class="lang-inline" data-lang="nl">Privacybeleid</span>
          <span class="lang-inline" data-lang="ca">Politica de privacitat</span>
          <span class="lang-inline" data-lang="ru">Политика конфиденциальности</span>
          <span class="lang-inline" data-lang="ja">プライバシーポリシー</span>
          <span class="lang-inline" data-lang="ko">개인정보 처리방침</span>
          <span class="lang-inline" data-lang="zh">隐私政策</span>
          <span class="lang-inline" data-lang="hi">गोपनीयता नीति</span>
          <span class="lang-inline" data-lang="ar">سياسة الخصوصية</span>
          <span class="lang-inline" data-lang="he">מדיניות פרטיות</span>
        </a>
      </div>
    </footer>
  </div>

  <script>
    (function() {
      var supported = ['ar', 'ca', 'de', 'en', 'es', 'fr', 'he', 'hi', 'ja', 'ko', 'nl', 'pt', 'ru', 'zh'];
      var saved = localStorage.getItem('cfm_lang');
      var browser = (navigator.language || 'en').slice(0, 2).toLowerCase();
      var initial = saved && supported.includes(saved) ? saved : (supported.includes(browser) ? browser : 'en');

      function setLang(lang) {
        if (!supported.includes(lang)) return;
        document.body.classList.remove(
          'lang-ar',
          'lang-ca',
          'lang-de',
          'lang-en',
          'lang-es',
          'lang-fr',
          'lang-he',
          'lang-hi',
          'lang-ja',
          'lang-ko',
          'lang-nl',
          'lang-pt',
          'lang-ru',
          'lang-zh'
        );
        document.body.classList.add('lang-' + lang);
        document.documentElement.lang = lang;
        document.documentElement.dir = (lang === 'ar' || lang === 'he') ? 'rtl' : 'ltr';
        localStorage.setItem('cfm_lang', lang);
      }

      var select = document.getElementById('lang-select');
      if (select) {
        select.value = initial;
        select.addEventListener('change', function() {
          setLang(select.value);
        });
      }

      setLang(initial);
    })();
  </script>
</body>
</html>
