<?php
/**
 * ClickFix Demo Gallery - self-hosted index.php
 * Drop this file into /ClickFix/demo/ and it will auto-list demos with preview + source viewer.
 */

declare(strict_types=1);

$baseDir = __DIR__;
$self = basename(__FILE__);

$allowedExt = ['php', 'html'];
$denyNames = [$self]; // add any extra files you want hidden here

// --- helpers ---
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function formatBytes(int $bytes): string {
  $units = ['B','KB','MB','GB','TB'];
  $i = 0;
  $n = (float)$bytes;
  while ($n >= 1024 && $i < count($units)-1) { $n /= 1024; $i++; }
  return ($i === 0 ? (string)$bytes : number_format($n, 1)) . ' ' . $units[$i];
}

function safeResolve(string $baseDir, string $file, array $allowedExt): ?string {
  $file = str_replace("\0", '', $file);
  $file = ltrim($file, '/\\');
  $file = basename($file);

  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) return null;

  $real = realpath($baseDir . DIRECTORY_SEPARATOR . $file);
  if ($real === false) return null;

  $baseReal = realpath($baseDir);
  if ($baseReal === false) return null;

  // must be inside base dir
  if (strpos($real, $baseReal . DIRECTORY_SEPARATOR) !== 0) return null;

  return $real;
}

// --- SOURCE endpoint ---
if (isset($_GET['source'])) {
  $file = (string)$_GET['source'];
  $resolved = safeResolve($baseDir, $file, $allowedExt);

  if ($resolved === null || !is_file($resolved)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found.";
    exit;
  }

  header('Content-Type: text/plain; charset=utf-8');
  // show raw source (client will escape it in <pre>), but safest is to just output plain text
  echo file_get_contents($resolved);
  exit;
}

// --- build file list ---
$items = [];
$dir = new DirectoryIterator($baseDir);

foreach ($dir as $f) {
  if ($f->isDot() || !$f->isFile()) continue;
  $name = $f->getFilename();

  if (in_array($name, $denyNames, true)) continue;

  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) continue;

  $items[] = [
    'name' => $name,
    'ext'  => $ext,
    'size' => $f->getSize(),
    'mtime'=> $f->getMTime(),
  ];
}

