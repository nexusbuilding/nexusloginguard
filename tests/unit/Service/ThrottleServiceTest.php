<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Tests\Unit\Service;

use OCA\NexusLoginGuard\Service\PolicyService;
use OCA\NexusLoginGuard\Service\ThrottleService;
use PHPUnit\Framework\TestCase;

final class ThrottleServiceTest extends TestCase {
	public function testIpStateOk(): void {
		$s = ThrottleService::buildIpState('192.0.2.1', 0, 0, 0, false, 10);
		$this->assertFalse($s['blocked']);
		$this->assertFalse($s['throttled']);
	}

	public function testIpStateThrottled(): void {
		$s = ThrottleService::buildIpState('192.0.2.1', 5, 5, 3200, false, 10);
		$this->assertFalse($s['blocked']);
		$this->assertTrue($s['throttled']);
	}

	public function testIpStateBlockedNeedsBothWindows(): void {
		// Many attempts in 12 h but only a few in the last 30 min → throttled, not blocked
		$s = ThrottleService::buildIpState('192.0.2.1', 15, 3, 25000, false, 10);
		$this->assertFalse($s['blocked']);
		$this->assertTrue($s['throttled']);

		$s = ThrottleService::buildIpState('192.0.2.1', 15, 11, 25000, false, 10);
		$this->assertTrue($s['blocked']);
		$this->assertFalse($s['throttled']);
	}

	public function testSummarizePriority(): void {
		$blocked = ThrottleService::buildIpState('192.0.2.1', 15, 11, 25000, false, 10);
		$throttled = ThrottleService::buildIpState('192.0.2.2', 3, 3, 800, false, 10);

		$r = ThrottleService::summarize([$throttled, $blocked], true, 10);
		$this->assertSame(ThrottleService::STATE_BLOCKED, $r['state']);
		$this->assertSame(['ip_blocked', 'ip_throttled', 'account_disabled'], array_column($r['reasons'], 'type'));

		$r = ThrottleService::summarize([], true, 10);
		$this->assertSame(ThrottleService::STATE_DISABLED, $r['state']);

		$r = ThrottleService::summarize([], false, 10);
		$this->assertSame(ThrottleService::STATE_OK, $r['state']);
		$this->assertSame([], $r['reasons']);
	}

	public function testPolicyLockHasHighestPriority(): void {
		$blocked = ThrottleService::buildIpState('192.0.2.1', 15, 11, 25000, false, 10);
		$info = ['reason' => PolicyService::REASON_POLICY, 'by' => 'password_policy', 'at' => 1700000000, 'attempts' => 5];

		$r = ThrottleService::summarize([$blocked], true, 10, $info, 0, 5);
		$this->assertSame(ThrottleService::STATE_LOCKED, $r['state']);
		$this->assertSame('account_locked', $r['reasons'][0]['type']);
		$this->assertSame(5, $r['reasons'][0]['attempts']);
	}

	public function testManuallyDisabledIsNotLocked(): void {
		$info = ['reason' => PolicyService::REASON_MANUAL, 'by' => 'admin', 'at' => 1700000000, 'attempts' => 5];
		$r = ThrottleService::summarize([], true, 10, $info, 0, 5);
		$this->assertSame(ThrottleService::STATE_DISABLED, $r['state']);
		$this->assertSame('manual', $r['reasons'][0]['cause'] ?? null);
	}

	public function testPolicyCounterIsReported(): void {
		$r = ThrottleService::summarize([], false, 10, null, 3, 5);
		$this->assertSame(ThrottleService::STATE_OK, $r['state']);
		$this->assertSame(['type' => 'policy_counter', 'count' => 3, 'max' => 5], $r['reasons'][0]);

		// No counter reason when the policy limit is disabled
		$r = ThrottleService::summarize([], false, 10, null, 3, 0);
		$this->assertSame([], $r['reasons']);
	}
}
