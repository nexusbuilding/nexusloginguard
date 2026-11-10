<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\NexusLoginGuard\AppInfo\Application;
use OCP\Util;

Util::addScript(Application::APP_ID, Application::APP_ID . '-admin');
Util::addStyle(Application::APP_ID, Application::APP_ID . '-admin');

?>

<div id="nexusloginguard-admin"></div>
