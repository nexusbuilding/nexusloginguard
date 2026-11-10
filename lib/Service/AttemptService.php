<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;

/**
 * Stores and aggregates failed login attempts.
 *
 * @psalm-type DbRow = array<string, string|int|float|null>
 * @psalm-type AccountAgg = array{key: string, uid: ?string, loginNames: list<string>, total: int, c24: int, c7: int, lastAttempt: int, firstAttempt: int}
 * @psalm-type AttemptRow = array{loginName: string, ip: string, userAgent: string, client: string, reason: string, occurred: int}
 * @psalm-api
 */
class AttemptService {
	public const TABLE = 'nexuslg_attempts';

	public const REASON_WRONG_PASSWORD = 'wrong_password';
	public const REASON_UNKNOWN_USER = 'unknown_user';
	public const REASON_USER_DISABLED = 'user_disabled';
	public const REASON_TWOFACTOR = 'twofactor_failed';

	private const DEFAULT_TIMEZONE = 'Europe/Berlin';

	public function __construct(
		private IDBConnection $db,
		private IRequest $request,
		private ITimeFactory $time,
		private IConfig $config,
	) {
	}

	public function now(): int {
		return $this->time->getTime();
	}

	public function log(?string $uid, string $loginName, string $reason): void {
		$now = $this->now();
		$ua = $this->request->getHeader('User-Agent');

		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'uid' => $qb->createNamedParameter($uid),
			'login_name' => $qb->createNamedParameter(mb_substr($loginName, 0, 255)),
			'ip' => $qb->createNamedParameter(mb_substr($this->request->getRemoteAddress(), 0, 64)),
			'user_agent' => $qb->createNamedParameter($ua === '' ? null : mb_substr($ua, 0, 255)),
			'reason' => $qb->createNamedParameter($reason),
			'occurred' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
			'day' => $qb->createNamedParameter($this->dayKey($now), IQueryBuilder::PARAM_INT),
		])->executeStatement();
	}

	public function timezone(): \DateTimeZone {
		$tz = $this->config->getSystemValueString('default_timezone', self::DEFAULT_TIMEZONE);
		try {
			return new \DateTimeZone($tz !== '' ? $tz : self::DEFAULT_TIMEZONE);
		} catch (\Throwable) {
			return new \DateTimeZone(self::DEFAULT_TIMEZONE);
		}
	}

	public function dayKey(int $ts): int {
		return (int)(new \DateTimeImmutable('@' . $ts))->setTimezone($this->timezone())->format('Ymd');
	}

	/** @return list<DbRow> */
	private static function rows(IResult $result): array {
		/** @var list<DbRow> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	private static function str(mixed $v): string {
		return is_scalar($v) ? (string)$v : '';
	}

	private static function int(mixed $v): int {
		return is_numeric($v) ? (int)$v : 0;
	}

	/**
	 * Aggregated list of accounts / login names with failed attempts.
	 * Rows for the same uid (e.g. login via e-mail and via uid) are merged;
	 * unknown login names are listed on their own.
	 *
	 * @return array<string, AccountAgg> keyed by "u:<uid>" or "l:<login name>"
	 */
	public function aggregateByAccount(int $sinceTs, string $search = '', int $limit = 500): array {
		$now = $this->now();
		$qb = $this->db->getQueryBuilder();
		$p24 = $qb->createNamedParameter($now - 86400, IQueryBuilder::PARAM_INT);
		$p7 = $qb->createNamedParameter($now - 7 * 86400, IQueryBuilder::PARAM_INT);

		$qb->select('uid', 'login_name')
			->selectAlias($qb->func()->count('*'), 'total')
			->selectAlias($qb->createFunction('SUM(CASE WHEN ' . $qb->getColumnName('occurred') . ' >= ' . $p24 . ' THEN 1 ELSE 0 END)'), 'c24')
			->selectAlias($qb->createFunction('SUM(CASE WHEN ' . $qb->getColumnName('occurred') . ' >= ' . $p7 . ' THEN 1 ELSE 0 END)'), 'c7')
			->selectAlias($qb->func()->max('occurred'), 'last_occ')
			->selectAlias($qb->func()->min('occurred'), 'first_occ')
			->from(self::TABLE)
			->where($qb->expr()->gte('occurred', $qb->createNamedParameter($sinceTs, IQueryBuilder::PARAM_INT)))
			->groupBy('uid', 'login_name')
			->orderBy('last_occ', 'DESC')
			->setMaxResults($limit * 2);

		if ($search !== '') {
			$like = '%' . $this->db->escapeLikeParameter($search) . '%';
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('uid', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('login_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('ip', $qb->createNamedParameter($like)),
			));
		}

		/** @var array<string, AccountAgg> $out */
		$out = [];
		foreach (self::rows($qb->executeQuery()) as $r) {
			$uid = self::str($r['uid'] ?? null);
			$uid = $uid !== '' ? $uid : null;
			$login = self::str($r['login_name'] ?? null);
			$key = $uid !== null ? 'u:' . $uid : 'l:' . $login;
			$e = $out[$key] ?? [
				'key' => $key,
				'uid' => $uid,
				'loginNames' => [],
				'total' => 0,
				'c24' => 0,
				'c7' => 0,
				'lastAttempt' => 0,
				'firstAttempt' => PHP_INT_MAX,
			];
			$e['loginNames'][] = $login;
			$e['total'] += self::int($r['total'] ?? 0);
			$e['c24'] += self::int($r['c24'] ?? 0);
			$e['c7'] += self::int($r['c7'] ?? 0);
			$e['lastAttempt'] = max($e['lastAttempt'], self::int($r['last_occ'] ?? 0));
			$e['firstAttempt'] = min($e['firstAttempt'], self::int($r['first_occ'] ?? 0));
			$out[$key] = $e;
		}

		uasort($out, static fn (array $a, array $b): int => $b['lastAttempt'] <=> $a['lastAttempt']);
		return array_slice($out, 0, $limit, true);
	}

	/** Restrict a query to one account key ("u:<uid>" / "l:<login>") */
	private function whereKey(IQueryBuilder $qb, string $key): void {
		if (str_starts_with($key, 'u:')) {
			$qb->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter(substr($key, 2))));
		} else {
			$qb->andWhere($qb->expr()->isNull('uid'));
			$qb->andWhere($qb->expr()->eq('login_name', $qb->createNamedParameter(substr($key, 2))));
		}
	}

	/** @return list<AttemptRow> */
	public function recentAttempts(string $key, int $limit = 100): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('login_name', 'ip', 'user_agent', 'reason', 'occurred')
			->from(self::TABLE)
			->orderBy('occurred', 'DESC')
			->setMaxResults($limit);
		$this->whereKey($qb, $key);

		$out = [];
		foreach (self::rows($qb->executeQuery()) as $r) {
			$ua = self::str($r['user_agent'] ?? null);
			$out[] = [
				'loginName' => self::str($r['login_name'] ?? null),
				'ip' => self::str($r['ip'] ?? null),
				'userAgent' => $ua,
				'client' => self::clientType($ua),
				'reason' => self::str($r['reason'] ?? null),
				'occurred' => self::int($r['occurred'] ?? 0),
			];
		}
		return $out;
	}

	/**
	 * Distinct IPs of one account since $sinceTs, newest first.
	 *
	 * @return array<string, array{count: int, last: int}>
	 */
	public function ipsForKey(string $key, int $sinceTs): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('ip')
			->selectAlias($qb->func()->count('*'), 'cnt')
			->selectAlias($qb->func()->max('occurred'), 'last_occ')
			->from(self::TABLE)
			->where($qb->expr()->gte('occurred', $qb->createNamedParameter($sinceTs, IQueryBuilder::PARAM_INT)))
			->groupBy('ip')
			->orderBy('last_occ', 'DESC');
		$this->whereKey($qb, $key);

		$ips = [];
		foreach (self::rows($qb->executeQuery()) as $r) {
			$ip = self::str($r['ip'] ?? null);
			if ($ip !== '') {
				$ips[$ip] = ['count' => self::int($r['cnt'] ?? 0), 'last' => self::int($r['last_occ'] ?? 0)];
			}
		}
		return $ips;
	}

	/** @return list<array{label: string, count: int}> */
	private function groupCount(string $column, int $sinceTs, ?int $limit = null, bool $orderByCount = true): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select($column)
			->selectAlias($qb->func()->count('*'), 'cnt')
			->from(self::TABLE)
			->where($qb->expr()->gte('occurred', $qb->createNamedParameter($sinceTs, IQueryBuilder::PARAM_INT)))
			->groupBy($column)
			->orderBy($orderByCount ? 'cnt' : $column, $orderByCount ? 'DESC' : 'ASC');
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}

		$out = [];
		foreach (self::rows($qb->executeQuery()) as $r) {
			$out[] = ['label' => self::str($r[$column] ?? null), 'count' => self::int($r['cnt'] ?? 0)];
		}
		return $out;
	}

	public function countSince(int $sinceTs, ?string $distinctColumn = null): int {
		$qb = $this->db->getQueryBuilder();
		$expr = $distinctColumn === null
			? $qb->func()->count('*')
			: $qb->createFunction('COUNT(DISTINCT ' . $qb->getColumnName($distinctColumn) . ')');
		$qb->select($expr)
			->from(self::TABLE)
			->where($qb->expr()->gte('occurred', $qb->createNamedParameter($sinceTs, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$value = self::int($result->fetchOne());
		$result->closeCursor();
		return $value;
	}

	/** @return list<string> distinct IPs that failed since $sinceTs */
	public function ipsSince(int $sinceTs, int $limit = 1000): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('ip')
			->from(self::TABLE)
			->where($qb->expr()->gte('occurred', $qb->createNamedParameter($sinceTs, IQueryBuilder::PARAM_INT)))
			->setMaxResults($limit);

		$ips = [];
		foreach (self::rows($qb->executeQuery()) as $r) {
			$ip = self::str($r['ip'] ?? null);
			if ($ip !== '') {
				$ips[] = $ip;
			}
		}
		return $ips;
	}

	/**
	 * @return array{days: int, perDay: list<array{day: string, count: int}>, reasons: list<array{reason: string, count: int}>, topIps: list<array{ip: string, count: int}>, topAccounts: list<AccountAgg>, totals: array{h24: int, d7: int, range: int, accounts: int, ips: int}}
	 */
	public function stats(int $days): array {
		$now = $this->now();
		$since = $now - $days * 86400;

		$perDayRaw = [];
		foreach ($this->groupCount('day', $since, null, false) as $r) {
			$perDayRaw[(int)$r['label']] = $r['count'];
		}
		$perDay = [];
		$tz = $this->timezone();
		$d = (new \DateTimeImmutable('@' . $since))->setTimezone($tz)->setTime(0, 0);
		$end = (new \DateTimeImmutable('@' . $now))->setTimezone($tz);
		while ($d <= $end) {
			$perDay[] = ['day' => $d->format('Y-m-d'), 'count' => $perDayRaw[(int)$d->format('Ymd')] ?? 0];
			$d = $d->modify('+1 day');
		}

		$accounts = $this->aggregateByAccount($since, '', 1000);
		uasort($accounts, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

		return [
			'days' => $days,
			'perDay' => $perDay,
			'reasons' => array_map(static fn (array $r): array => ['reason' => $r['label'], 'count' => $r['count']], $this->groupCount('reason', $since)),
			'topIps' => array_map(static fn (array $r): array => ['ip' => $r['label'], 'count' => $r['count']], $this->groupCount('ip', $since, 10)),
			'topAccounts' => array_slice(array_values($accounts), 0, 10),
			'totals' => [
				'h24' => $this->countSince($now - 86400),
				'd7' => $this->countSince($now - 7 * 86400),
				'range' => $this->countSince($since),
				'accounts' => count($accounts),
				'ips' => $this->countSince($since, 'ip'),
			],
		];
	}

	public function deleteOlderThan(int $ts): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE)
			->where($qb->expr()->lt('occurred', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * Rough client classification from the User-Agent (translated in the frontend).
	 *
	 * @return 'unknown'|'desktop'|'android'|'ios'|'dav'|'office'|'browser'|'script'|'other'
	 */
	public static function clientType(string $ua): string {
		$u = strtolower($ua);
		return match (true) {
			$u === '' => 'unknown',
			str_contains($u, 'mirall') || str_contains($u, 'nextcloud-desktop') => 'desktop',
			str_contains($u, 'nextcloud-android') => 'android',
			str_contains($u, 'nextcloud-ios') => 'ios',
			str_contains($u, 'dav') || str_contains($u, 'thunderbird') || str_contains($u, 'dataaccessd') => 'dav',
			str_contains($u, 'outlook') || str_contains($u, 'microsoft') => 'office',
			str_contains($u, 'curl') || str_contains($u, 'python') || str_contains($u, 'go-http') || str_contains($u, 'wget') => 'script',
			str_contains($u, 'mozilla') => 'browser',
			default => 'other',
		};
	}
}
