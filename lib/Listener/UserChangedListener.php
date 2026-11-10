<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Listener;

use OCA\NexusLoginGuard\Service\ActionLogService;
use OCA\NexusLoginGuard\Service\PolicyService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\User\Events\UserChangedEvent;
use Psr\Log\LoggerInterface;

/**
 * Remembers why an account was disabled:
 * - during an unauthenticated web request while the password policy limit is
 *   active → disabled by the password policy after too many failed logins
 * - by a logged-in user → manually (admin / user management)
 * - otherwise (occ, cron, LDAP sync …) → system
 *
 * @psalm-api
 * @template-implements IEventListener<UserChangedEvent>
 */
class UserChangedListener implements IEventListener {
	public function __construct(
		private PolicyService $policy,
		private ActionLogService $actionLog,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserChangedEvent)) {
			return;
		}
		if ($event->getFeature() !== 'enabled') {
			return;
		}
		try {
			$uid = $event->getUser()->getUID();
			$enabled = filter_var($event->getValue(), FILTER_VALIDATE_BOOLEAN);

			if ($enabled) {
				$this->policy->clearDisabled($uid);
				return;
			}

			$actor = $this->userSession->getUser();
			if ($actor !== null && $actor->getUID() !== $uid) {
				$this->policy->recordDisabled($uid, PolicyService::REASON_MANUAL, $actor->getUID());
				$this->actionLog->add($actor->getUID(), ActionLogService::ACTION_ACCOUNT_DISABLED, $uid);
			} elseif (PHP_SAPI !== 'cli' && $actor === null && $this->policy->isActive()) {
				$this->policy->recordDisabled($uid, PolicyService::REASON_POLICY, PolicyService::POLICY_APP);
				$this->actionLog->add(PolicyService::POLICY_APP, ActionLogService::ACTION_POLICY_LOCK, $uid, (string)$this->policy->maxAttempts());
			} else {
				$this->policy->recordDisabled($uid, PolicyService::REASON_SYSTEM, PHP_SAPI === 'cli' ? 'occ' : '');
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Could not record account state change', ['exception' => $e]);
		}
	}
}
