<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts" generic="T extends { count: number }">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import { APP_ID, formatNumber } from '../utils.ts'

const props = defineProps<{ items: T[] }>()
const max = computed(() => Math.max(1, ...props.items.map(i => i.count)))
</script>

<template>
	<ul v-if="items.length" class="bar-list">
		<li v-for="(item, idx) in items" :key="idx" class="bar-list__item">
			<div class="bar-list__head">
				<div class="bar-list__label">
					<slot name="label" :item="item" />
				</div>
				<div class="bar-list__value">
					{{ formatNumber(item.count) }}
				</div>
				<div v-if="$slots.action" class="bar-list__action">
					<slot name="action" :item="item" />
				</div>
			</div>
			<div class="bar-list__track">
				<div class="bar-list__fill" :style="{ width: `${(item.count / max) * 100}%` }" />
			</div>
		</li>
	</ul>
	<p v-else class="bar-list__empty">
		{{ t(APP_ID, 'No data') }}
	</p>
</template>

<style scoped>
.bar-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.bar-list__head {
	display: flex;
	align-items: center;
	gap: 8px;
	min-height: 28px;
}

.bar-list__label {
	flex: 1;
	min-width: 0;
	display: flex;
	align-items: center;
	gap: 8px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.bar-list__value {
	font-weight: 600;
	font-variant-numeric: tabular-nums;
}

.bar-list__track {
	height: 6px;
	border-radius: 3px;
	background: var(--color-background-dark);
	overflow: hidden;
	margin-top: 4px;
}

.bar-list__fill {
	height: 100%;
	border-radius: 3px;
	background: var(--color-primary-element);
	transition: width .3s ease;
}

.bar-list__empty {
	color: var(--color-text-maxcontrast);
}
</style>
