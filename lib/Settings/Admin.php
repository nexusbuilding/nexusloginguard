<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Settings;

use OCA\NexusLoginGuard\AppInfo\Application;
use OCA\NexusLoginGuard\Service\AccessService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

/** @psalm-suppress UnusedClass */
class Admin implements ISettings {
	public function __construct(
		private AccessService $access,
		private IInitialState $initialState,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('admin-config', [
			'allowedGroups' => $this->access->getAllowedGroups(),
			'managersCanEnable' => $this->access->managersCanEnable(),
			'retentionDays' => $this->access->getRetentionDays(),
		]);

		return new TemplateResponse(Application::APP_ID, 'admin');
	}

	public function getSection(): string {
		return 'security';
	}

	public function getPriority(): int {
		return 60;
	}
}
