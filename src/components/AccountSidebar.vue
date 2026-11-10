<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { showConfirmation, showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { computed, ref, watch } from 'vue'
import IconAccountCheck from 'vue-material-design-icons/AccountCheck.vue'
import IconAccountQuestion from 'vue-material-design-icons/AccountQuestion.vue'
import IconHistory from 'vue-material-design-icons/History.vue'
import IconIpNetwork from 'vue-material-design-icons/IpNetwork.vue'
import IconLockOpen from 'vue-material-design-icons/LockOpenVariant.vue'
import StateChip from './StateChip.vue'
import { type Account, type AccountIp, api, type Attempt, errorMessage } from '../api.ts'
import { APP_ID, clientLabel, failureReason, formatDateTime, ipState, reasonText, relativeTime, seconds } from '../utils.ts'

const props = defineProps<{ account: Account, canEnable: boolean, busy: boolean }>()
const emit = defineEmits<{
	(e: 'close'): void
	(e: 'unblock'): void
	(e: 'enable'): void
	(e: 'changed'): void
}>()

const loading = ref(false)
const ips = ref<AccountIp[]>([])
const attempts = ref<Attempt[]>([])
const activeTab = ref('ips')
const ipBusy = ref<Record<string, boolean>>({})

const subname = computed(() => {
	const parts: string[] = []
	if (props.account.uid && props.account.uid !== props.account.displayName) {
		parts.push(props.account.uid)
	}
	parts.push(n(APP_ID, '%n failed login', '%n failed logins', props.account.total))
	return parts.join(' · ')
})

const isActive = computed(() => props.account.state === 'blocked' || props.account.state === 'throttled')

async function loadDetails() {
	loading.value = true
	try {
		const d = await api.account(props.account.key)
		ips.value = d.ips
		attempts.value = d.attempts
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
}

watch(() => [props.account.key, props.account.lastAttempt, props.account.state], loadDetails, { immediate: true })

async function unblockIp(ip: string) {
	let confirmed = false
	try {
		confirmed = await showConfirmation({
			name: t(APP_ID, 'Unblock {ip}?', { ip }),
			text: t(APP_ID, 'This affects every user connecting from this address.'),
			labelConfirm: t(APP_ID, 'Unblock'),
			labelReject: t(APP_ID, 'Cancel'),
			severity: 'warning',
		})
	} catch {
		return
	}
	if (!confirmed) {
		return
	}
	ipBusy.value[ip] = true
	try {
		await api.unblockIp(ip)
		showSuccess(t(APP_ID, 'IP address {ip} unblocked', { ip }))
		emit('changed')
		await loadDetails()
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		ipBusy.value[ip] = false
	}
}
</script>

<template>
	<NcAppSidebar v-model:active="activeTab"
		:name="account.displayName"
		:subname="subname"
		@close="emit('close')">
		<template #tertiary-actions>
			<NcAvatar v-if="account.exists && account.uid"
				class="no-shrink"
				:user="account.uid"
				:display-name="account.displayName"
				:size="44"
				disable-menu />
			<div v-else class="unknown-avatar">
				<IconAccountQuestion :size="24" />
			</div>
		</template>

		<template #description>
			<div class="summary">
				<div class="summary__state">
					<StateChip :state="account.state" />
					<span v-if="!account.exists" class="summary__muted">{{ t(APP_ID, 'This login name does not exist') }}</span>
					<span v-else class="summary__muted">
						{{ t(APP_ID, 'Last successful login: {date}', { date: account.lastLogin ? formatDateTime(account.lastLogin) : t(APP_ID, 'never') }) }}
					</span>
				</div>

				<NcNoteCard v-for="(r, i) in account.reasons"
					:key="i"
					:type="r.type === 'ip_blocked' ? 'error' : (r.type === 'ip_throttled' ? 'warning' : 'info')"
					class="summary__note">
					{{ reasonText(r) }}
				</NcNoteCard>

				<div v-if="account.loginNames.length > 1 || (account.loginNames[0] && account.loginNames[0] !== account.uid)" class="summary__logins">
					<span class="summary__muted">{{ t(APP_ID, 'Used login names') }}:</span>
					<code v-for="name in account.loginNames" :key="name">{{ name }}</code>
				</div>

				<div class="summary__actions">
					<NcButton v-if="isActive"
						variant="primary"
						:disabled="busy"
						@click="emit('unblock')">
						<template #icon>
							<NcLoadingIcon v-if="busy" :size="20" />
							<IconLockOpen v-else :size="20" />
						</template>
						{{ t(APP_ID, 'Unblock account') }}
					</NcButton>
					<NcButton v-if="canEnable && account.exists && account.enabled === false"
						variant="secondary"
						:disabled="busy"
						@click="emit('enable')">
						<template #icon>
							<IconAccountCheck :size="20" />
						</template>
						{{ t(APP_ID, 'Enable account') }}
					</NcButton>
				</div>
			</div>
		</template>

		<NcAppSidebarTab id="ips" :name="t(APP_ID, 'IP addresses')" :order="1">
			<template #icon>
				<IconIpNetwork :size="20" />
			</template>
			<div v-if="loading" class="center">
				<NcLoadingIcon :size="32" />
			</div>
			<NcEmptyContent v-else-if="ips.length === 0" :name="t(APP_ID, 'No IP addresses in the last 30 days')" />
			<ul v-else class="ip-list">
				<li v-for="ip in ips" :key="ip.ip" class="ip-list__item">
					<div class="ip-list__head">
						<code>{{ ip.ip }}</code>
						<span v-if="ip.bypass" class="tag">{{ t(APP_ID, 'Allowlisted') }}</span>
						<StateChip v-else :state="ipState(ip)" />
					</div>
					<dl class="ip-list__facts">
						<dt>{{ t(APP_ID, 'Last 30 minutes') }}</dt>
						<dd>{{ ip.attempts30m }}</dd>
						<dt>{{ t(APP_ID, 'Last 12 hours') }}</dt>
						<dd>{{ ip.attempts12h }}</dd>
						<dt>{{ t(APP_ID, 'Delay per login') }}</dt>
						<dd>{{ seconds(ip.delayMs) }} s</dd>
						<dt>{{ t(APP_ID, 'Failures for this account (30 days)') }}</dt>
						<dd>{{ ip.count30d }}</dd>
						<dt>{{ t(APP_ID, 'Last failure') }}</dt>
						<dd>{{ relativeTime(ip.last) }}</dd>
					</dl>
					<NcButton v-if="ip.blocked || ip.throttled"
						variant="secondary"
						size="small"
						:disabled="ipBusy[ip.ip]"
						@click="unblockIp(ip.ip)">
						<template #icon>
							<NcLoadingIcon v-if="ipBusy[ip.ip]" :size="18" />
							<IconLockOpen v-else :size="18" />
						</template>
						{{ t(APP_ID, 'Unblock IP address') }}
					</NcButton>
				</li>
			</ul>
			<p class="hint">
				{{ t(APP_ID, 'Nextcloud throttles per IP address (IPv6: per /64 network), not per account.') }}
			</p>
		</NcAppSidebarTab>

		<NcAppSidebarTab id="attempts" :name="t(APP_ID, 'Failed logins')" :order="2">
			<template #icon>
				<IconHistory :size="20" />
			</template>
			<div v-if="loading" class="center">
				<NcLoadingIcon :size="32" />
			</div>
			<NcEmptyContent v-else-if="attempts.length === 0" :name="t(APP_ID, 'No failed logins')" />
			<ol v-else class="timeline">
				<li v-for="(a, i) in attempts" :key="i" class="timeline__item">
					<div class="timeline__dot" :class="`timeline__dot--${a.reason}`" />
					<div class="timeline__body">
						<div class="timeline__title">
							{{ failureReason(a.reason) }}
						</div>
						<div class="timeline__meta">
							{{ formatDateTime(a.occurred) }} · <code>{{ a.ip }}</code>
						</div>
						<div class="timeline__meta" :title="a.userAgent">
							{{ clientLabel(a.client) }}<template v-if="a.loginName && a.loginName !== account.uid">
								· {{ t(APP_ID, 'as {name}', { name: a.loginName }) }}
							</template>
						</div>
					</div>
				</li>
			</ol>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<style scoped>
.no-shrink {
	flex-shrink: 0;
}

.unknown-avatar {
	width: 44px;
	flex-shrink: 0;
	height: 44px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--color-background-darker);
	color: var(--color-text-maxcontrast);
}

.summary {
	display: flex;
	flex-direction: column;
	gap: 10px;
	padding: 0 10px 8px;
}

.summary__state {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8px 12px;
}

.summary__muted {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.summary__note {
	margin: 0 !important;
}

.summary__logins {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	align-items: center;
}

.summary__logins code,
.ip-list code,
.timeline code {
	font-family: var(--font-face-monospace, monospace);
	font-size: 13px;
}

.summary__logins code {
	padding: 1px 6px;
	border-radius: var(--border-radius-small, 4px);
	background: var(--color-background-dark);
}

.summary__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.center {
	display: flex;
	justify-content: center;
	padding: 24px;
}

.ip-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.ip-list__item {
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-element, var(--border-radius-large));
}

.ip-list__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	font-weight: 600;
}

