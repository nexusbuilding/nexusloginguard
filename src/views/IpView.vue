<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { showConfirmation, showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { onMounted, ref, watch } from 'vue'
import IconIpNetwork from 'vue-material-design-icons/IpNetwork.vue'
import IconLockOpen from 'vue-material-design-icons/LockOpenVariant.vue'
import IconMagnify from 'vue-material-design-icons/Magnify.vue'
import StateChip from '../components/StateChip.vue'
import ViewHeader from '../components/ViewHeader.vue'
import { api, errorMessage, type IpState } from '../api.ts'
import { APP_ID, ipState, seconds } from '../utils.ts'

const props = defineProps<{ initialIp?: string }>()

const ip = ref(props.initialIp ?? '')
const loading = ref(false)
const unblocking = ref(false)
const result = ref<IpState | null>(null)
const error = ref('')

async function check() {
	const value = ip.value.trim()
	if (!value) {
		return
	}
	loading.value = true
	error.value = ''
	try {
		result.value = await api.ip(value)
	} catch (e) {
		result.value = null
		error.value = errorMessage(e)
	} finally {
		loading.value = false
	}
}

async function unblock() {
	if (!result.value) {
		return
	}
	const address = result.value.ip
	let confirmed = false
	try {
		confirmed = await showConfirmation({
			name: t(APP_ID, 'Unblock {ip}?', { ip: address }),
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
	unblocking.value = true
	try {
		await api.unblockIp(address)
		showSuccess(t(APP_ID, 'IP address {ip} unblocked', { ip: address }))
		await check()
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		unblocking.value = false
	}
}

watch(() => props.initialIp, (value) => {
	if (value) {
		ip.value = value
		check()
	}
})
onMounted(() => {
	if (ip.value) {
		check()
	}
})
</script>

<template>
	<div class="ipview">
		<ViewHeader :title="t(APP_ID, 'Check IP address')"
			:description="t(APP_ID, 'Shows the live state of the brute-force protection for any address – including attempts that happened before this app was installed.')" />

		<form class="ipview__form" @submit.prevent="check">
			<NcTextField v-model="ip"
				class="ipview__input"
				:label="t(APP_ID, 'IP address')"
				:placeholder="t(APP_ID, 'e.g. 203.0.113.17 or 2001:db8::1')">
				<template #icon>
					<IconIpNetwork :size="20" />
				</template>
			</NcTextField>
			<NcButton type="submit" variant="primary" :disabled="loading || !ip.trim()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<IconMagnify v-else :size="20" />
				</template>
				{{ t(APP_ID, 'Check') }}
			</NcButton>
		</form>

		<NcNoteCard v-if="error" type="error">
			{{ error }}
		</NcNoteCard>

		<section v-if="result" class="card">
			<div class="card__head">
				<code class="card__ip">{{ result.ip }}</code>
				<span v-if="result.bypass" class="tag">{{ t(APP_ID, 'Allowlisted – never throttled') }}</span>
				<StateChip v-else :state="ipState(result)" />
			</div>
			<div class="facts">
				<div class="fact">
					<div class="fact__value">
						{{ result.attempts30m }}
					</div>
					<div class="fact__label">
						{{ t(APP_ID, 'Failed logins in 30 minutes') }}
					</div>
				</div>
				<div class="fact">
					<div class="fact__value">
						{{ result.attempts12h }}
					</div>
					<div class="fact__label">
						{{ t(APP_ID, 'Failed logins in 12 hours') }}
					</div>
				</div>
				<div class="fact">
					<div class="fact__value">
						{{ seconds(result.delayMs) }} s
					</div>
					<div class="fact__label">
						{{ t(APP_ID, 'Current delay per login') }}
					</div>
				</div>
			</div>
			<NcButton v-if="result.blocked || result.throttled"
				variant="primary"
				:disabled="unblocking"
				@click="unblock">
				<template #icon>
					<NcLoadingIcon v-if="unblocking" :size="20" />
					<IconLockOpen v-else :size="20" />
				</template>
				{{ t(APP_ID, 'Unblock IP address') }}
			</NcButton>
		</section>
	</div>
</template>

<style scoped>
.ipview__form {
	display: flex;
	align-items: flex-end;
	gap: 12px;
	margin-bottom: 16px;
	max-width: 620px;
}

.ipview__input {
	flex: 1;
}

.card {
	max-width: 620px;
	padding: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-container-large, var(--border-radius-large));
	background: var(--color-main-background);
}

.card__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.card__ip {
	font-family: var(--font-face-monospace, monospace);
	font-size: 18px;
	font-weight: 600;
}

.facts {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 12px;
	margin: 20px 0;
}

.fact {
	padding: 12px;
	border-radius: var(--border-radius-element, var(--border-radius-large));
	background: var(--color-background-hover);
}

.fact__value {
	font-size: 24px;
	font-weight: 700;
	font-variant-numeric: tabular-nums;
}

.fact__label {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.tag {
	font-size: 13px;
	padding: 2px 10px;
	border-radius: var(--border-radius-pill);
	background: var(--color-background-darker);
}
</style>
