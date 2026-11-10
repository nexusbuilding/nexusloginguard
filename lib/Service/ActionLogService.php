<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Audit trail of unblock / enable actions done through the app.
 *
 * @psalm-type AuditRow = array{actor: string, action: string, target: string, details: string, occurred: int}
 * @psalm-api
 */
class ActionLogService {
	public const TABLE = 'nexuslg_actions';

	public const ACTION_UNBLOCK_ACCOUNT = 'unblock_account';
	public const ACTION_UNBLOCK_IP = 'unblock_ip';
	public const ACTION_ENABLE_ACCOUNT = 'enable_account';

	public function __construct(
		private IDBConnection $db,
		private ITimeFactory $time,
		private LoggerInterface $logger,
	) {
	}

	public function add(string $actor, string $action, string $target, string $details = ''): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'actor' => $qb->createNamedParameter(mb_substr($actor, 0, 64)),
			'action' => $qb->createNamedParameter($action),
			'target' => $qb->createNamedParameter(mb_substr($target, 0, 255)),
			'details' => $qb->createNamedParameter($details === '' ? null : mb_substr($details, 0, 1024)),
			'occurred' => $qb->createNamedParameter($this->time->getTime(), IQueryBuilder::PARAM_INT),
		])->executeStatement();

		// Also visible in nextcloud.log / admin_audit pipelines
		$this->logger->warning(sprintf('nexusloginguard: %s by %s on %s %s', $action, $actor, $target, $details), ['app' => 'nexusloginguard']);
	}

	/** @return list<AuditRow> */
	public function latest(int $limit = 100): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('actor', 'action', 'target', 'details', 'occurred')
			->from(self::TABLE)
			->orderBy('occurred', 'DESC')
			->setMaxResults($limit);
		$res = $qb->executeQuery();
		/** @var list<array<string, string|int|null>> $raw */
		$raw = $res->fetchAll();
		$res->closeCursor();
		$rows = [];
		foreach ($raw as $r) {
			$rows[] = [
				'actor' => (string)($r['actor'] ?? ''),
				'action' => (string)($r['action'] ?? ''),
				'target' => (string)($r['target'] ?? ''),
				'details' => (string)($r['details'] ?? ''),
				'occurred' => (int)($r['occurred'] ?? 0),
			];
		}
		return $rows;
	}

	public function deleteOlderThan(int $ts): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE)
			->where($qb->expr()->lt('occurred', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
