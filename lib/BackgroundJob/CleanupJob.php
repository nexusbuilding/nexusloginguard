<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\BackgroundJob;

use OCA\NexusLoginGuard\Service\AccessService;
use OCA\NexusLoginGuard\Service\ActionLogService;
use OCA\NexusLoginGuard\Service\AttemptService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/** @psalm-api */
class CleanupJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private AttemptService $attempts,
		private ActionLogService $actions,
		private AccessService $access,
	) {
		parent::__construct($time);
		$this->setInterval(86400);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	/** @param mixed $argument */
	#[\Override]
	protected function run($argument): void {
		$cutoff = $this->time->getTime() - $this->access->getRetentionDays() * 86400;
		$this->attempts->deleteOlderThan($cutoff);
		// Audit trail is kept a year at least
		$this->actions->deleteOlderThan(min($cutoff, $this->time->getTime() - 365 * 86400));
	}
}
