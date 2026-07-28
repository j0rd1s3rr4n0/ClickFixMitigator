<nav class="navbar p-0 fixed-top d-flex flex-row" id="app-header">
  <div class="navbar-brand-wrapper d-flex d-lg-none align-items-center justify-content-center">
    <a class="navbar-brand brand-logo-mini" href="<?= clickfix_h(cfurl('home', true)); ?>">
      <span class="brand-lockup brand-lockup-mini">
        <img src="assets/corona/images/clickfix-logo.png" alt="ClickFix Mitigator" width="28" height="28" />
        <span class="brand-lockup-text">ClickFix Mitigator</span>
      </span>
    </a>
  </div>
  <div class="navbar-menu-wrapper flex-grow d-flex align-items-stretch">
    <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
      <span class="mdi mdi-menu"></span>
    </button>
    <ul class="navbar-nav w-100">
      <li class="nav-item w-100">
        <div class="nav-link mt-2 mt-md-0 d-none d-lg-flex align-items-center gap-2">
          <span class="badge badge-outline-light"><?= clickfix_h(cft('label_module')); ?>: <?= clickfix_h($currentPageLabel); ?></span>
          <span class="badge badge-outline-light">records: <?= (int) ($metrics['total_alerts'] ?? 0); ?></span>
          <span class="badge badge-outline-light">module: <?= clickfix_h($currentPageLabel); ?></span>
        </div>
      </li>
    </ul>
    <ul class="navbar-nav navbar-nav-right">
      <li class="nav-item dropdown d-none d-lg-block">
        <a class="nav-link btn btn-success create-new-button" id="displaySettingsDropdown" data-bs-toggle="dropdown" aria-expanded="false" href="#">Display</a>
        <div class="dropdown-menu dropdown-menu-end navbar-dropdown preview-list" aria-labelledby="displaySettingsDropdown">
          <h6 class="p-3 mb-0">Display Settings</h6>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item preview-item" href="javascript:void(0)" id="display-settings-toggle">
            <div class="preview-thumbnail">
              <div class="preview-icon bg-dark rounded-circle">
                <i class="mdi mdi-tune-vertical text-success"></i>
              </div>
            </div>
            <div class="preview-item-content">
              <p class="preview-subject ellipsis mb-1">Open display panel</p>
            </div>
          </a>
        </div>
      </li>
      <li class="nav-item dropdown border-left">
        <a class="nav-link count-indicator dropdown-toggle" id="langDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="mdi mdi-translate"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-end navbar-dropdown preview-list" aria-labelledby="langDropdown">
          <h6 class="p-3 mb-0"><?= clickfix_h(cft('lang_label')); ?></h6>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item preview-item" href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'es'])); ?>">ES</a>
          <a class="dropdown-item preview-item" href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'en'])); ?>">EN</a>
          <a class="dropdown-item preview-item" href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'ca'])); ?>">CA</a>
          <a class="dropdown-item preview-item" href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'de'])); ?>">DE</a>
          <a class="dropdown-item preview-item" href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'fr'])); ?>">FR</a>
          <a class="dropdown-item preview-item" href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'it'])); ?>">IT</a>
        </div>
      </li>
      <?php if ($loggedIn): ?>
      <?php
        $headerAvatarUrl = (string) ($user['profile_avatar_url'] ?? '');
        $headerAvatarSeed = strtoupper(substr((string) ($user['username'] ?? 'U'), 0, 1));
        $headerStrictLocalPages = ['analytics', 'intel_stats'];
        $headerLocalOnly = in_array((string) ($page ?? ''), $headerStrictLocalPages, true);
        if ($headerLocalOnly && preg_match('#^https?://#i', $headerAvatarUrl)) {
            $headerAvatarUrl = '';
        }
      ?>
      <li class="nav-item dropdown">
        <a class="nav-link" id="profileDropdown" href="#" data-bs-toggle="dropdown">
          <div class="navbar-profile">
            <?php if ($headerAvatarUrl !== ''): ?>
              <img class="img-xs rounded-circle navbar-profile-avatar" src="<?= clickfix_h($headerAvatarUrl); ?>" alt="avatar">
            <?php else: ?>
              <span class="img-xs rounded-circle navbar-profile-avatar navbar-profile-avatar--placeholder" aria-hidden="true">
                <?= clickfix_h($headerAvatarSeed); ?>
              </span>
            <?php endif; ?>
            <p class="mb-0 d-none d-sm-block navbar-profile-name"><?= clickfix_h((string) $user['username']); ?></p>
            <i class="mdi mdi-menu-down d-none d-sm-block"></i>
          </div>
        </a>
        <div class="dropdown-menu dropdown-menu-end navbar-dropdown preview-list" aria-labelledby="profileDropdown">
          <h6 class="p-3 mb-0">Profile</h6>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item preview-item" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>">
            <div class="preview-thumbnail">
              <div class="preview-icon bg-dark rounded-circle">
                <i class="mdi mdi-account text-primary"></i>
              </div>
            </div>
            <div class="preview-item-content">
              <p class="preview-subject mb-1"><?= clickfix_h(cft('nav_profile')); ?></p>
            </div>
          </a>
          <a class="dropdown-item preview-item" href="<?= clickfix_h(cfurl('settings')); ?>">
            <div class="preview-thumbnail">
              <div class="preview-icon bg-dark rounded-circle">
                <i class="mdi mdi-cog text-success"></i>
              </div>
            </div>
            <div class="preview-item-content">
              <p class="preview-subject mb-1"><?= clickfix_h(cft('nav_settings')); ?></p>
            </div>
          </a>
          <div class="dropdown-divider"></div>
          <form method="post" class="px-3">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn-danger btn-sm w-100">Cerrar sesi?n</button>
          </form>
        </div>
      </li>
      <?php endif; ?>
    </ul>
    <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
      <span class="mdi mdi-format-line-spacing"></span>
    </button>
  </div>
