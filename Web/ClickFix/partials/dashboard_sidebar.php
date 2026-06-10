<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <div class="sidebar-brand-wrapper d-none d-lg-flex align-items-center justify-content-center fixed-top">
    <a class="sidebar-brand brand-logo" href="<?= clickfix_h(cfurl('home', true)); ?>">
      <span class="brand-lockup">
        <img src="assets/corona/images/clickfix-logo.png" alt="ClickFix Mitigator" width="34" height="34" />
        <span class="brand-lockup-text">ClickFix Mitigator</span>
      </span>
    </a>
    <a class="sidebar-brand brand-logo-mini" href="<?= clickfix_h(cfurl('home', true)); ?>">
      <img src="assets/corona/images/clickfix-logo.png" alt="ClickFix Mitigator" />
    </a>
  </div>
  <ul class="nav">
    <li class="nav-item profile">
      <div class="profile-desc">
        <?php
          $sidebarAvatarUrl = $loggedIn ? (string) ($user['profile_avatar_url'] ?? '') : '';
          $sidebarAvatarSeed = strtoupper(substr((string) ($loggedIn ? ($user['username'] ?? 'U') : 'G'), 0, 1));
          $sidebarStrictLocalPages = ['analytics', 'intel_stats'];
          $sidebarLocalOnly = in_array((string) ($page ?? ''), $sidebarStrictLocalPages, true);
          if ($sidebarLocalOnly && preg_match('#^https?://#i', $sidebarAvatarUrl)) {
              $sidebarAvatarUrl = '';
          }
        ?>
        <div class="profile-pic">
          <div class="count-indicator">
            <?php if ($sidebarAvatarUrl !== ''): ?>
              <img class="img-xs rounded-circle sidebar-profile-avatar" src="<?= clickfix_h($sidebarAvatarUrl); ?>" alt="avatar">
            <?php else: ?>
              <div class="img-xs rounded-circle sidebar-profile-avatar sidebar-profile-avatar--placeholder">
                <?= clickfix_h($sidebarAvatarSeed); ?>
              </div>
            <?php endif; ?>
            <span class="count bg-success"></span>
          </div>
          <div class="profile-name">
            <h5 class="mb-0 font-weight-normal"><?= $loggedIn ? clickfix_h((string) $user['username']) : 'Guest'; ?></h5>
            <span><?= $loggedIn ? clickfix_h((string) ($user['role'] ?? 'analyst')) : 'Public view'; ?></span>
          </div>
        </div>
      </div>
    </li>
    <li class="nav-item nav-category">
      <span class="nav-link">Navigation</span>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link<?= $page === 'home' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('home', true)); ?>">
        <span class="menu-icon"><i class="mdi mdi-speedometer"></i></span>
        <span class="menu-title"><?= clickfix_h(cft('nav_home')); ?></span>
      </a>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link<?= $page === 'search' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('search', true)); ?>">
        <span class="menu-icon"><i class="mdi mdi-magnify"></i></span>
        <span class="menu-title"><?= clickfix_h(cft('nav_search')); ?></span>
      </a>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link<?= $page === 'coverage' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('coverage', true)); ?>">
        <span class="menu-icon"><i class="mdi mdi-shield-check"></i></span>
        <span class="menu-title"><?= clickfix_h(cft('nav_coverage')); ?></span>
      </a>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link<?= $page === 'about' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('about', true)); ?>">
        <span class="menu-icon"><i class="mdi mdi-information-outline"></i></span>
        <span class="menu-title"><?= clickfix_h(cft('nav_about')); ?></span>
      </a>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link<?= $page === 'clickfix_domain_list' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('clickfix_domain_list', true)); ?>">
        <span class="menu-icon"><i class="mdi mdi-format-list-bulleted"></i></span>
        <span class="menu-title">Domain List</span>
      </a>
    </li>
    <?php if (!$loggedIn): ?>
    <li class="nav-item menu-items">
      <a class="nav-link<?= $page === 'access' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('access', true)); ?>">
        <span class="menu-icon"><i class="mdi mdi-login"></i></span>
        <span class="menu-title"><?= clickfix_h(cft('nav_access')); ?></span>
      </a>
    </li>
    <?php endif; ?>

    <?php if ($loggedIn): ?>
    <li class="nav-item nav-category">
      <span class="nav-link">Operations</span>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link" data-bs-toggle="collapse" href="#menu_ops" aria-expanded="<?= in_array($page, ['ops','analytics','intel_stats','intel','investigation','community'], true) ? 'true' : 'false'; ?>" aria-controls="menu_ops">
        <span class="menu-icon"><i class="mdi mdi-clipboard-flow"></i></span>
        <span class="menu-title"><?= clickfix_h(cft('nav_ops')); ?></span>
        <i class="menu-arrow"></i>
      </a>
      <div class="collapse<?= in_array($page, ['ops','analytics','intel_stats','intel','investigation','community'], true) ? ' show' : ''; ?>" id="menu_ops">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"><a class="nav-link<?= $page === 'ops' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('ops')); ?>"><?= clickfix_h(cft('nav_ops')); ?></a></li>
          <li class="nav-item"><a class="nav-link<?= $page === 'analytics' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('analytics')); ?>"><?= clickfix_h(cft('nav_graphs')); ?></a></li>
          <li class="nav-item"><a class="nav-link<?= $page === 'intel_stats' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('intel_stats')); ?>"><?= clickfix_h(cft('nav_intel_stats')); ?></a></li>
          <li class="nav-item"><a class="nav-link<?= ($page === 'intel' || $page === 'investigation') ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('intel')); ?>"><?= clickfix_h(cft('nav_investigation')); ?></a></li>
          <li class="nav-item"><a class="nav-link<?= $page === 'community' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('community')); ?>"><?= clickfix_h(cft('nav_community')); ?></a></li>
        </ul>
      </div>
    </li>
    <?php endif; ?>

    <?php if ($loggedIn && cfcan($user, 'analyst_mid')): ?>
    <li class="nav-item nav-category">
      <span class="nav-link">AI & Automation</span>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link<?= $page === 'llm_settings' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('settings', false, ['tab' => 'llm'])); ?>">
        <span class="menu-icon"><i class="mdi mdi-robot-outline"></i></span>
        <span class="menu-title">LLM Profiles</span>
      </a>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link<?= $page === 'auto_investigation' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('settings', false, ['tab' => 'auto_inv'])); ?>">
        <span class="menu-icon"><i class="mdi mdi-auto-fix"></i></span>
        <span class="menu-title">Auto-Investigation</span>
      </a>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link<?= $page === 'domain_feeds' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('domain_feeds')); ?>">
        <span class="menu-icon"><i class="mdi mdi-cloud-download-outline"></i></span>
        <span class="menu-title">Domain Feeds</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if ($loggedIn && cfcan($user, 'analyst_sr')): ?>
    <li class="nav-item nav-category">
      <span class="nav-link">Advanced</span>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link" data-bs-toggle="collapse" href="#menu_sr" aria-expanded="<?= in_array($page, ['extensions','lists','requests','messaging','data_center'], true) ? 'true' : 'false'; ?>" aria-controls="menu_sr">
        <span class="menu-icon"><i class="mdi mdi-rocket-launch-outline"></i></span>
        <span class="menu-title"><?= clickfix_h(cft('nav_extensions')); ?></span>
        <i class="menu-arrow"></i>
      </a>
      <div class="collapse<?= in_array($page, ['extensions','lists','requests','messaging','data_center'], true) ? ' show' : ''; ?>" id="menu_sr">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"><a class="nav-link<?= $page === 'extensions' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('extensions')); ?>"><?= clickfix_h(cft('nav_extensions')); ?></a></li>
          <li class="nav-item"><a class="nav-link<?= $page === 'lists' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('lists')); ?>"><?= clickfix_h(cft('nav_lists')); ?></a></li>
          <li class="nav-item"><a class="nav-link<?= $page === 'requests' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('requests')); ?>"><?= clickfix_h(cft('nav_requests')); ?></a></li>
          <li class="nav-item"><a class="nav-link<?= $page === 'messaging' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('messaging')); ?>"><?= clickfix_h(cft('nav_messaging')); ?></a></li>
          <li class="nav-item"><a class="nav-link<?= $page === 'data_center' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('data_center')); ?>"><?= clickfix_h(cft('nav_data_center')); ?></a></li>
        </ul>
      </div>
    </li>
    <?php endif; ?>

    <?php if ($loggedIn && cfcan($user, 'admin')): ?>
    <li class="nav-item nav-category">
      <span class="nav-link">Admin</span>
    </li>
    <li class="nav-item menu-items">
      <a class="nav-link" data-bs-toggle="collapse" href="#menu_admin" aria-expanded="<?= in_array($page, ['configs','reports','users','settings'], true) ? 'true' : 'false'; ?>" aria-controls="menu_admin">
        <span class="menu-icon"><i class="mdi mdi-cog-outline"></i></span>
        <span class="menu-title"><?= clickfix_h(cft('nav_settings')); ?></span>
        <i class="menu-arrow"></i>
      </a>
      <div class="collapse<?= in_array($page, ['configs','reports','users','settings'], true) ? ' show' : ''; ?>" id="menu_admin">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"><a class="nav-link<?= $page === 'configs' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('configs')); ?>"><?= clickfix_h(cft('nav_score_config')); ?></a></li>
          <li class="nav-item"><a class="nav-link<?= $page === 'reports' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('reports')); ?>"><?= clickfix_h(cft('nav_reports')); ?></a></li>
          <li class="nav-item"><a class="nav-link<?= $page === 'users' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('users')); ?>"><?= clickfix_h(cft('nav_users')); ?></a></li>
        </ul>
      </div>
    </li>
    <?php endif; ?>
  </ul>
</nav>