// sort: newest first
usort($items, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

// Optional: friendly titles/descriptions (edit to taste)
$meta = [
  'attacker-obfuscated-command.php' => [
    'title' => 'Obfuscated command',
    'desc'  => 'Simulates a ClickFix lure using obfuscation / encoded-style prompts.'
  ],
  'attacker-sample.php' => [
    'title' => 'Attacker sample #1',
    'desc'  => 'Classic “copy/paste to fix” flow.'
  ],
  'attacker-sample2.php' => [
    'title' => 'Attacker sample #2',
    'desc'  => 'Variant campaign / wording changes.'
  ],
  'attacker-spaced-win.php' => [
    'title' => 'Spaced Win+R hint',
    'desc'  => 'Uses spacing tricks to evade naive detectors.'
  ],
  'attacker-winlogo.php' => [
    'title' => 'Win logo lure',
    'desc'  => 'Uses Windows branding cues to increase trust.'
  ],
  'demo-cloudflare.html' => [
    'title' => 'Cloudflare-style human check (HTML)',
    'desc'  => 'Fake “verify you’re human” flow using Win+R / Ctrl+V pattern.'
  ],
  'demo-cloudflare.php' => [
    'title' => 'Cloudflare-style human check (PHP)',
    'desc'  => 'Server-side version of the fake verification flow.'
  ],
  'demo-file-explorer.php' => [
    'title' => 'File Explorer lure',
    'desc'  => 'Tries to push paste/run behavior from a file-explorer themed UI.'
  ],
  'demo-windows-update.php' => [
    'title' => 'Windows Update lure',
    'desc'  => '“Update required” style pressure prompt.'
  ],
];

function badgeClass(string $ext): string {
  return $ext === 'php' ? 'badge-php' : 'badge-html';
}

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$baseUrl = $basePath === '' ? '.' : $basePath;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ClickFix Mitigator · Demo Gallery</title>
  <style>
    :root{
      --bg:#0b1020; --panel:#111a33; --panel2:#0f1730;
      --text:#e8ecff; --muted:#a9b3d6; --line:rgba(255,255,255,.10);
      --accent:#f7c948; --danger:#ff5a6a; --ok:#5ef0a1;
      --shadow: 0 20px 60px rgba(0,0,0,.35);
      --radius:18px;
    }
    *{box-sizing:border-box}
    body{
      margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Helvetica Neue";
      background: radial-gradient(1200px 700px at 20% -10%, rgba(247,201,72,.20), transparent 55%),
                  radial-gradient(900px 600px at 90% 0%, rgba(94,240,161,.10), transparent 55%),
                  var(--bg);
      color:var(--text);
    }
    header{
      padding:34px 20px 14px;
      max-width:1200px; margin:0 auto;
    }
    .top{
      display:flex; gap:16px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap;
    }
    h1{
      margin:0; font-size:28px; letter-spacing:.2px;
    }
    .subtitle{
      margin-top:8px; color:var(--muted); line-height:1.4;
      max-width:760px;
    }
    .pillbar{
      display:flex; gap:10px; flex-wrap:wrap; align-items:center;
      margin-top:16px;
    }
    .pill{
      border:1px solid var(--line);
      background: rgba(255,255,255,.04);
      padding:8px 12px; border-radius:999px;
      color:var(--muted); font-size:13px;
    }
    .pill strong{ color:var(--text); font-weight:700; }
    main{
      max-width:1200px; margin:0 auto; padding:16px 20px 50px;
    }
    .grid{
      display:grid;
      grid-template-columns: repeat( auto-fit, minmax(280px, 1fr) );
      gap:14px;
      margin-top:14px;
    }
    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.03));
      border:1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow:hidden;
      position:relative;
      min-height: 190px;
    }
    .card::before{
      content:"";
      position:absolute; inset:0;
      background: radial-gradient(500px 220px at 30% 0%, rgba(247,201,72,.18), transparent 60%);
      pointer-events:none;
      opacity:.7;
    }
    .card-inner{
      position:relative;
      padding:16px;
      display:flex;
      flex-direction:column;
      gap:10px;
      height:100%;
    }
    .row{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
    }
    .badge{
      font-size:12px; letter-spacing:.4px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      background: rgba(255,255,255,.04);
      color:var(--muted);
      text-transform:uppercase;
    }
    .badge-php{ border-color: rgba(94,240,161,.35); color: #b9ffd9; }
    .badge-html{ border-color: rgba(247,201,72,.35); color: #ffe6a3; }
    .title{
      font-size:16px; font-weight:800; margin:0;
    }
    .filename{
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono";
      color: rgba(232,236,255,.85);
      font-size:12px;
      opacity:.9;
      margin-top:2px;
    }
    .desc{
      color:var(--muted);
      font-size:13px;
      line-height:1.45;
      min-height: 36px;
    }
    .meta{
      display:flex; gap:10px; flex-wrap:wrap;
      color: rgba(232,236,255,.75);
      font-size:12px;
      margin-top:auto;
    }
    .meta span{
      border:1px solid var(--line);
      background: rgba(255,255,255,.03);
      padding:6px 10px;
      border-radius: 999px;
    }
    .actions{
      display:flex; gap:10px; flex-wrap:wrap;
      margin-top:10px;
    }
    a.btn, button.btn{
      appearance:none;
      border:none;
      cursor:pointer;
      text-decoration:none;
      color:var(--text);
      padding:10px 12px;
      border-radius: 12px;
      font-weight:800;
      font-size:13px;
      letter-spacing:.2px;
      display:inline-flex; align-items:center; gap:8px;
      background: rgba(255,255,255,.06);
      border:1px solid var(--line);
    }
    a.btn:hover, button.btn:hover{ transform: translateY(-1px); }
    .btn.primary{
      background: linear-gradient(180deg, rgba(247,201,72,.95), rgba(247,201,72,.80));
      border-color: rgba(247,201,72,.35);
      color:#161616;
    }
    .btn.ghost{
      background: rgba(255,255,255,.03);
    }
    .btn.danger{
      background: rgba(255,90,106,.10);
      border-color: rgba(255,90,106,.25);
      color: #ffd0d5;
    }

    /* modal */
    .modal{
      position:fixed; inset:0;
      background: rgba(0,0,0,.55);
      display:none;
      align-items:center;
      justify-content:center;
      padding:16px;
      z-index:9999;
    }
    .modal.open{ display:flex; }
    .modal-box{
      width:min(1100px, 100%);
      height:min(80vh, 760px);
      background: linear-gradient(180deg, rgba(17,26,51,.96), rgba(15,23,48,.96));
      border:1px solid var(--line);
      border-radius: 18px;
      box-shadow: var(--shadow);
      overflow:hidden;
      display:flex;
      flex-direction:column;
    }
    .modal-head{
      display:flex; align-items:center; justify-content:space-between;
      padding:12px 14px;
      border-bottom:1px solid var(--line);
      background: rgba(255,255,255,.03);
      gap:10px;
    }
    .modal-title{
      font-weight:900;
      font-size:14px;
      color: rgba(232,236,255,.95);
      display:flex; gap:10px; align-items:center; flex-wrap:wrap;
    }
    .modal-title code{
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono";
      font-size:12px;
      color: rgba(232,236,255,.85);
      background: rgba(0,0,0,.25);
      padding:4px 8px;
      border-radius: 10px;
      border:1px solid var(--line);
    }
    .modal-body{
      flex:1;
      display:grid;
      grid-template-columns: 1.2fr .8fr;
      min-height:0;
    }
    .pane{
      min-height:0;
      border-right:1px solid var(--line);
    }
    .pane:last-child{ border-right:none; }
    iframe{
      width:100%;
      height:100%;
      border:0;
      background:#fff;
    }
    pre{
      margin:0;
      height:100%;
      overflow:auto;
      padding:14px;
      background: rgba(0,0,0,.25);
      color: rgba(232,236,255,.92);
      font-size:12px;
      line-height:1.5;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono";
      white-space: pre;
    }
    .modal-foot{
      display:flex; gap:10px; justify-content:flex-end;
      padding:12px 14px;
      border-top:1px solid var(--line);
      background: rgba(255,255,255,.02);
    }
    .hint{
      color: var(--muted);
      font-size:12px;
      margin-top:10px;
    }
    @media (max-width: 860px){
      .modal-body{ grid-template-columns: 1fr; }
      .pane{ border-right:none; border-bottom:1px solid var(--line); }
      .pane:last-child{ border-bottom:none; }
    }
  </style>
</head>
<body>
<header>
  <div class="top">
    <div>
      <h1>ClickFix Mitigator · Demo Gallery</h1>
      <div class="subtitle">
        Self-hosted demos that simulate ClickFix-style lures (Win+R / copy-paste “fixes”) so defenders can test detections safely.
      </div>
      <div class="pillbar">
        <div class="pill"><strong><?= count($items) ?></strong> demos</div>
        <div class="pill">Preview + View source</div>
        <div class="pill">No external assets</div>
      </div>
      <div class="hint">Tip: open a demo in a fresh profile if you’re testing blocking behavior.</div>
    </div>
    <div class="pillbar">
      <div class="pill">Directory: <strong><?= h($_SERVER['REQUEST_URI'] ?? '/ClickFix/demo/') ?></strong></div>
    </div>
  </div>
</header>

<main>
  <div class="grid">
    <?php foreach ($items as $it):
      $name = $it['name'];
      $ext = $it['ext'];
      $title = $meta[$name]['title'] ?? preg_replace('/\.(php|html)$/i', '', $name);
      $desc  = $meta[$name]['desc']  ?? 'Demo page for ClickFix pattern simulation.';
      $href  = $baseUrl . '/' . rawurlencode($name);
      $mtime = date('Y-m-d H:i', (int)$it['mtime']);
      $size  = formatBytes((int)$it['size']);
    ?>
      <div class="card">
        <div class="card-inner">
          <div class="row">
            <span class="badge <?= h(badgeClass($ext)) ?>"><?= h($ext) ?></span>
            <span class="badge" title="Last modified"><?= h($mtime) ?></span>
          </div>

          <div>
            <div class="title"><?= h($title) ?></div>
            <div class="filename"><?= h($name) ?></div>
          </div>

          <div class="desc"><?= h($desc) ?></div>

          <div class="meta">
            <span title="File size"><?= h($size) ?></span>
            <span title="Open in new tab">Self-hosted</span>
          </div>

          <div class="actions">
            <a class="btn primary" href="<?= h($href) ?>" target="_blank" rel="noopener">Open</a>
            <button class="btn ghost" data-preview="<?= h($href) ?>" data-name="<?= h($name) ?>">Preview</button>
            <button class="btn danger" data-source="<?= h($name) ?>">View source</button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<!-- Modal -->
<div class="modal" id="modal" aria-hidden="true">
  <div class="modal-box" role="dialog" aria-modal="true" aria-label="Preview">
    <div class="modal-head">
      <div class="modal-title">
        Preview <code id="modalFile">—</code>
      </div>
      <button class="btn ghost" id="closeBtn">Close</button>
    </div>

    <div class="modal-body">
      <div class="pane">
        <iframe id="previewFrame" src="about:blank" referrerpolicy="no-referrer"></iframe>
      </div>
      <div class="pane">
        <pre id="sourceBox">Select “View source” to load code…</pre>
      </div>
    </div>

    <div class="modal-foot">
      <a class="btn primary" id="openNew" href="#" target="_blank" rel="noopener">Open in new tab</a>
      <button class="btn danger" id="loadSourceBtn">View source</button>
    </div>
  </div>
</div>

<script>
  const modal = document.getElementById('modal');
  const previewFrame = document.getElementById('previewFrame');
  const sourceBox = document.getElementById('sourceBox');
  const modalFile = document.getElementById('modalFile');
  const openNew = document.getElementById('openNew');
  const closeBtn = document.getElementById('closeBtn');
  const loadSourceBtn = document.getElementById('loadSourceBtn');

  let currentFile = null;
  let currentHref = null;

  function openModal() {
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    previewFrame.src = 'about:blank';
    sourceBox.textContent = 'Select “View source” to load code…';
    currentFile = null;
    currentHref = null;
    document.body.style.overflow = '';
  }

  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

  document.querySelectorAll('[data-preview]').forEach(btn => {
    btn.addEventListener('click', () => {
      const href = btn.getAttribute('data-preview');
      const name = btn.getAttribute('data-name');
      currentFile = name;
      currentHref = href;
      modalFile.textContent = name;
      previewFrame.src = href;
      openNew.href = href;
      loadSourceBtn.disabled = false;
      openModal();
    });
  });

  document.querySelectorAll('[data-source]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const name = btn.getAttribute('data-source');
      // if source clicked from card, open modal with no preview
      currentFile = name;
      currentHref = null;
      modalFile.textContent = name;
      previewFrame.src = 'about:blank';
      openNew.href = '#';
      loadSourceBtn.disabled = false;
      openModal();
      await loadSource(name);
    });
  });

  loadSourceBtn.addEventListener('click', async () => {
    if (!currentFile) return;
    await loadSource(currentFile);
  });

  async function loadSource(name) {
    sourceBox.textContent = 'Loading source…';
    try {
      const url = new URL(window.location.href);
      url.searchParams.set('source', name);
      const res = await fetch(url.toString(), { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const text = await res.text();
      sourceBox.textContent = text;
    } catch (err) {
      sourceBox.textContent = 'Failed to load source: ' + err;
    }
  }
</script>
</body>
</html>