</nav>

<div class="display-settings-panel" id="display-settings-panel" aria-hidden="true">
  <div class="display-settings-header">
    <h4>Display Settings</h4>
    <button class="nav-btn" type="button" id="display-settings-close">&times;</button>
  </div>
  <div class="display-settings-grid">
    <div class="display-toggle">
      <div class="label">Dark mode</div>
      <label class="switch">
        <input type="checkbox" data-setting="dark">
        <span class="switch-track"><span class="switch-thumb"></span></span>
      </label>
    </div>
    <div class="display-toggle">
      <div class="label">Contrast</div>
      <label class="switch">
        <input type="checkbox" data-setting="contrast">
        <span class="switch-track"><span class="switch-thumb"></span></span>
      </label>
    </div>
    <div class="display-toggle">
      <div class="label">Compact</div>
      <label class="switch">
        <input type="checkbox" data-setting="compact">
        <span class="switch-track"><span class="switch-thumb"></span></span>
      </label>
    </div>
    <div class="display-toggle">
      <div class="label">Reduce Motion</div>
      <label class="switch">
        <input type="checkbox" data-setting="reducedMotion">
        <span class="switch-track"><span class="switch-thumb"></span></span>
      </label>
    </div>
    <div class="display-toggle">
      <div class="label">Decorations</div>
      <label class="switch">
        <input type="checkbox" data-setting="decorations">
        <span class="switch-track"><span class="switch-thumb"></span></span>
      </label>
    </div>
    <div class="display-toggle">
      <div class="label">Template UI</div>
      <label class="switch">
        <input type="checkbox" data-setting="template">
        <span class="switch-track"><span class="switch-thumb"></span></span>
      </label>
    </div>
  </div>
  <div class="display-section">
    <h5>Presets</h5>
    <div class="preset-grid">
      <button class="preset-card" type="button" data-preset="integrated" data-accent="blue">
        <span class="preset-thumb preset-thumb--integrated">
          <span class="preset-dot" style="background:#5b8bff"></span>
        </span>
        <span class="preset-meta">
          <span class="preset-name">Integrado</span>
          <span class="preset-desc">Sidebar + grid amplio</span>
        </span>
      </button>
      <button class="preset-card" type="button" data-preset="wide" data-accent="green">
        <span class="preset-thumb preset-thumb--wide">
          <span class="preset-dot" style="background:#37e3a7"></span>
        </span>
        <span class="preset-meta">
          <span class="preset-name">Panorámico</span>
          <span class="preset-desc">Más columnas visibles</span>
        </span>
      </button>
      <button class="preset-card" type="button" data-preset="split" data-accent="purple">
        <span class="preset-thumb preset-thumb--split">
          <span class="preset-dot" style="background:#a685ff"></span>
        </span>
        <span class="preset-meta">
          <span class="preset-name">Split</span>
          <span class="preset-desc">Dos paneles de foco</span>
        </span>
      </button>
      <button class="preset-card" type="button" data-preset="focused" data-accent="amber">
        <span class="preset-thumb preset-thumb--focused">
          <span class="preset-dot" style="background:#ffb454"></span>
        </span>
        <span class="preset-meta">
          <span class="preset-name">Foco</span>
          <span class="preset-desc">Centro dominante</span>
        </span>
      </button>
      <button class="preset-card" type="button" data-preset="compact" data-accent="red">
        <span class="preset-thumb preset-thumb--compact">
          <span class="preset-dot" style="background:#ff7a86"></span>
        </span>
        <span class="preset-meta">
          <span class="preset-name">Compacto</span>
          <span class="preset-desc">Densidad máxima</span>
        </span>
      </button>
      <button class="preset-card" type="button" data-preset="minimal" data-accent="cyan">
        <span class="preset-thumb preset-thumb--minimal">
          <span class="preset-dot" style="background:#5fd8ff"></span>
        </span>
        <span class="preset-meta">
          <span class="preset-name">Minimal</span>
          <span class="preset-desc">Menos ruido visual</span>
        </span>
      </button>
    </div>
  </div>
  <div class="display-section">
    <h5>Font</h5>
    <div class="font-grid">
      <button class="font-btn" type="button" data-font="jakarta"><b>Plus Jakarta</b><span>Template</span></button>
      <button class="font-btn" type="button" data-font="public"><b>Public Sans</b><span>UI sans</span></button>
      <button class="font-btn" type="button" data-font="dm"><b>DM Sans</b><span>Modern sans</span></button>
      <button class="font-btn" type="button" data-font="nunito"><b>Nunito Sans</b><span>Rounded</span></button>
      <button class="font-btn" type="button" data-font="sora"><b>Sora</b><span>Tech display</span></button>
      <button class="font-btn" type="button" data-font="arial"><b>Arial</b><span>System sans</span></button>
      <button class="font-btn" type="button" data-font="helvetica"><b>Helvetica</b><span>Classic</span></button>
      <button class="font-btn" type="button" data-font="ubuntu"><b>Ubuntu</b><span>Humanist</span></button>
      <button class="font-btn" type="button" data-font="roboto"><b>Roboto</b><span>UI default</span></button>
    </div>
  </div>
</div>
