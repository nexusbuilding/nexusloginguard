<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexusLoginGuard\Tests\Unit\Service;

use OCA\NexusLoginGuard\Service\AttemptService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttemptServiceTest extends TestCase {
	public static function userAgents(): array {
		return [
			['', 'unknown'],
			['Mozilla/5.0 (Windows NT 10.0) mirall/3.14.0 (Nextcloud, windows)', 'desktop'],
			['Mozilla/5.0 (Android) Nextcloud-android/3.30.0', 'android'],
			['Mozilla/5.0 (iOS) Nextcloud-iOS/6.0', 'ios'],
			['DAVx5/4.4 (2024/05/01; dav4jvm; okhttp/4.12.0) Android/14', 'dav'],
			['Microsoft Office/16.0 (Windows NT 10.0; Microsoft Outlook 16.0)', 'office'],
			['Mozilla/5.0 (X11; Linux x86_64) Gecko/20100101 Firefox/140.0', 'browser'],
			['curl/8.5.0', 'script'],
			['python-requests/2.32', 'script'],
			['SomethingElse/1.0', 'other'],
		];
	}

	#[DataProvider('userAgents')]
	public function testClientType(string $ua, string $expected): void {
		$this->assertSame($expected, AttemptService::clientType($ua));
	}
}
