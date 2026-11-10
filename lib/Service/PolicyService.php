<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Service;

use OCA\NexusLoginGuard\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IConfig;

/**
 * Integration with the "Password policy" app (password_policy):
 * its "maximum login attempts" setting disables an account after N
 * consecutive failed logins. The counter lives in the user preferences
 * (password_policy / failedLoginAttempts).
 *
 * In addition, this service remembers why and by whom an account was
 * disabled (set by {@see \OCA\NexusLoginGuard\Listener\UserChangedListener}).
 *
 * @psalm-type DisabledInfo = array{reason: 'policy'|'manual'|'system', by: string, at: int, attempts: int}
 * @psalm-api
 */
class PolicyService {
	public const POLICY_APP = 'password_policy';
	private const POLICY_MAX_KEY = 'maximumLoginAttempts';
	private const POLICY_COUNTER_KEY = 'failedLoginAttempts';

	public const REASON_POLICY = 'policy';
	public const REASON_MANUAL = 'manual';
	public const REASON_SYSTEM = 'system';

	private const PREF_REASON = 'disabled_reason';
	private const PREF_BY = 'disabled_by';
	private const PREF_AT = 'disabled_at';
	private const PREF_ATTEMPTS = 'disabled_attempts';

	private ?int $max = null;

	public function __construct(
		private IAppManager $appManager,
		private IAppConfig $appConfig,
		private IConfig $config,
		private ITimeFactory $time,
	) {
	}

	/** "Maximum login attempts" of the password policy app, 0 = disabled or app not enabled */
	public function maxAttempts(): int {
		if ($this->max === null) {
			$this->max = $this->appManager->isInstalled(self::POLICY_APP)
				? max(0, $this->appConfig->getValueInt(self::POLICY_APP, self::POLICY_MAX_KEY, 0))
				: 0;
		}
		return $this->max;
	}

	public function isActive(): bool {
		return $this->maxAttempts() > 0;
	}

	/** Consecutive failed logins counted by the password policy app */
	public function counter(string $uid): int {
		return max(0, (int)$this->config->getUserValue($uid, self::POLICY_APP, self::POLICY_COUNTER_KEY, '0'));
	}

	public function resetCounter(string $uid): void {
		$this->config->setUserValue($uid, self::POLICY_APP, self::POLICY_COUNTER_KEY, '0');
	}

	/** @param self::REASON_* $reason */
	public function recordDisabled(string $uid, string $reason, string $by): void {
		$this->config->setUserValue($uid, Application::APP_ID, self::PREF_REASON, $reason);
		$this->config->setUserValue($uid, Application::APP_ID, self::PREF_BY, $by);
		$this->config->setUserValue($uid, Application::APP_ID, self::PREF_AT, (string)$this->time->getTime());
		$this->config->setUserValue($uid, Application::APP_ID, self::PREF_ATTEMPTS, (string)$this->maxAttempts());
	}

	public function clearDisabled(string $uid): void {
		foreach ([self::PREF_REASON, self::PREF_BY, self::PREF_AT, self::PREF_ATTEMPTS] as $key) {
			$this->config->deleteUserValue($uid, Application::APP_ID, $key);
		}
	}

	/** @return DisabledInfo|null */
	public function disabledInfo(string $uid): ?array {
		$reason = $this->config->getUserValue($uid, Application::APP_ID, self::PREF_REASON, '');
		if (!in_array($reason, [self::REASON_POLICY, self::REASON_MANUAL, self::REASON_SYSTEM], true)) {
			return null;
		}
		return [
			'reason' => $reason,
			'by' => $this->config->getUserValue($uid, Application::APP_ID, self::PREF_BY, ''),
			'at' => (int)$this->config->getUserValue($uid, Application::APP_ID, self::PREF_AT, '0'),
			'attempts' => (int)$this->config->getUserValue($uid, Application::APP_ID, self::PREF_ATTEMPTS, '0'),
		];
	}

	/** @return list<string> uids that were disabled by the password policy (may be enabled again meanwhile) */
	public function policyLockedUids(): array {
		return array_map('strval', $this->config->getUsersForUserValue(Application::APP_ID, self::PREF_REASON, self::REASON_POLICY));
	}
}
