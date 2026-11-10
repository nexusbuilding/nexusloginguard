<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { computed, onMounted, ref } from 'vue'
import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import IconChartBar from 'vue-material-design-icons/ChartBar.vue'
import IconHistory from 'vue-material-design-icons/History.vue'
import IconIpNetwork from 'vue-material-design-icons/IpNetwork.vue'
import AccountSidebar from './components/AccountSidebar.vue'
import AccountsView from './views/AccountsView.vue'
import AuditView from './views/AuditView.vue'
import IpView from './views/IpView.vue'
import StatsView from './views/StatsView.vue'
import { type Account, api, type Meta } from './api.ts'
import { APP_ID } from './utils.ts'
import '@nextcloud/dialogs/style.css'

type View = 'accounts' | 'stats' | 'ip' | 'audit'

const config = loadState<{ isAdmin: boolean, canEnable: boolean }>(APP_ID, 'config', { isAdmin: false, canEnable: false })

const view = ref<View>('accounts')
const meta = ref<Meta | null>(null)
const blockedCount = ref(0)
const ipToCheck = ref('')
const selectedKey = ref<string | null>(null)
const selectedAccount = ref<Account | null>(null)
const accountsView = ref<InstanceType<typeof AccountsView> | null>(null)
const sidebarBusy = computed(() => !!(selectedAccount.value && accountsView.value?.busy[selectedAccount.value.key]))

const isPrivateRemote = computed(() => !!meta.value?.proxyMisconfigured)

onMounted(async () => {
	try {
		meta.value = await api.meta()
	} catch {
		// non-critical
	}
})

function checkIp(ip: string) {
	ipToCheck.value = ip
	selectedKey.value = null
	view.value = 'ip'
}
</script>

<template>
	<NcContent :app-name="APP_ID">
		<NcAppNavigation :aria-label="t(APP_ID, 'Login Guard navigation')">
			<template #list>
				<NcAppNavigationItem :name="t(APP_ID, 'Accounts')" :active="view === 'accounts'" @click="view = 'accounts'">
					<template #icon>
						<IconAccountMultiple :size="20" />
					</template>
					<template v-if="blockedCount > 0" #counter>
						<NcCounterBubble :count="blockedCount" type="highlighted" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="t(APP_ID, 'Statistics')" :active="view === 'stats'" @click="view = 'stats'">
					<template #icon>
						<IconChartBar :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="t(APP_ID, 'Check IP address')" :active="view === 'ip'" @click="view = 'ip'">
					<template #icon>
						<IconIpNetwork :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="t(APP_ID, 'Audit log')" :active="view === 'audit'" @click="view = 'audit'">
					<template #icon>
						<IconHistory :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
			<template #footer>
				<div v-if="meta" class="nav-footer">
					<div class="nav-footer__row">
						<span>{{ t(APP_ID, 'Backend') }}</span>
						<strong>{{ meta.backend === 'redis' ? 'Redis' : t(APP_ID, 'Database') }}</strong>
					</div>
					<div class="nav-footer__row">
						<span>{{ t(APP_ID, 'Block limit') }}</span>
						<strong>{{ t(APP_ID, '{count} / 30 min', { count: String(meta.maxAttempts) }) }}</strong>
					</div>
					<div class="nav-footer__row">
						<span>{{ t(APP_ID, 'Password policy') }}</span>
						<strong>{{ meta.policyMax > 0 ? t(APP_ID, '{count} attempts', { count: String(meta.policyMax) }) : t(APP_ID, 'off') }}</strong>
					</div>
					<div class="nav-footer__row">
						<span>{{ t(APP_ID, 'Retention') }}</span>
						<strong>{{ t(APP_ID, '{days} days', { days: String(meta.retentionDays) }) }}</strong>
					</div>
				</div>
			</template>
		</NcAppNavigation>

		<NcAppContent>
			<div class="app-main">
				<div v-if="meta && (!meta.protectionEnabled || isPrivateRemote)" class="app-main__notes">
					<NcNoteCard v-if="!meta.protectionEnabled" type="warning" :heading="t(APP_ID, 'Brute-force protection is disabled')">
						{{ t(APP_ID, 'Failed logins are recorded, but Nextcloud does not throttle them because "auth.bruteforce.protection.enabled" is set to false.') }}
					</NcNoteCard>
					<NcNoteCard v-if="isPrivateRemote" type="warning" :heading="t(APP_ID, 'Check your reverse proxy configuration')">
						{{ t(APP_ID, 'Your request was forwarded by a reverse proxy ({ip}), but Nextcloud ignores the forwarded client address. Add the proxy to "trusted_proxies" – otherwise all failed logins are attributed to the proxy and every user gets throttled at once.', { ip: meta.remoteAddr }) }}
					</NcNoteCard>
				</div>

				<AccountsView v-if="view === 'accounts'"
					ref="accountsView"
					v-model:selected="selectedKey"
					:can-enable="config.canEnable"
					:policy-max="meta?.policyMax ?? 0"
					@select="selectedAccount = $event"
					@update:blocked="blockedCount = $event"
					@check-ip="checkIp" />
				<StatsView v-else-if="view === 'stats'" @check-ip="checkIp" />
				<IpView v-else-if="view === 'ip'" :initial-ip="ipToCheck" />
				<AuditView v-else-if="view === 'audit'" />
			</div>
		</NcAppContent>

		<AccountSidebar v-if="view === 'accounts' && selectedAccount"
			:account="selectedAccount"
			:can-enable="config.canEnable"
			:busy="sidebarBusy"
			@close="selectedKey = null"
			@unblock="accountsView?.unblock(selectedAccount)"
			@enable="accountsView?.enable(selectedAccount)"
			@reset-counter="accountsView?.resetCounter(selectedAccount)"
			@changed="accountsView?.load(true)" />
	</NcContent>
</template>

<style scoped>
.app-main {
	padding: calc(var(--default-grid-baseline, 4px) * 4) calc(var(--default-grid-baseline, 4px) * 6) 48px;
	padding-inline-start: max(calc(var(--default-grid-baseline, 4px) * 6), var(--app-navigation-padding, 0px) + 44px);
	max-width: 1600px;
}

.app-main__notes {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 8px;
}

.nav-footer {
	margin: 8px;
	padding: 10px 12px;
	border-radius: var(--border-radius-element, var(--border-radius-large));
	background: var(--color-background-dark);
	display: flex;
	flex-direction: column;
	gap: 4px;
	font-size: var(--font-size-small, 13px);
}

.nav-footer__row {
	display: flex;
	justify-content: space-between;
	gap: 8px;
	color: var(--color-text-maxcontrast);
}

.nav-footer__row strong {
	color: var(--color-main-text);
	font-weight: 600;
}
</style>
