<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Service;

use OCP\IConfig;
use OCP\Security\Bruteforce\IThrottler;
use Psr\Log\LoggerInterface;

/**
 * Reads the live state of Nextcloud's brute-force protection and resets it.
 *
 * Works with both throttler backends (database and Redis memory cache), because
 * all access goes through {@see IThrottler}. Nextcloud throttles per subnet
 * (IPv4 /32, IPv6 /64) and action – not per user account.
 *
 * Note: IThrottler::getAttempts(), getDelay() and resetDelayForIP() are marked
 * deprecated/internal since Nextcloud 28, but they are the only public API to
 * read the throttling state and are what `occ security:bruteforce:*` uses.
 *
 * @psalm-type IpState = array{ip: string, attempts12h: int, attempts30m: int, delayMs: int, blocked: bool, throttled: bool, bypass: bool}
 * @psalm-type Reason = array{type: 'ip_blocked'|'ip_throttled'|'account_disabled', ip?: string, attempts?: int, limit?: int, delayMs?: int}
 * @psalm-api
 */
class ThrottleService {
	public const ACTION = 'login';

	public const STATE_BLOCKED = 'blocked';
	public const STATE_THROTTLED = 'throttled';
	public const STATE_DISABLED = 'disabled';
	public const STATE_OK = 'ok';

	/** @var array<string, IpState> */
	private array $cache = [];

	public function __construct(
		private IThrottler $throttler,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	public function maxAttempts(): int {
		return $this->config->getSystemValueInt('auth.bruteforce.max-attempts', 10);
	}

	public function protectionEnabled(): bool {
		return $this->config->getSystemValueBool('auth.bruteforce.protection.enabled', true);
	}

	/** @return 'redis'|'database' */
	public function backend(): string {
		if (!$this->config->getSystemValueBool('auth.bruteforce.protection.force.database', false)
			&& ltrim($this->config->getSystemValueString('memcache.distributed', ''), '\\') === 'OC\\Memcache\\Redis') {
			return 'redis';
		}
		return 'database';
	}

	/**
	 * @return IpState
	 * @psalm-suppress DeprecatedMethod
	 */
	public function ipStatus(string $ip): array {
		if (isset($this->cache[$ip])) {
			return $this->cache[$ip];
		}
		$bypass = false;
		$a12 = 0;
		$a30 = 0;
		$delay = 0;
		try {
			$bypass = $this->throttler->isBypassListed($ip);
			if (!$bypass) {
				$a12 = $this->throttler->getAttempts($ip, self::ACTION, 12);
				if ($a12 > 0) {
					$a30 = $this->throttler->getAttempts($ip, self::ACTION, 0.5);
					$delay = $this->throttler->getDelay($ip, self::ACTION);
				}
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Brute-force lookup failed for ' . $ip, ['exception' => $e]);
		}

		return $this->cache[$ip] = self::buildIpState($ip, $a12, $a30, $delay, $bypass, $this->maxAttempts());
	}

	/** @return IpState */
	public static function buildIpState(string $ip, int $attempts12h, int $attempts30m, int $delayMs, bool $bypass, int $maxAttempts): array {
		// Same rule as Throttler::sleepDelayOrThrowOnMax()
		$blocked = $attempts12h > $maxAttempts && $attempts30m > $maxAttempts;
		return [
			'ip' => $ip,
			'attempts12h' => $attempts12h,
			'attempts30m' => $attempts30m,
			'delayMs' => $delayMs,
			'blocked' => $blocked,
			'throttled' => !$blocked && $attempts12h > 0,
			'bypass' => $bypass,
		];
	}

	/** @psalm-suppress DeprecatedMethod */
	public function resetIp(string $ip): void {
		$this->throttler->resetDelayForIP($ip);
		unset($this->cache[$ip]);
	}

	/**
	 * Overall state and machine readable reasons (translated in the frontend).
	 *
	 * @param list<IpState> $ipStates
	 * @return array{state: string, reasons: list<Reason>}
	 */
	public static function summarize(array $ipStates, bool $accountDisabled, int $maxAttempts): array {
		$reasons = [];
		$hasBlocked = false;
		$hasThrottled = false;

		foreach ($ipStates as $s) {
			if ($s['blocked']) {
				$hasBlocked = true;
				$reasons[] = ['type' => 'ip_blocked', 'ip' => $s['ip'], 'attempts' => $s['attempts30m'], 'limit' => $maxAttempts];
			}
		}
		foreach ($ipStates as $s) {
			if ($s['throttled']) {
				$hasThrottled = true;
				$reasons[] = ['type' => 'ip_throttled', 'ip' => $s['ip'], 'attempts' => $s['attempts12h'], 'delayMs' => $s['delayMs']];
			}
		}
		if ($accountDisabled) {
			$reasons[] = ['type' => 'account_disabled'];
		}

		$state = match (true) {
			$hasBlocked => self::STATE_BLOCKED,
			$hasThrottled => self::STATE_THROTTLED,
			$accountDisabled => self::STATE_DISABLED,
			default => self::STATE_OK,
		};

		return ['state' => $state, 'reasons' => $reasons];
	}
}
