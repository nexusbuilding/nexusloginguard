<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** @psalm-api */
class Version1000Date20261109000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('nexuslg_attempts')) {
			$t = $schema->createTable('nexuslg_attempts');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('uid', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('login_name', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$t->addColumn('ip', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => '']);
			$t->addColumn('user_agent', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('reason', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => '']);
			$t->addColumn('occurred', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'default' => 0]);
			$t->addColumn('day', Types::INTEGER, ['notnull' => true, 'unsigned' => true, 'default' => 0]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['occurred'], 'nexuslg_att_occ');
			$t->addIndex(['uid', 'occurred'], 'nexuslg_att_uid');
			$t->addIndex(['login_name'], 'nexuslg_att_login');
			$t->addIndex(['ip', 'occurred'], 'nexuslg_att_ip');
			$t->addIndex(['day'], 'nexuslg_att_day');
		}

		if (!$schema->hasTable('nexuslg_actions')) {
			$t = $schema->createTable('nexuslg_actions');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$t->addColumn('actor', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => '']);
			$t->addColumn('action', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => '']);
			$t->addColumn('target', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$t->addColumn('details', Types::STRING, ['notnull' => false, 'length' => 1024]);
			$t->addColumn('occurred', Types::BIGINT, ['notnull' => true, 'unsigned' => true, 'default' => 0]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['occurred'], 'nexuslg_act_occ');
		}

		return $schema;
	}
}
