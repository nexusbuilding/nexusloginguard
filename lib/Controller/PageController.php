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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

/** @psalm-suppress UnusedClass */
class PageController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private AccessService $access,
		private IInitialState $initialState,
		private IL10N $l,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		$user = $this->userSession->getUser();
		if ($user === null || !$this->access->canAccess($user)) {
			$response = new TemplateResponse('core', '403', ['message' => $this->l->t('You are not allowed to access Login Guard.')], TemplateResponse::RENDER_AS_GUEST);
			$response->setStatus(Http::STATUS_FORBIDDEN);
			return $response;
		}

		$this->initialState->provideInitialState('config', [
			'isAdmin' => $this->access->isAdmin($user),
			'canEnable' => $this->access->canEnableAccounts($user),
		]);

		return new TemplateResponse(Application::APP_ID, 'main');
	}
}
