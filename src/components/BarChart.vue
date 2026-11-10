<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import { APP_ID, formatNumber, locale } from '../utils.ts'

const props = defineProps<{ data: { day: string, count: number }[] }>()

const hovered = ref<number | null>(null)

/** Nice axis maximum that splits into four integer steps */
const max = computed(() => {
	const m = Math.max(4, ...props.data.map(d => d.count))
	const raw = m / 4
	const p = Math.pow(10, Math.floor(Math.log10(raw)))
	for (const f of [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10]) {
		const step = f * p
		if (step >= raw && Number.isInteger(step)) {
			return step * 4
		}
	}
	return Math.ceil(raw) * 4
})

const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map(f => Math.round(max.value * f)))
const labelEvery = computed(() => Math.max(1, Math.ceil(props.data.length / 8)))

function shortDate(day: string): string {
	return new Date(day + 'T12:00:00').toLocaleDateString(locale(), { day: '2-digit', month: '2-digit' })
}

function longDate(day: string): string {
	return new Date(day + 'T12:00:00').toLocaleDateString(locale(), { weekday: 'short', day: '2-digit', month: 'long' })
}

const active = computed(() => hovered.value === null ? null : props.data[hovered.value])
</script>

<template>
	<div class="bar-chart">
		<div class="bar-chart__tooltip" :class="{ visible: active }">
			<template v-if="active">
				<strong>{{ formatNumber(active.count) }}</strong> · {{ longDate(active.day) }}
			</template>
			<template v-else>
				&nbsp;
			</template>
		</div>
		<div class="bar-chart__plot" role="img" :aria-label="t(APP_ID, 'Failed logins per day')">
			<div class="bar-chart__axis" aria-hidden="true">
				<span v-for="tick in ticks.slice().reverse()" :key="tick">{{ formatNumber(tick) }}</span>
			</div>
			<div class="bar-chart__area">
				<div class="bar-chart__grid" aria-hidden="true">
					<span v-for="tick in ticks" :key="tick" />
				</div>
				<div class="bar-chart__bars">
					<div v-for="(d, i) in data"
						:key="d.day"
						class="bar-chart__col"
						@mouseenter="hovered = i"
						@mouseleave="hovered = null">
						<div class="bar-chart__bar"
							:class="{ zero: d.count === 0, hover: hovered === i }"
							:style="{ height: `${(d.count / max) * 100}%` }" />
					</div>
				</div>
				<div class="bar-chart__labels" aria-hidden="true">
					<span v-for="(d, i) in data" :key="d.day">{{ i % labelEvery === 0 ? shortDate(d.day) : '' }}</span>
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
.bar-chart__tooltip {
	min-height: 22px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 8px;
	font-variant-numeric: tabular-nums;
}

.bar-chart__tooltip strong {
	color: var(--color-main-text);
}

.bar-chart__plot {
	display: flex;
	gap: 8px;
	height: 220px;
}

.bar-chart__axis {
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	align-items: flex-end;
	padding-bottom: 22px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
	line-height: 0;
}

.bar-chart__area {
	position: relative;
	flex: 1;
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.bar-chart__grid {
	position: absolute;
	inset: 0 0 22px 0;
	display: flex;
	flex-direction: column-reverse;
	justify-content: space-between;
	pointer-events: none;
}

.bar-chart__grid span {
	border-top: 1px solid var(--color-border);
	height: 0;
}

.bar-chart__bars {
	position: relative;
	flex: 1;
	display: flex;
	align-items: flex-end;
	gap: 3px;
}

.bar-chart__col {
	flex: 1;
	height: 100%;
	display: flex;
	align-items: flex-end;
	cursor: default;
	min-width: 0;
}

.bar-chart__bar {
	width: 100%;
	min-height: 2px;
	border-radius: 4px 4px 0 0;
	background: var(--color-primary-element);
	opacity: .85;
	transition: opacity .15s ease, height .3s ease;
}

.bar-chart__bar.zero {
	background: var(--color-border-dark);
	opacity: .5;
}

.bar-chart__bar.hover {
	opacity: 1;
}

.bar-chart__labels {
	display: flex;
	gap: 3px;
	height: 22px;
	align-items: flex-end;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.bar-chart__labels span {
	flex: 1;
	min-width: 0;
	overflow: visible;
	white-space: nowrap;
}
</style>
