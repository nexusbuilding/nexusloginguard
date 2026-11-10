<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Controller;

use OCA\NexusLoginGuard\AppInfo\Application;
use OCA\NexusLoginGuard\Service\AccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Admin only: no #[NoAdminRequired] attribute.
 *
 * @psalm-suppress UnusedClass
 */
class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private AccessService $access,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @param list<string> $groups
	 */
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'PUT', url: '/settings')]
	public function save(array $groups = [], bool $managersCanEnable = false, int $retentionDays = 90): JSONResponse {
		$this->access->setAllowedGroups($groups);
		$this->access->setManagersCanEnable($managersCanEnable);
		$this->access->setRetentionDays($retentionDays);

		return new JSONResponse([
			'allowedGroups' => $this->access->getAllowedGroups(),
			'managersCanEnable' => $this->access->managersCanEnable(),
			'retentionDays' => $this->access->getRetentionDays(),
		]);
	}
}
