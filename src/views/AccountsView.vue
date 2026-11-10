<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { showConfirmation, showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import IconAccountCheck from 'vue-material-design-icons/AccountCheck.vue'
import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import IconAccountQuestion from 'vue-material-design-icons/AccountQuestion.vue'
import IconAlertOctagon from 'vue-material-design-icons/AlertOctagon.vue'
import IconCounter from 'vue-material-design-icons/Counter.vue'
import IconInformation from 'vue-material-design-icons/InformationOutline.vue'
import IconLock from 'vue-material-design-icons/AccountLock.vue'
import IconLockOpen from 'vue-material-design-icons/LockOpenVariant.vue'
import IconMagnify from 'vue-material-design-icons/Magnify.vue'
import IconRefresh from 'vue-material-design-icons/Refresh.vue'
import IconShieldCheck from 'vue-material-design-icons/ShieldCheckOutline.vue'
import IconTimerSand from 'vue-material-design-icons/TimerSand.vue'
import StatTile from '../components/StatTile.vue'
import StateChip from '../components/StateChip.vue'
import ViewHeader from '../components/ViewHeader.vue'
import { type Account, api, errorMessage } from '../api.ts'
import { APP_ID, formatDateTime, formatNumber, reasonText, relativeTime } from '../utils.ts'

const props = defineProps<{ canEnable: boolean, policyMax: number }>()
const emit = defineEmits<{
	(e: 'update:blocked', count: number): void
	(e: 'checkIp', ip: string): void
	(e: 'select', account: Account | null): void
}>()

type Option = { id: string, label: string }

const periodOptions: Option[] = [
	{ id: '1', label: t(APP_ID, 'Last 24 hours') },
	{ id: '7', label: t(APP_ID, 'Last 7 days') },
	{ id: '30', label: t(APP_ID, 'Last 30 days') },
	{ id: '90', label: t(APP_ID, 'Last 90 days') },
]
const filterOptions: Option[] = [
	{ id: 'all', label: t(APP_ID, 'All') },
	{ id: 'active', label: t(APP_ID, 'Needs attention') },
	{ id: 'locked', label: t(APP_ID, 'Locked accounts') },
	{ id: 'blocked', label: t(APP_ID, 'Blocked IPs') },
	{ id: 'unknown', label: t(APP_ID, 'Unknown login names') },
]

const period = ref<Option>(periodOptions[1]!)
const filter = ref('all')
const search = ref('')
const autoRefresh = ref(false)
const loading = ref(true)
const refreshing = ref(false)
const accounts = ref<Account[]>([])
const selectedKey = defineModel<string | null>('selected', { default: null })
const busy = ref<Record<string, boolean>>({})

let timer: number | undefined
let searchTimer: number | undefined

const selected = computed(() => accounts.value.find(a => a.key === selectedKey.value) ?? null)
watch(selected, account => emit('select', account), { immediate: true })

const counts = computed(() => ({
	locked: accounts.value.filter(a => a.state === 'locked').length,
	blocked: accounts.value.filter(a => a.state === 'blocked').length,
	throttled: accounts.value.filter(a => a.state === 'throttled').length,
	unknown: accounts.value.filter(a => !a.exists).length,
	h24: accounts.value.reduce((sum, a) => sum + a.c24, 0),
}))

const filtered = computed(() => accounts.value.filter((a) => {
	switch (filter.value) {
	case 'active': return a.state === 'locked' || a.state === 'blocked' || a.state === 'throttled'
	case 'locked': return a.state === 'locked'
	case 'blocked': return a.state === 'blocked'
	case 'unknown': return !a.exists
	default: return true
	}
}))

async function load(silent = false) {
	if (silent) {
		refreshing.value = true
	} else {
		loading.value = true
	}
	try {
		accounts.value = await api.accounts(Number(period.value.id), search.value.trim())
		emit('update:blocked', counts.value.locked + counts.value.blocked)
	} catch (e) {
		showError(t(APP_ID, 'Could not load failed logins: {error}', { error: errorMessage(e) }))
	} finally {
		loading.value = false
		refreshing.value = false
	}
}

watch(period, () => load())
watch(search, () => {
	window.clearTimeout(searchTimer)
	searchTimer = window.setTimeout(() => load(true), 350)
})
watch(autoRefresh, (on) => {
	window.clearInterval(timer)
	if (on) {
		timer = window.setInterval(() => load(true), 30000)
	}
})

onMounted(() => load())
onBeforeUnmount(() => {
	window.clearInterval(timer)
	window.clearTimeout(searchTimer)
})

function subline(a: Account): string {
	const names = a.loginNames.filter(name => name !== a.uid && name !== a.displayName)
	return [a.uid && a.uid !== a.displayName ? a.uid : '', ...names].filter(Boolean).join(' · ')
}

async function unblock(a: Account) {
	let confirmed = false
	try {
		confirmed = await showConfirmation({
			name: t(APP_ID, 'Unblock {name}?', { name: a.displayName }),
			text: t(APP_ID, 'All IP addresses this account failed from during the last 48 hours are reset. Note that Nextcloud blocks IP addresses, so this also unblocks other users behind the same address.'),
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
	busy.value[a.key] = true
	try {
		const ips = await api.unblock(a.key)
		showSuccess(ips.length
			? n(APP_ID, '%n IP address unblocked', '%n IP addresses unblocked', ips.length)
			: t(APP_ID, 'No IP addresses found for the last 48 hours'))
		await load(true)
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		busy.value[a.key] = false
	}
}

async function enable(a: Account) {
	if (!a.uid) {
		return
	}
	let confirmed = false
	try {
		confirmed = await showConfirmation({
			name: t(APP_ID, 'Enable {name}?', { name: a.displayName }),
			text: t(APP_ID, 'The user account will be enabled again and the user can log in.'),
			labelConfirm: t(APP_ID, 'Enable account'),
			labelReject: t(APP_ID, 'Cancel'),
			severity: 'info',
		})
	} catch {
		return
	}
	if (!confirmed) {
		return
	}
	busy.value[a.key] = true
	try {
		await api.enable(a.uid)
		showSuccess(t(APP_ID, 'Account {name} enabled', { name: a.displayName }))
		await load(true)
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		busy.value[a.key] = false
	}
}

async function resetCounter(a: Account) {
	if (!a.uid) {
		return
	}
	busy.value[a.key] = true
	try {
		await api.resetCounter(a.uid)
		showSuccess(t(APP_ID, 'Failed login counter of {name} reset', { name: a.displayName }))
		await load(true)
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		busy.value[a.key] = false
	}
}

const isActive = (a: Account) => a.state === 'blocked' || a.state === 'throttled'
const policyPercent = (a: Account) => a.policyMax > 0 ? Math.min(100, (a.policyCount / a.policyMax) * 100) : 0

defineExpose({ unblock, enable, resetCounter, load, busy })
</script>

<template>
	<div class="accounts">
		<ViewHeader :title="t(APP_ID, 'Accounts')"
			:description="t(APP_ID, 'Failed logins per account and the live state of the brute-force protection')">
			<template #actions>
				<NcCheckboxRadioSwitch v-model="autoRefresh" type="switch">
					{{ t(APP_ID, 'Auto refresh') }}
				</NcCheckboxRadioSwitch>
				<NcButton variant="secondary" :disabled="refreshing" @click="load(true)">
					<template #icon>
						<NcLoadingIcon v-if="refreshing" :size="20" />
						<IconRefresh v-else :size="20" />
					</template>
					{{ t(APP_ID, 'Refresh') }}
				</NcButton>
			</template>
		</ViewHeader>

		<div class="accounts__tiles">
			<StatTile v-if="policyMax > 0"
				:label="t(APP_ID, 'Accounts locked by password policy')"
				:value="counts.locked"
				:hint="t(APP_ID, 'Limit: {max} consecutive failed logins', { max: String(policyMax) })"
				tone="error">
				<template #icon>
					<IconLock :size="22" />
				</template>
			</StatTile>
			<StatTile :label="t(APP_ID, 'Accounts with a blocked IP')" :value="counts.blocked" tone="error">
				<template #icon>
					<IconAlertOctagon :size="22" />
				</template>
			</StatTile>
			<StatTile :label="t(APP_ID, 'Accounts with a throttled IP')" :value="counts.throttled" tone="warning">
				<template #icon>
					<IconTimerSand :size="22" />
				</template>
			</StatTile>
			<StatTile :label="t(APP_ID, 'Accounts with failed logins')" :value="accounts.length" :hint="n(APP_ID, '%n unknown login name', '%n unknown login names', counts.unknown)">
				<template #icon>
					<IconAccountMultiple :size="22" />
				</template>
			</StatTile>
			<StatTile :label="t(APP_ID, 'Failed logins in 24 hours')" :value="counts.h24">
				<template #icon>
					<IconAccountQuestion :size="22" />
				</template>
			</StatTile>
		</div>

		<div class="accounts__toolbar">
			<NcTextField v-model="search"
				class="accounts__search"
				:label="t(APP_ID, 'Search user, login name or IP')"
				trailing-button-icon="close"
				:show-trailing-button="search !== ''"
				@trailing-button-click="search = ''">
				<template #icon>
					<IconMagnify :size="20" />
				</template>
			</NcTextField>
			<NcSelect v-model="period"
				class="accounts__period"
				:options="periodOptions"
				:clearable="false"
				:searchable="false"
				:input-label="t(APP_ID, 'Period')" />
			<div class="accounts__filters" role="group" :aria-label="t(APP_ID, 'Filter')">
				<NcCheckboxRadioSwitch v-for="opt in filterOptions"
					:key="opt.id"
					v-model="filter"
					type="radio"
					button-variant
					button-variant-grouped="horizontal"
					name="nlg-filter"
					:value="opt.id">
					{{ opt.label }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>

		<div v-if="loading" class="accounts__loading">
			<NcLoadingIcon :size="44" />
		</div>

		<NcEmptyContent v-else-if="filtered.length === 0"
			:name="accounts.length ? t(APP_ID, 'No accounts match the filter') : t(APP_ID, 'No failed logins')"
			:description="accounts.length ? '' : t(APP_ID, 'Nobody failed to log in during the selected period.')">
			<template #icon>
				<IconShieldCheck />
			</template>
		</NcEmptyContent>

		<div v-else class="accounts__table-wrap">
			<table class="accounts__table">
				<thead>
					<tr>
						<th>{{ t(APP_ID, 'Account') }}</th>
						<th>{{ t(APP_ID, 'Status') }}</th>
						<th class="num col-short">
							{{ t(APP_ID, '24 h') }}
						</th>
						<th class="num col-short">
							{{ t(APP_ID, '7 days') }}
						</th>
						<th class="num">
							{{ t(APP_ID, 'Period') }}
						</th>
						<th v-if="policyMax > 0" class="col-policy">
							{{ t(APP_ID, 'Policy') }}
						</th>
						<th class="col-time">
							{{ t(APP_ID, 'Last failure') }}
						</th>
						<th class="col-ip">
							{{ t(APP_ID, 'Last IP') }}
						</th>
						<th class="actions">
							<span class="hidden-visually">{{ t(APP_ID, 'Actions') }}</span>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="a in filtered"
						:key="a.key"
						:class="['row', `row--${a.state}`, { 'row--selected': a.key === selectedKey }]"
						tabindex="0"
						@click="selectedKey = a.key"
						@keydown.enter="selectedKey = a.key">
						<td class="cell-account">
							<NcAvatar v-if="a.exists && a.uid"
								class="cell-account__avatar"
								:user="a.uid"
								:display-name="a.displayName"
								:size="36"
								:show-user-status="false"
								disable-menu />
							<div v-else class="unknown-avatar" :title="t(APP_ID, 'This login name does not exist')">
								<IconAccountQuestion :size="20" />
							</div>
							<div class="cell-account__text">
								<div class="cell-account__name">
									{{ a.displayName }}
									<span v-if="!a.exists" class="tag">{{ t(APP_ID, 'unknown') }}</span>
								</div>
								<div v-if="a.reasons.length" class="cell-account__reason" :class="`cell-account__reason--${a.state}`">
									<IconInformation :size="16" />
									<span>{{ reasonText(a.reasons[0]!) }}</span>
								</div>
								<div v-else-if="subline(a)" class="cell-account__sub">
									{{ subline(a) }}
								</div>
							</div>
						</td>
						<td>
							<StateChip :state="a.state" />
						</td>
						<td class="num col-short">
							{{ formatNumber(a.c24) }}
						</td>
						<td class="num col-short">
							{{ formatNumber(a.c7) }}
						</td>
						<td class="num strong">
							{{ formatNumber(a.total) }}
						</td>
						<td v-if="policyMax > 0" class="col-policy">
							<div v-if="a.exists && a.state !== 'locked'"
								class="policy-meter"
								:class="{ 'policy-meter--warn': a.policyCount > 0 && a.policyCount >= a.policyMax - 1 }"
								:title="t(APP_ID, '{count} of {max} consecutive failed logins until the password policy locks the account', { count: String(a.policyCount), max: String(a.policyMax) })">
								<span class="policy-meter__text">{{ a.policyCount }} / {{ a.policyMax }}</span>
								<span class="policy-meter__track"><span class="policy-meter__fill" :style="{ width: `${policyPercent(a)}%` }" /></span>
							</div>
							<span v-else-if="a.state === 'locked'" class="policy-locked">{{ a.policyMax }} / {{ a.policyMax }}</span>
							<span v-else class="muted">–</span>
						</td>
						<td class="col-time" :title="formatDateTime(a.lastAttempt)">
							{{ relativeTime(a.lastAttempt) }}
						</td>
						<td class="col-ip">
							<code class="ip">{{ a.lastIp }}</code>
							<span v-if="a.ipCount > 1" class="more">+{{ a.ipCount - 1 }}</span>
						</td>
						<td class="actions" @click.stop>
							<NcButton v-if="a.state === 'locked' && props.canEnable"
								variant="primary"
								size="small"
								:disabled="busy[a.key]"
								@click="enable(a)">
								<template #icon>
									<NcLoadingIcon v-if="busy[a.key]" :size="18" />
									<IconLockOpen v-else :size="18" />
								</template>
								{{ t(APP_ID, 'Unlock') }}
							</NcButton>
							<NcButton v-else-if="isActive(a)"
								variant="primary"
								size="small"
								:disabled="busy[a.key]"
								@click="unblock(a)">
								<template #icon>
									<NcLoadingIcon v-if="busy[a.key]" :size="18" />
									<IconLockOpen v-else :size="18" />
								</template>
								{{ t(APP_ID, 'Unblock') }}
							</NcButton>
							<NcActions :force-name="false">
								<NcActionButton close-after-click @click="selectedKey = a.key">
									<template #icon>
										<IconInformation :size="20" />
									</template>
									{{ t(APP_ID, 'Show details') }}
								</NcActionButton>
								<NcActionButton v-if="a.lastIp" close-after-click @click="emit('checkIp', a.lastIp)">
									<template #icon>
										<IconMagnify :size="20" />
									</template>
									{{ t(APP_ID, 'Check last IP address') }}
								</NcActionButton>
								<NcActionButton v-if="isActive(a) && a.state !== 'locked'" close-after-click @click="unblock(a)">
									<template #icon>
										<IconLockOpen :size="20" />
									</template>
									{{ t(APP_ID, 'Unblock IP addresses') }}
								</NcActionButton>
								<NcActionButton v-if="a.exists && a.enabled !== false && a.policyCount > 0" close-after-click @click="resetCounter(a)">
									<template #icon>
										<IconCounter :size="20" />
									</template>
									{{ t(APP_ID, 'Reset failed login counter') }}
								</NcActionButton>
								<NcActionButton v-if="props.canEnable && a.exists && a.enabled === false" close-after-click @click="enable(a)">
									<template #icon>
										<IconAccountCheck :size="20" />
									</template>
									{{ t(APP_ID, 'Enable account') }}
								</NcActionButton>
							</NcActions>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<style scoped>
.accounts__tiles {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 12px;
	margin-bottom: 20px;
}

.accounts__toolbar {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 12px;
	margin-bottom: 16px;
}

.accounts__search {
	flex: 1 1 280px;
	max-width: 420px;
}

.accounts__period {
	width: 200px;
	margin: 0 !important;
}

.accounts__filters {
	display: flex;
}

.accounts__loading {
	display: flex;
	justify-content: center;
	padding: 64px 0;
}

.accounts__table-wrap {
	container-type: inline-size;
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-container-large, var(--border-radius-large));
	background: var(--color-main-background);
}

.accounts__table {
	width: 100%;
	border-collapse: collapse;
}

.accounts__table th {
	position: sticky;
	top: 0;
	text-align: start;
	padding: 10px;
	font-weight: 600;
	font-size: var(--font-size-small, 13px);
	color: var(--color-text-maxcontrast);
	background: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
	white-space: nowrap;
}

.accounts__table td {
	padding: 10px;
	border-bottom: 1px solid var(--color-border);
	vertical-align: middle;
}

.accounts__table tr:last-child td {
	border-bottom: none;
}

.num {
	text-align: end !important;
	font-variant-numeric: tabular-nums;
}

.strong {
	font-weight: 700;
}

.row {
	cursor: pointer;
	transition: background-color .1s ease;
}

.row:hover,
.row:focus-visible {
	background: var(--color-background-hover);
	outline: none;
}

.row--selected {
	background: var(--color-primary-element-light) !important;
}

.row--locked td:first-child,
.row--blocked td:first-child {
	box-shadow: inset 4px 0 0 var(--color-error-text);
}

.row--throttled td:first-child {
	box-shadow: inset 4px 0 0 var(--color-warning-text);
}

.cell-account {
	display: flex;
	align-items: center;
	gap: 12px;
	min-width: 260px;
}

.cell-account__avatar {
	flex-shrink: 0;
}

.col-time {
	white-space: nowrap;
}

@container (max-width: 1150px) {
	.col-short {
		display: none;
	}
}

@container (max-width: 950px) {
	.col-ip {
		display: none;
	}
}

@container (max-width: 780px) {
	.col-time {
		display: none;
	}

	.cell-account {
		min-width: 200px;
	}
}

.cell-account__name {
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 6px;
}

.cell-account__sub,
.cell-account__reason {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
	max-width: 380px;
}

.cell-account__reason {
	display: flex;
	align-items: flex-start;
	gap: 4px;
}

.cell-account__text {
	flex: 1;
	min-width: 0;
}

.cell-account__reason span {
	white-space: normal;
	overflow-wrap: break-word;
	min-width: 0;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.cell-account__reason :deep(.material-design-icon) {
	margin-top: 1px;
	flex-shrink: 0;
}

.policy-meter {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 70px;
}

.policy-meter__text {
	font-variant-numeric: tabular-nums;
	font-size: var(--font-size-small, 13px);
}

.policy-meter__track {
	height: 4px;
	border-radius: 2px;
	background: var(--color-background-dark);
	overflow: hidden;
}

.policy-meter__fill {
	display: block;
	height: 100%;
	background: var(--color-primary-element);
}

.policy-meter--warn .policy-meter__fill {
	background: var(--color-warning-text);
}

.policy-meter--warn .policy-meter__text,
.policy-locked {
	color: var(--color-error-text);
	font-weight: 600;
}

.muted {
	color: var(--color-text-maxcontrast);
}

.cell-account__reason--locked,
.cell-account__reason--blocked {
	color: var(--color-error-text, var(--color-error-text));
}

.unknown-avatar {
	width: 36px;
	height: 36px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--color-background-darker);
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
}

.tag {
	font-size: 11px;
	font-weight: 600;
	padding: 0 6px;
	border-radius: var(--border-radius-small, 4px);
	background: var(--color-background-darker);
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: .02em;
}

.ip {
	font-family: var(--font-face-monospace, monospace);
	font-size: 13px;
}

.more {
	margin-inline-start: 4px;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

td.actions {
	white-space: nowrap;
	text-align: end;
}

td.actions > * {
	display: inline-flex;
	vertical-align: middle;
}

td.actions > * + * {
	margin-inline-start: 4px;
}
</style>
