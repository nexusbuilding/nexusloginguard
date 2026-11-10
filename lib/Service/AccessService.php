<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Service;

use OCA\NexusLoginGuard\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;

/** @psalm-api */
class AccessService {
	public const KEY_GROUPS = 'allowed_groups';
	public const KEY_MANAGER_ENABLE = 'managers_can_enable';
	public const KEY_RETENTION = 'retention_days';

	public function __construct(
		private IGroupManager $groupManager,
		private IAppConfig $appConfig,
	) {
	}

	public function isAdmin(IUser $user): bool {
		return $this->groupManager->isAdmin($user->getUID());
	}

	/** @return string[] */
	public function getAllowedGroups(): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, self::KEY_GROUPS, '[]');
		/** @psalm-suppress MixedAssignment */
		$groups = json_decode($raw, true);
		if (!is_array($groups)) {
			return [];
		}
		return array_values(array_filter($groups, 'is_string'));
	}

	/** @param string[] $groups */
	public function setAllowedGroups(array $groups): void {
		$groups = array_values(array_unique(array_filter($groups, fn (string $g): bool => $this->groupManager->groupExists($g))));
		$this->appConfig->setValueString(Application::APP_ID, self::KEY_GROUPS, json_encode($groups));
	}

	public function canAccess(IUser $user): bool {
		if ($this->isAdmin($user)) {
			return true;
		}
		foreach ($this->getAllowedGroups() as $gid) {
			if ($this->groupManager->isInGroup($user->getUID(), $gid)) {
				return true;
			}
		}
		return false;
	}

	public function managersCanEnable(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, self::KEY_MANAGER_ENABLE, false);
	}

	public function setManagersCanEnable(bool $value): void {
		$this->appConfig->setValueBool(Application::APP_ID, self::KEY_MANAGER_ENABLE, $value);
	}

	public function canEnableAccounts(IUser $user): bool {
		return $this->isAdmin($user) || ($this->canAccess($user) && $this->managersCanEnable());
	}

	public function getRetentionDays(): int {
		return max(1, $this->appConfig->getValueInt(Application::APP_ID, self::KEY_RETENTION, 90));
	}

	public function setRetentionDays(int $days): void {
		$this->appConfig->setValueInt(Application::APP_ID, self::KEY_RETENTION, max(1, min(3650, $days)));
	}
}