.ip-list__facts {
	display: grid;
	grid-template-columns: 1fr auto;
	gap: 2px 12px;
	margin: 10px 0;
	font-size: var(--font-size-small, 13px);
}

.ip-list__facts dt {
	color: var(--color-text-maxcontrast);
}

.ip-list__facts dd {
	text-align: end;
	font-variant-numeric: tabular-nums;
	font-weight: 600;
}

.tag {
	font-size: 12px;
	padding: 1px 8px;
	border-radius: var(--border-radius-pill);
	background: var(--color-background-darker);
}

.hint {
	margin-top: 12px;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.timeline {
	position: relative;
	padding-inline-start: 4px;
}

.timeline__item {
	position: relative;
	display: flex;
	gap: 12px;
	padding-bottom: 14px;
}

.timeline__item:not(:last-child)::before {
	content: '';
	position: absolute;
	inset-inline-start: 5px;
	top: 14px;
	bottom: 0;
	width: 2px;
	background: var(--color-border);
}

.timeline__dot {
	width: 12px;
	height: 12px;
	margin-top: 4px;
	border-radius: 50%;
	flex-shrink: 0;
	background: var(--color-text-maxcontrast);
}

.timeline__dot--wrong_password {
	background: var(--color-warning-text);
}

.timeline__dot--unknown_user {
	background: var(--color-error-text);
}

.timeline__dot--twofactor_failed {
	background: var(--color-primary-element);
}

.timeline__title {
	font-weight: 600;
}

.timeline__meta {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}
</style>
