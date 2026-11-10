<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Listener;

use OCA\NexusLoginGuard\Service\AttemptService;
use OCP\Authentication\Events\AnyLoginFailedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * @psalm-api
 * @template-implements IEventListener<AnyLoginFailedEvent>
 */
class LoginFailedListener implements IEventListener {
	public function __construct(
		private AttemptService $attempts,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AnyLoginFailedEvent) {
			return;
		}
		try {
			$loginName = trim($event->getLoginName());
			$user = $this->resolveUser($loginName);

			if ($user === null) {
				$reason = AttemptService::REASON_UNKNOWN_USER;
			} elseif (!$user->isEnabled()) {
				$reason = AttemptService::REASON_USER_DISABLED;
			} else {
				$reason = AttemptService::REASON_WRONG_PASSWORD;
			}

			$this->attempts->log($user?->getUID(), $loginName, $reason);
		} catch (\Throwable $e) {
			// Never break the login flow because of logging
			$this->logger->warning('nexusloginguard: could not log failed login', ['exception' => $e]);
		}
	}

	private function resolveUser(string $loginName): ?IUser {
		if ($loginName === '') {
			return null;
		}
		$user = $this->userManager->get($loginName);
		if ($user !== null) {
			return $user;
		}
		if (str_contains($loginName, '@')) {
			$byMail = $this->userManager->getByEmail($loginName);
			if (count($byMail) === 1) {
				return $byMail[0];
			}
		}
		return null;
	}
}
