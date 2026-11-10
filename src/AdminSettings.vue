<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcSettingsSelectGroup from '@nextcloud/vue/components/NcSettingsSelectGroup'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { ref } from 'vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import { api, errorMessage } from './api.ts'
import { APP_ID } from './utils.ts'
import '@nextcloud/dialogs/style.css'

const initial = loadState<{ allowedGroups: string[], managersCanEnable: boolean, retentionDays: number }>(APP_ID, 'admin-config', {
	allowedGroups: [],
	managersCanEnable: false,
	retentionDays: 90,
})

const groups = ref<string[]>(initial.allowedGroups)
const managersCanEnable = ref(initial.managersCanEnable)
const retentionDays = ref<string | number>(initial.retentionDays)
const saving = ref(false)

const appUrl = generateUrl('/apps/nexusloginguard/')

async function save() {
	saving.value = true
	try {
		const res = await api.saveSettings({
			groups: groups.value,
			managersCanEnable: managersCanEnable.value,
			retentionDays: Math.max(1, Number(retentionDays.value) || 90),
		})
		groups.value = res.allowedGroups
		managersCanEnable.value = res.managersCanEnable
		retentionDays.value = res.retentionDays
		showSuccess(t(APP_ID, 'Settings saved'))
	} catch (e) {
		showError(t(APP_ID, 'Could not save settings: {error}', { error: errorMessage(e) }))
	} finally {
		saving.value = false
	}
}
</script>

<template>
	<NcSettingsSection :name="t(APP_ID, 'Login Guard')"
		:description="t(APP_ID, 'Administrators always have access. Additionally, members of the groups below can view failed logins and lift brute-force blocks.')">
		<div class="lg-settings">
			<div class="lg-settings__field">
				<label class="lg-settings__label" for="lg-settings-groups">{{ t(APP_ID, 'Groups with access') }}</label>
				<NcSettingsSelectGroup id="lg-settings-groups"
					v-model="groups"
					class="lg-settings__groups"
					:label="t(APP_ID, 'Groups with access')"
					:placeholder="t(APP_ID, 'Select groups …')" />
			</div>

			<NcCheckboxRadioSwitch v-model="managersCanEnable" type="switch">
				{{ t(APP_ID, 'These groups may re-enable disabled user accounts (never administrator accounts)') }}
			</NcCheckboxRadioSwitch>

			<NcTextField v-model="retentionDays"
				class="lg-settings__retention"
				type="number"
				min="1"
				max="3650"
				:label="t(APP_ID, 'Keep failed logins for (days)')"
				:helper-text="t(APP_ID, 'Older entries are removed automatically by a background job.')" />

			<div class="lg-settings__actions">
				<NcButton variant="primary" :disabled="saving" @click="save">
					<template #icon>
						<NcLoadingIcon v-if="saving" :size="20" />
						<IconCheck v-else :size="20" />
					</template>
					{{ t(APP_ID, 'Save') }}
				</NcButton>
				<NcButton variant="tertiary" :href="appUrl">
					{{ t(APP_ID, 'Open Login Guard') }}
				</NcButton>
			</div>
		</div>
	</NcSettingsSection>
</template>

<style scoped>
.lg-settings {
	display: flex;
	flex-direction: column;
	gap: 16px;
	max-width: 560px;
}

.lg-settings__field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.lg-settings__label {
	font-weight: 600;
}

.lg-settings__groups {
	width: 100%;
}

.lg-settings__retention {
	max-width: 380px;
}

.lg-settings__actions {
	display: flex;
	gap: 8px;
}
</style>
