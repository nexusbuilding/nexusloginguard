<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Controller;

use OCA\NexusLoginGuard\AppInfo\Application;
use OCA\NexusLoginGuard\Service\AccessService;
use OCA\NexusLoginGuard\Service\ActionLogService;
use OCA\NexusLoginGuard\Service\AttemptService;
use OCA\NexusLoginGuard\Service\PolicyService;
use OCA\NexusLoginGuard\Service\ThrottleService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Internal API for the app frontend. Access is checked per request:
 * admins and members of the delegated groups only.
 *
 * @psalm-import-type AccountAgg from AttemptService
 * @psalm-import-type IpState from ThrottleService
 * @psalm-suppress UnusedClass
 */
#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
class ApiController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private AccessService $access,
		private AttemptService $attempts,
		private ThrottleService $throttle,
		private ActionLogService $actionLog,
		private PolicyService $policy,
		private IL10N $l,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	private function currentUser(): ?IUser {
		$user = $this->userSession->getUser();
		return ($user !== null && $this->access->canAccess($user)) ? $user : null;
	}

	/** @param Http::STATUS_* $status */
	private function error(string $message, int $status): JSONResponse {
		return new JSONResponse(['error' => $message], $status);
	}

	private function forbidden(): JSONResponse {
		return $this->error($this->l->t('Access denied'), Http::STATUS_FORBIDDEN);
	}

	private static function stateOrder(string $state): int {
		return match ($state) {
			ThrottleService::STATE_LOCKED => 0,
			ThrottleService::STATE_BLOCKED => 1,
			ThrottleService::STATE_THROTTLED => 2,
			ThrottleService::STATE_DISABLED => 3,
			default => 4,
		};
	}

	private static function clampDays(int $days): int {
		return max(1, min(365, $days));
	}

	private static function isValidKey(string $key): bool {
		return (bool)preg_match('/^[ul]:.+/', $key);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/meta')]
	public function meta(): JSONResponse {
		$user = $this->currentUser();
		if ($user === null) {
			return $this->forbidden();
		}
		return new JSONResponse([
			'isAdmin' => $this->access->isAdmin($user),
			'canEnable' => $this->access->canEnableAccounts($user),
			'maxAttempts' => $this->throttle->maxAttempts(),
			'protectionEnabled' => $this->throttle->protectionEnabled(),
			'backend' => $this->throttle->backend(),
			'retentionDays' => $this->access->getRetentionDays(),
			'remoteAddr' => $this->request->getRemoteAddress(),
			'proxyMisconfigured' => $this->proxyHeaderIgnored(),
			'policyMax' => $this->policy->maxAttempts(),
		]);
	}

	/**
	 * True if the request carries an X-Forwarded-For header but Nextcloud
	 * still uses the direct peer address – i.e. the reverse proxy is not
	 * listed in "trusted_proxies" and all clients appear with the proxy IP.
	 */
	private function proxyHeaderIgnored(): bool {
		$forwarded = $this->request->getHeader('X-Forwarded-For');
		$peer = $_SERVER['REMOTE_ADDR'] ?? '';
		return $forwarded !== '' && $peer !== '' && $this->request->getRemoteAddress() === $peer;
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/accounts')]
	public function accounts(int $days = 7, string $search = ''): JSONResponse {
		if ($this->currentUser() === null) {
			return $this->forbidden();
		}
		$now = $this->attempts->now();
		$since = $now - self::clampDays($days) * 86400;
		$liveSince = $now - 12 * 3600;
		$max = $this->throttle->maxAttempts();

		$search = trim($search);
		$policyMax = $this->policy->maxAttempts();
		$rows = [];
		$seen = [];
		foreach ($this->attempts->aggregateByAccount($since, $search) as $key => $agg) {
			$ips = $this->attempts->ipsForKey($key, $since);
			$ipStates = [];
			foreach ($ips as $ip => $info) {
				if ($info['last'] >= $liveSince) {
					$ipStates[] = $this->throttle->ipStatus($ip);
				}
			}
			$rows[] = $this->buildRow($key, $agg, $ips, $ipStates, $max, $policyMax);
			if ($agg['uid'] !== null) {
				$seen[$agg['uid']] = true;
			}
		}

		// Accounts locked by the password policy stay visible until they are enabled again,
		// even when their failed logins are older than the selected period
		foreach ($this->policy->policyLockedUids() as $uid) {
			if (isset($seen[$uid])) {
				continue;
			}
			$user = $this->userManager->get($uid);
			if ($user === null || $user->isEnabled()) {
				continue;
			}
			if ($search !== '' && stripos($uid . ' ' . $user->getDisplayName(), $search) === false) {
				continue;
			}
			$key = 'u:' . $uid;
			$ips = $this->attempts->ipsForKey($key, $now - 30 * 86400);
			$agg = [
				'key' => $key, 'uid' => $uid, 'loginNames' => [$uid],
				'total' => 0, 'c24' => 0, 'c7' => 0, 'lastAttempt' => 0, 'firstAttempt' => 0,
			];
			$rows[] = $this->buildRow($key, $agg, $ips, [], $max, $policyMax);
		}

		usort($rows, /**
		 * @param array{state: string, lastAttempt: int, ...} $a
		 * @param array{state: string, lastAttempt: int, ...} $b
		 */ static fn (array $a, array $b): int
			=> [self::stateOrder($a['state']), -$a['lastAttempt']] <=> [self::stateOrder($b['state']), -$b['lastAttempt']]);

		return new JSONResponse(['accounts' => $rows, 'generatedAt' => $now]);
	}

	/**
	 * @param AccountAgg $agg
	 * @param array<string, array{count: int, last: int}> $ips
	 * @param list<IpState> $ipStates
	 * @return array{state: string, lastAttempt: int, ...<string, mixed>}
	 */
	private function buildRow(string $key, array $agg, array $ips, array $ipStates, int $max, int $policyMax): array {
		$user = $agg['uid'] !== null ? $this->userManager->get($agg['uid']) : null;
		$disabled = $user !== null && !$user->isEnabled();
		$disabledInfo = $disabled ? $this->policy->disabledInfo($user->getUID()) : null;
		$policyCount = ($user !== null && $policyMax > 0) ? $this->policy->counter($user->getUID()) : 0;
		$summary = ThrottleService::summarize($ipStates, $disabled, $max, $disabledInfo, $policyCount, $policyMax);

		return [
			'key' => $key,
			'uid' => $agg['uid'],
			'exists' => $user !== null,
			'displayName' => $user?->getDisplayName() ?? ($agg['uid'] ?? ($agg['loginNames'][0] ?? '')),
			'loginNames' => array_values(array_unique($agg['loginNames'])),
			'enabled' => $user?->isEnabled(),
			'lastLogin' => $user?->getLastLogin() ?? 0,
			'total' => $agg['total'],
			'c24' => $agg['c24'],
			'c7' => $agg['c7'],
			'lastAttempt' => $agg['lastAttempt'],
			'firstAttempt' => $agg['firstAttempt'],
			'lastIp' => array_key_first($ips) ?? '',
			'ipCount' => count($ips),
			'policyCount' => $policyCount,
			'policyMax' => $policyMax,
			'state' => $summary['state'],
			'reasons' => $summary['reasons'],
		];
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/account')]
	public function account(string $key): JSONResponse {
		if ($this->currentUser() === null) {
			return $this->forbidden();
		}
		if (!self::isValidKey($key)) {
			return $this->error($this->l->t('Invalid account'), Http::STATUS_BAD_REQUEST);
		}
		$ips = $this->attempts->ipsForKey($key, $this->attempts->now() - 30 * 86400);
		$ipRows = [];
		foreach ($ips as $ip => $info) {
			$ipRows[] = array_merge($this->throttle->ipStatus($ip), ['count30d' => $info['count'], 'last' => $info['last']]);
		}
		return new JSONResponse([
			'key' => $key,
			'ips' => $ipRows,
			'attempts' => $this->attempts->recentAttempts($key, 100),
		]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/stats')]
	public function stats(int $days = 30): JSONResponse {
		if ($this->currentUser() === null) {
			return $this->forbidden();
		}
		$data = $this->attempts->stats(self::clampDays($days));

		$blocked = 0;
		$throttled = 0;
		foreach ($this->attempts->ipsSince($this->attempts->now() - 12 * 3600) as $ip) {
			$s = $this->throttle->ipStatus($ip);
			$blocked += $s['blocked'] ? 1 : 0;
			$throttled += $s['throttled'] ? 1 : 0;
		}
		$data['live'] = ['blockedIps' => $blocked, 'throttledIps' => $throttled];

		$top = [];
		foreach ($data['topAccounts'] as $a) {
			$user = $a['uid'] !== null ? $this->userManager->get($a['uid']) : null;
			$top[] = [
				'key' => $a['key'],
				'uid' => $a['uid'],
				'displayName' => $user?->getDisplayName() ?? ($a['uid'] ?? $a['loginNames'][0]),
				'exists' => $user !== null,
				'count' => $a['total'],
			];
		}
		$data['topAccounts'] = $top;

		return new JSONResponse($data);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/ip')]
	public function ip(string $ip): JSONResponse {
		if ($this->currentUser() === null) {
			return $this->forbidden();
		}
		$ip = trim($ip);
		if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
			return $this->error($this->l->t('Invalid IP address'), Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse($this->throttle->ipStatus($ip));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/unblock')]
	public function unblock(string $key): JSONResponse {
		$actor = $this->currentUser();
		if ($actor === null) {
			return $this->forbidden();
		}
		if (!self::isValidKey($key)) {
			return $this->error($this->l->t('Invalid account'), Http::STATUS_BAD_REQUEST);
		}
		// The throttler looks back at most 48 h, so every IP of the last 48 h is relevant
		$done = [];
		foreach (array_keys($this->attempts->ipsForKey($key, $this->attempts->now() - 48 * 3600)) as $ip) {
			if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
				$this->throttle->resetIp($ip);
				$done[] = $ip;
			}
		}
		$this->actionLog->add($actor->getUID(), ActionLogService::ACTION_UNBLOCK_ACCOUNT, substr($key, 2), implode(', ', $done));
		return new JSONResponse(['resetIps' => $done]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/unblock-ip')]
	public function unblockIp(string $ip): JSONResponse {
		$actor = $this->currentUser();
		if ($actor === null) {
			return $this->forbidden();
		}
		$ip = trim($ip);
		if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
			return $this->error($this->l->t('Invalid IP address'), Http::STATUS_BAD_REQUEST);
		}
		$this->throttle->resetIp($ip);
		$this->actionLog->add($actor->getUID(), ActionLogService::ACTION_UNBLOCK_IP, $ip);
		return new JSONResponse(['resetIps' => [$ip]]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/enable')]
	public function enable(string $uid): JSONResponse {
		$actor = $this->currentUser();
		if ($actor === null || !$this->access->canEnableAccounts($actor)) {
			return $this->forbidden();
		}
		$user = $this->userManager->get($uid);
		if ($user === null) {
			return $this->error($this->l->t('User not found'), Http::STATUS_NOT_FOUND);
		}
		// Delegated groups must never re-enable admin accounts
		if (!$this->access->isAdmin($actor) && $this->groupManager->isAdmin($uid)) {
			return $this->forbidden();
		}
		$user->setEnabled(true);
		$this->policy->resetCounter($uid);
		$this->actionLog->add($actor->getUID(), ActionLogService::ACTION_ENABLE_ACCOUNT, $uid);
		return new JSONResponse(['enabled' => true]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/reset-counter')]
	public function resetCounter(string $uid): JSONResponse {
		$actor = $this->currentUser();
		if ($actor === null) {
			return $this->forbidden();
		}
		if ($this->userManager->get($uid) === null) {
			return $this->error($this->l->t('User not found'), Http::STATUS_NOT_FOUND);
		}
		$this->policy->resetCounter($uid);
		$this->actionLog->add($actor->getUID(), ActionLogService::ACTION_RESET_COUNTER, $uid);
		return new JSONResponse(['reset' => true]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/actions')]
	public function actions(): JSONResponse {
		if ($this->currentUser() === null) {
			return $this->forbidden();
		}
		return new JSONResponse(['actions' => $this->actionLog->latest(200)]);
	}
}
