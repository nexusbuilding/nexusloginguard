<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { onMounted, ref } from 'vue'
import IconAccountCheck from 'vue-material-design-icons/AccountCheck.vue'
import IconHistory from 'vue-material-design-icons/History.vue'
import IconLockOpen from 'vue-material-design-icons/LockOpenVariant.vue'
import ViewHeader from '../components/ViewHeader.vue'
import { api, type AuditEntry, errorMessage } from '../api.ts'
import { actionLabel, APP_ID, formatDateTime, relativeTime } from '../utils.ts'

const loading = ref(true)
const entries = ref<AuditEntry[]>([])

onMounted(async () => {
	try {
		entries.value = await api.actions()
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
})
</script>

<template>
	<div>
		<ViewHeader :title="t(APP_ID, 'Audit log')" :description="t(APP_ID, 'Every unblock and enable action performed with Login Guard')" />

		<div v-if="loading" class="center">
			<NcLoadingIcon :size="44" />
		</div>
		<NcEmptyContent v-else-if="entries.length === 0"
			:name="t(APP_ID, 'No actions yet')"
			:description="t(APP_ID, 'Unblocked accounts and IP addresses will show up here.')">
			<template #icon>
				<IconHistory />
			</template>
		</NcEmptyContent>
		<ul v-else class="audit">
			<li v-for="(e, i) in entries" :key="i" class="audit__item">
				<div class="audit__icon" :class="`audit__icon--${e.action}`">
					<IconAccountCheck v-if="e.action === 'enable_account'" :size="20" />
					<IconLockOpen v-else :size="20" />
				</div>
				<div class="audit__body">
					<div class="audit__title">
						{{ actionLabel(e.action) }}: <strong>{{ e.target }}</strong>
					</div>
					<div v-if="e.details" class="audit__details">
						{{ t(APP_ID, 'IP addresses: {ips}', { ips: e.details }) }}
					</div>
				</div>
				<div class="audit__actor">
					<NcAvatar :user="e.actor"
						:size="24"
						:show-user-status="false"
						disable-menu />
					<span>{{ e.actor }}</span>
				</div>
				<div class="audit__time" :title="formatDateTime(e.occurred)">
					{{ relativeTime(e.occurred) }}
				</div>
			</li>
		</ul>
	</div>
</template>

<style scoped>
.center {
	display: flex;
	justify-content: center;
	padding: 64px 0;
}

.audit {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-container-large, var(--border-radius-large));
	background: var(--color-main-background);
	overflow: hidden;
}

.audit__item {
	display: grid;
	grid-template-columns: auto 1fr auto 110px;
	align-items: center;
	gap: 12px;
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
}

.audit__item:last-child {
	border-bottom: none;
}

.audit__icon {
	width: 36px;
	height: 36px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.audit__icon--enable_account {
	background: color-mix(in srgb, var(--color-success-text) 14%, transparent);
	color: var(--color-success-text);
}

.audit__details {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
	font-family: var(--font-face-monospace, monospace);
}

.audit__actor {
	display: flex;
	align-items: center;
	gap: 6px;
	color: var(--color-text-maxcontrast);
}

.audit__time {
	text-align: end;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

@media (max-width: 720px) {
	.audit__item {
		grid-template-columns: auto 1fr;
	}

	.audit__actor,
	.audit__time {
		grid-column: 2;
		text-align: start;
	}
}
</style>
