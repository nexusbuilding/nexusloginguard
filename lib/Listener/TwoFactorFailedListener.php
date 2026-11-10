<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Listener;

use OCA\NexusLoginGuard\Service\AttemptService;
use OCP\Authentication\TwoFactorAuth\TwoFactorProviderChallengeFailed;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * @psalm-api
 * @template-implements IEventListener<TwoFactorProviderChallengeFailed>
 */
class TwoFactorFailedListener implements IEventListener {
	public function __construct(
		private AttemptService $attempts,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof TwoFactorProviderChallengeFailed) {
			return;
		}
		try {
			$user = $event->getUser();
			$this->attempts->log($user->getUID(), $user->getUID(), AttemptService::REASON_TWOFACTOR);
		} catch (\Throwable $e) {
			$this->logger->warning('nexusloginguard: could not log 2FA failure', ['exception' => $e]);
		}
	}
}
