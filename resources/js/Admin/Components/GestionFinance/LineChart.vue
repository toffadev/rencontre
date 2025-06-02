<template>
  <div>
    <canvas ref="chartCanvas"></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import Chart from 'chart.js/auto'

const props = defineProps({
  chartData: {
    type: Object,
    required: true
  },
  options: {
    type: Object,
    default: () => ({})
  }
})

const chartCanvas = ref(null)
let chart = null

const createChart = () => {
  if (chart) {
    chart.destroy()
  }

  const ctx = chartCanvas.value.getContext('2d')
  chart = new Chart(ctx, {
    type: 'line',
    data: props.chartData,
    options: {
      ...props.options,
      plugins: {
        legend: {
          position: 'top'
        }
      }
    }
  })
}

onMounted(() => {
  createChart()
})

watch(() => props.chartData, () => {
  createChart()
}, { deep: true })

watch(() => props.options, () => {
  createChart()
}, { deep: true })
</script> 