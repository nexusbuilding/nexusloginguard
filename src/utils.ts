/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { getLanguage, n, t } from '@nextcloud/l10n'
import type { IpState, Reason, State } from './api.ts'

export const APP_ID = 'nexusloginguard'

/** BCP 47 locale for Intl APIs (Nextcloud uses de_DE style) */
export const locale = (): string => getLanguage().replace('_', '-')

export function stateLabel(state: State): string {
	switch (state) {
	case 'blocked': return t(APP_ID, 'Blocked')
	case 'throttled': return t(APP_ID, 'Throttled')
	case 'disabled': return t(APP_ID, 'Disabled')
	default: return t(APP_ID, 'OK')
	}
}

export function stateVariant(state: State): 'error' | 'warning' | 'secondary' | 'success' {
	switch (state) {
	case 'blocked': return 'error'
	case 'throttled': return 'warning'
	case 'disabled': return 'secondary'
	default: return 'success'
	}
}

export function ipState(s: IpState): State {
	return s.blocked ? 'blocked' : (s.throttled ? 'throttled' : 'ok')
}

export function seconds(ms: number): string {
	return (ms / 1000).toLocaleString(locale(), { maximumFractionDigits: 1, minimumFractionDigits: ms > 0 && ms < 10000 ? 1 : 0 })
}

export function reasonText(r: Reason): string {
	switch (r.type) {
	case 'ip_blocked':
		return t(APP_ID, 'IP {ip} is blocked (HTTP 429): {attempts} failed attempts in 30 minutes, limit is {limit}', {
			ip: r.ip ?? '', attempts: String(r.attempts ?? 0), limit: String(r.limit ?? 0),
		})
	case 'ip_throttled':
		return t(APP_ID, 'IP {ip} is throttled: {attempts} failed attempts in 12 hours, every login is delayed by {seconds} s', {
			ip: r.ip ?? '', attempts: String(r.attempts ?? 0), seconds: seconds(r.delayMs ?? 0),
		})
	case 'account_disabled':
		return t(APP_ID, 'The user account is disabled')
	}
	return ''
}

export function failureReason(reason: string): string {
	switch (reason) {
	case 'wrong_password': return t(APP_ID, 'Wrong password')
	case 'unknown_user': return t(APP_ID, 'Unknown login name')
	case 'user_disabled': return t(APP_ID, 'Account disabled')
	case 'twofactor_failed': return t(APP_ID, 'Two-factor authentication failed')
	default: return reason
	}
}

export function clientLabel(client: string): string {
	switch (client) {
	case 'desktop': return t(APP_ID, 'Desktop client')
	case 'android': return t(APP_ID, 'Android app')
	case 'ios': return t(APP_ID, 'iOS app')
	case 'dav': return t(APP_ID, 'WebDAV / CalDAV / CardDAV')
	case 'office': return t(APP_ID, 'Outlook / Office')
	case 'browser': return t(APP_ID, 'Browser')
	case 'script': return t(APP_ID, 'Script / bot')
	case 'unknown': return t(APP_ID, 'Unknown')
	default: return t(APP_ID, 'Other')
	}
}

export function actionLabel(action: string): string {
	switch (action) {
	case 'unblock_account': return t(APP_ID, 'Account unblocked')
	case 'unblock_ip': return t(APP_ID, 'IP unblocked')
	case 'enable_account': return t(APP_ID, 'Account enabled')
	default: return action
	}
}

export function formatDateTime(ts: number): string {
	if (!ts) {
		return '–'
	}
	return new Date(ts * 1000).toLocaleString(locale(), { dateStyle: 'medium', timeStyle: 'short' })
}

export function relativeTime(ts: number): string {
	if (!ts) {
		return '–'
	}
	const diff = Math.max(0, Math.floor(Date.now() / 1000) - ts)
	if (diff < 60) {
		return t(APP_ID, 'just now')
	}
	if (diff < 3600) {
		return n(APP_ID, '%n minute ago', '%n minutes ago', Math.floor(diff / 60))
	}
	if (diff < 86400) {
		return n(APP_ID, '%n hour ago', '%n hours ago', Math.floor(diff / 3600))
	}
	return n(APP_ID, '%n day ago', '%n days ago', Math.floor(diff / 86400))
}

export function formatNumber(value: number): string {
	return value.toLocaleString(locale())
}
