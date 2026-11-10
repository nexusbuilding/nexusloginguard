/**
 * SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export type State = 'locked' | 'blocked' | 'throttled' | 'disabled' | 'ok'

export interface Reason {
	type: 'ip_blocked' | 'ip_throttled' | 'account_locked' | 'account_disabled' | 'policy_counter'
	ip?: string
	attempts?: number
	limit?: number
	delayMs?: number
	count?: number
	max?: number
	by?: string
	at?: number
	cause?: 'policy' | 'manual' | 'system'
}

export interface Account {
	key: string
	uid: string | null
	exists: boolean
	displayName: string
	loginNames: string[]
	enabled: boolean | null
	lastLogin: number
	total: number
	c24: number
	c7: number
	lastAttempt: number
	firstAttempt: number
	lastIp: string
	ipCount: number
	policyCount: number
	policyMax: number
	state: State
	reasons: Reason[]
}

export interface IpState {
	ip: string
	attempts12h: number
	attempts30m: number
	delayMs: number
	blocked: boolean
	throttled: boolean
	bypass: boolean
}

export interface AccountIp extends IpState {
	count30d: number
	last: number
}

export interface Attempt {
	loginName: string
	ip: string
	userAgent: string
	client: string
	reason: string
	occurred: number
}

export interface Meta {
	isAdmin: boolean
	canEnable: boolean
	maxAttempts: number
	protectionEnabled: boolean
	backend: 'redis' | 'database'
	retentionDays: number
	remoteAddr: string
	proxyMisconfigured: boolean
	policyMax: number
}

export interface Stats {
	days: number
	perDay: { day: string, count: number }[]
	reasons: { reason: string, count: number }[]
	topIps: { ip: string, count: number }[]
	topAccounts: { key: string, uid: string | null, displayName: string, exists: boolean, count: number }[]
	totals: { h24: number, d7: number, range: number, accounts: number, ips: number }
	live: { blockedIps: number, throttledIps: number }
}

export interface AuditEntry {
	actor: string
	action: string
	target: string
	details: string
	occurred: number
}

const url = (path: string) => generateUrl('/apps/nexusloginguard' + path)

/**
 * Extract a readable error message from an axios error
 * @param e the caught error
 */
export function errorMessage(e: unknown): string {
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	const err = e as any
	return err?.response?.data?.error ?? err?.message ?? String(e)
}

export const api = {
	async meta(): Promise<Meta> {
		return (await axios.get(url('/api/meta'))).data
	},
	async accounts(days: number, search: string): Promise<Account[]> {
		return (await axios.get(url('/api/accounts'), { params: { days, search } })).data.accounts
	},
	async account(key: string): Promise<{ ips: AccountIp[], attempts: Attempt[] }> {
		return (await axios.get(url('/api/account'), { params: { key } })).data
	},
	async stats(days: number): Promise<Stats> {
		return (await axios.get(url('/api/stats'), { params: { days } })).data
	},
	async ip(ip: string): Promise<IpState> {
		return (await axios.get(url('/api/ip'), { params: { ip } })).data
	},
	async unblock(key: string): Promise<string[]> {
		return (await axios.post(url('/api/unblock'), { key })).data.resetIps
	},
	async unblockIp(ip: string): Promise<void> {
		await axios.post(url('/api/unblock-ip'), { ip })
	},
	async enable(uid: string): Promise<void> {
		await axios.post(url('/api/enable'), { uid })
	},
	async resetCounter(uid: string): Promise<void> {
		await axios.post(url('/api/reset-counter'), { uid })
	},
	async actions(): Promise<AuditEntry[]> {
		return (await axios.get(url('/api/actions'))).data.actions
	},
	async saveSettings(settings: { groups: string[], managersCanEnable: boolean, retentionDays: number }) {
		return (await axios.put(url('/settings'), settings)).data
	},
}
