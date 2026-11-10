<!--
  - SPDX-FileCopyrightText: 2026 Yannik / Nexus Building
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
defineProps<{ title: string, description?: string }>()
</script>

<template>
	<header class="view-header">
		<div class="view-header__text">
			<h2 class="view-header__title">
				{{ title }}
			</h2>
			<p v-if="description" class="view-header__description">
				{{ description }}
			</p>
		</div>
		<div v-if="$slots.actions" class="view-header__actions">
			<slot name="actions" />
		</div>
	</header>
</template>

<style scoped>
.view-header {
	display: flex;
	align-items: flex-end;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px 24px;
	margin: 8px 0 20px;
	min-height: var(--default-clickable-area, 44px);
}

.view-header__title {
	margin: 0;
	font-size: 22px;
	font-weight: 700;
	line-height: 1.3;
}

.view-header__description {
	margin: 2px 0 0;
	color: var(--color-text-maxcontrast);
}

.view-header__actions {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}
</style>
