<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { computed } from 'vue'
import type { State } from '../api.ts'
import { stateLabel } from '../utils.ts'

const props = defineProps<{ state: State, label?: string }>()
const text = computed(() => props.label ?? stateLabel(props.state))
</script>

<template>
	<span class="state-chip" :class="`state-chip--${state}`">
		<span class="state-chip__dot" aria-hidden="true" />
		{{ text }}
	</span>
</template>

<style scoped>
.state-chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 2px 10px 2px 8px;
	border-radius: var(--border-radius-pill);
	font-size: var(--font-size-small, 13px);
	font-weight: 600;
	line-height: 20px;
	white-space: nowrap;
	background: var(--color-background-dark);
	color: var(--color-main-text);
}

.state-chip__dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: var(--color-text-maxcontrast);
	flex-shrink: 0;
}

.state-chip--blocked {
	background: color-mix(in srgb, var(--color-error-text) 14%, transparent);
	color: var(--color-error-text);
}

.state-chip--blocked .state-chip__dot {
	background: var(--color-error-text);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-error-text) 25%, transparent);
}

.state-chip--locked {
	background: var(--color-error-text);
	color: var(--color-main-background);
}

.state-chip--locked .state-chip__dot {
	background: var(--color-main-background);
}

.state-chip--throttled {
	background: color-mix(in srgb, var(--color-warning-text) 16%, transparent);
	color: var(--color-warning-text);
}

.state-chip--throttled .state-chip__dot {
	background: var(--color-warning-text);
}

.state-chip--ok {
	background: color-mix(in srgb, var(--color-success-text) 12%, transparent);
	color: var(--color-success-text);
}

.state-chip--ok .state-chip__dot {
	background: var(--color-success-text);
}
</style>
