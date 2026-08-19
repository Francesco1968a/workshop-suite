<script setup>
import { computed } from 'vue';

const props = defineProps({
  pct: { type: Number, default: 0 },
  size: { type: Number, default: 90 },
});

// r = 15.915 makes the circle's circumference ~100, so the raw
// percentage can be used directly as the dash length.
const RADIUS = 15.915;
const clamped = computed(() => Math.max(0, Math.min(100, props.pct)));
const dasharray = computed(() => `${clamped.value}, 100`);
</script>

<template>
  <div class="wvr-ring" :style="{ width: size + 'px', height: size + 'px' }">
    <svg viewBox="0 0 36 36" :width="size" :height="size">
      <circle cx="18" cy="18" :r="RADIUS" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="2.4" />
      <circle
        cx="18"
        cy="18"
        :r="RADIUS"
        fill="none"
        stroke="#ff6608"
        stroke-width="2.4"
        :stroke-dasharray="dasharray"
        stroke-linecap="round"
        transform="rotate(-90 18 18)"
        style="transition: stroke-dasharray 0.8s ease"
      />
    </svg>
    <div class="wvr-ring-center">
      <slot />
    </div>
  </div>
</template>
