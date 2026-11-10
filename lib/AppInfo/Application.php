<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\AppInfo;

use OCA\NexusLoginGuard\Listener\LoginFailedListener;
use OCA\NexusLoginGuard\Listener\TwoFactorFailedListener;
use OCA\NexusLoginGuard\Listener\UserChangedListener;
use OCA\NexusLoginGuard\Service\AccessService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Authentication\Events\AnyLoginFailedEvent;
use OCP\Authentication\TwoFactorAuth\TwoFactorProviderChallengeFailed;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\User\Events\UserChangedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'nexusloginguard';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(AnyLoginFailedEvent::class, LoginFailedListener::class);
		$context->registerEventListener(TwoFactorProviderChallengeFailed::class, TwoFactorFailedListener::class);
		$context->registerEventListener(UserChangedEvent::class, UserChangedListener::class);
	}

	public function boot(IBootContext $context): void {
		// The navigation entry is only shown to admins and members of the
		// delegated groups, so it cannot be declared statically in info.xml.
		$context->injectFn(function (
			INavigationManager $nav,
			IUserSession $userSession,
			AccessService $access,
			IURLGenerator $url,
			IFactory $l10nFactory,
		): void {
			$user = $userSession->getUser();
			if ($user === null || !$access->canAccess($user)) {
				return;
			}
			$nav->add(static function () use ($url, $l10nFactory): array {
				return [
					'id' => self::APP_ID,
					'order' => 90,
					'href' => $url->linkToRoute(self::APP_ID . '.page.index'),
					'icon' => $url->imagePath(self::APP_ID, 'app.svg'),
					'name' => $l10nFactory->get(self::APP_ID)->t('Login Guard'),
				];
			});
		});
	}
}
