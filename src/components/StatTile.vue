<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { formatNumber } from '../utils.ts'

withDefaults(defineProps<{
	label: string
	value: number
	tone?: 'default' | 'error' | 'warning'
	hint?: string
}>(), { tone: 'default', hint: '' })
</script>

<template>
	<div class="stat-tile" :class="`stat-tile--${value > 0 ? tone : 'default'}`">
		<div class="stat-tile__icon" aria-hidden="true">
			<slot name="icon" />
		</div>
		<div class="stat-tile__body">
			<div class="stat-tile__value">
				{{ formatNumber(value) }}
			</div>
			<div class="stat-tile__label">
				{{ label }}
			</div>
			<div v-if="hint" class="stat-tile__hint">
				{{ hint }}
			</div>
		</div>
	</div>
</template>

<style scoped>
.stat-tile {
	display: flex;
	gap: 12px;
	align-items: flex-start;
	padding: 16px;
	border-radius: var(--border-radius-container-large, var(--border-radius-large));
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	min-width: 0;
}

.stat-tile__icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	border-radius: var(--border-radius-element, 12px);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	flex-shrink: 0;
}

.stat-tile--error {
	border-color: color-mix(in srgb, var(--color-error-text) 45%, var(--color-border));
}

.stat-tile--error .stat-tile__icon {
	background: color-mix(in srgb, var(--color-error-text) 14%, transparent);
	color: var(--color-error-text);
}

.stat-tile--error .stat-tile__value {
	color: var(--color-error-text);
}

.stat-tile--warning {
	border-color: color-mix(in srgb, var(--color-warning-text) 45%, var(--color-border));
}

.stat-tile--warning .stat-tile__icon {
	background: color-mix(in srgb, var(--color-warning-text) 16%, transparent);
	color: var(--color-warning-text);
}

.stat-tile--warning .stat-tile__value {
	color: var(--color-warning-text);
}

.stat-tile__value {
	font-size: 26px;
	font-weight: 700;
	line-height: 1.15;
	font-variant-numeric: tabular-nums;
}

.stat-tile__label {
	color: var(--color-text-maxcontrast);
	margin-top: 2px;
}

.stat-tile__hint {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}
</style>
