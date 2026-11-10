<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { onMounted, ref, watch } from 'vue'
import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import IconAccountQuestion from 'vue-material-design-icons/AccountQuestion.vue'
import IconAlertOctagon from 'vue-material-design-icons/AlertOctagon.vue'
import IconChartBar from 'vue-material-design-icons/ChartBar.vue'
import IconEarth from 'vue-material-design-icons/Earth.vue'
import IconMagnify from 'vue-material-design-icons/Magnify.vue'
import IconTimerSand from 'vue-material-design-icons/TimerSand.vue'
import BarChart from '../components/BarChart.vue'
import BarList from '../components/BarList.vue'
import StatTile from '../components/StatTile.vue'
import ViewHeader from '../components/ViewHeader.vue'
import { api, errorMessage, type Stats } from '../api.ts'
import { APP_ID, failureReason } from '../utils.ts'

const emit = defineEmits<{ (e: 'checkIp', ip: string): void }>()

const options = [
	{ id: '7', label: t(APP_ID, 'Last 7 days') },
	{ id: '30', label: t(APP_ID, 'Last 30 days') },
	{ id: '90', label: t(APP_ID, 'Last 90 days') },
]
const period = ref(options[1]!)
const loading = ref(true)
const stats = ref<Stats | null>(null)

async function load() {
	loading.value = true
	try {
		stats.value = await api.stats(Number(period.value.id))
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
}

watch(period, load)
onMounted(load)
</script>

<template>
	<div class="stats">
		<ViewHeader :title="t(APP_ID, 'Statistics')" :description="t(APP_ID, 'Trends of failed logins and the most affected accounts and addresses')">
			<template #actions>
				<NcSelect v-model="period"
					class="stats__period"
					:options="options"
					:clearable="false"
					:searchable="false"
					:input-label="t(APP_ID, 'Period')" />
			</template>
		</ViewHeader>

		<div v-if="loading && !stats" class="center">
			<NcLoadingIcon :size="44" />
		</div>

		<template v-else-if="stats">
			<div class="stats__tiles" :class="{ dimmed: loading }">
				<StatTile :label="t(APP_ID, 'Failed logins in 24 hours')" :value="stats.totals.h24">
					<template #icon>
						<IconAccountQuestion :size="22" />
					</template>
				</StatTile>
				<StatTile :label="t(APP_ID, 'Failed logins in 7 days')" :value="stats.totals.d7">
					<template #icon>
						<IconChartBar :size="22" />
					</template>
				</StatTile>
				<StatTile :label="t(APP_ID, 'Failed logins in {days} days', { days: String(stats.days) })" :value="stats.totals.range">
					<template #icon>
						<IconChartBar :size="22" />
					</template>
				</StatTile>
				<StatTile :label="t(APP_ID, 'Affected accounts')" :value="stats.totals.accounts">
					<template #icon>
						<IconAccountMultiple :size="22" />
					</template>
				</StatTile>
				<StatTile :label="t(APP_ID, 'Distinct IP addresses')" :value="stats.totals.ips">
					<template #icon>
						<IconEarth :size="22" />
					</template>
				</StatTile>
				<StatTile :label="t(APP_ID, 'IP addresses blocked now')" :value="stats.live.blockedIps" tone="error">
					<template #icon>
						<IconAlertOctagon :size="22" />
					</template>
				</StatTile>
				<StatTile :label="t(APP_ID, 'IP addresses throttled now')" :value="stats.live.throttledIps" tone="warning">
					<template #icon>
						<IconTimerSand :size="22" />
					</template>
				</StatTile>
			</div>

			<section class="card" :class="{ dimmed: loading }">
				<h3>{{ t(APP_ID, 'Failed logins per day') }}</h3>
				<BarChart :data="stats.perDay" />
			</section>

			<div class="stats__grid" :class="{ dimmed: loading }">
				<section class="card">
					<h3>{{ t(APP_ID, 'Reasons') }}</h3>
					<BarList :items="stats.reasons">
						<template #label="{ item }">
							{{ failureReason(item.reason) }}
						</template>
					</BarList>
				</section>
				<section class="card">
					<h3>{{ t(APP_ID, 'Top accounts') }}</h3>
					<BarList :items="stats.topAccounts">
						<template #label="{ item }">
							<NcAvatar v-if="item.exists && item.uid"
								:user="item.uid"
								:display-name="item.displayName"
								:size="24"
								:show-user-status="false"
								disable-menu />
							<IconAccountQuestion v-else :size="24" class="muted" />
							<span class="ellipsis">{{ item.displayName }}</span>
						</template>
					</BarList>
				</section>
				<section class="card">
					<h3>{{ t(APP_ID, 'Top IP addresses') }}</h3>
					<BarList :items="stats.topIps">
						<template #label="{ item }">
							<code>{{ item.ip }}</code>
						</template>
						<template #action="{ item }">
							<NcButton variant="tertiary"
								size="small"
								:aria-label="t(APP_ID, 'Check {ip}', { ip: item.ip })"
								:title="t(APP_ID, 'Check {ip}', { ip: item.ip })"
								@click="emit('checkIp', item.ip)">
								<template #icon>
									<IconMagnify :size="18" />
								</template>
							</NcButton>
						</template>
					</BarList>
				</section>
			</div>
		</template>
	</div>
</template>

<style scoped>
.center {
	display: flex;
	justify-content: center;
	padding: 64px 0;
}

.stats__period {
	width: 200px;
	margin: 0 !important;
}

.stats__tiles {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
	gap: 12px;
	margin-bottom: 16px;
}

.stats__grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 16px;
}

.card {
	padding: 16px 20px 20px;
	margin-bottom: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-container-large, var(--border-radius-large));
	background: var(--color-main-background);
}

.stats__grid .card {
	margin-bottom: 0;
}

.card h3 {
	margin: 0 0 12px;
	font-size: 16px;
	font-weight: 600;
}

.dimmed {
	opacity: .6;
	transition: opacity .2s ease;
}

code {
	font-family: var(--font-face-monospace, monospace);
	font-size: 13px;
}

.muted {
	color: var(--color-text-maxcontrast);
}

.ellipsis {
	overflow: hidden;
	text-overflow: ellipsis;
}
</style>
