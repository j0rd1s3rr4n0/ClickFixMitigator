<?php
// Legacy entrypoint: preserve backward compatibility for deployments that expect /dashboard.php at repo root.
// The dashboard implementation now lives in Web/ClickFix/dashboard.php.
require __DIR__ . '/Web/ClickFix/dashboard.php';
