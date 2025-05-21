<template>
  <div>
    <div v-if="title || showLegend" class="flex items-center justify-between mb-4">
      <h2 v-if="title" class="text-lg font-semibold text-dark">{{ title }}</h2>
      <div v-if="showLegend && chartType === 'line' && periods.length > 0" class="flex space-x-2">
        <button 
          v-for="period in periods" 
          :key="period.id"
          @click="$emit('period-change', period.id)"
          :class="[
            'px-3 py-1 text-sm rounded', 
            activePeriod === period.id ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700'
          ]"
        >
          {{ period.label }}
        </button>
      </div>
    </div>
    <div class="chart-container" :style="{ height: `${height}px` }">
      <canvas ref="chartRef"></canvas>
    </div>
    <div v-if="chartType === 'doughnut' && legendItems.length > 0" class="mt-6 space-y-4">
      <div v-for="(item, index) in legendItems" :key="index" class="flex items-center">
        <div class="w-3 h-3 rounded-full mr-2" :style="{ backgroundColor: item.color }"></div>
        <span class="text-sm text-gray-600">{{ item.label }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import Chart from 'chart.js/auto'

const props = defineProps({
  chartType: {
    type: String,
    default: 'line',
    validator: (value) => ['line', 'bar', 'doughnut', 'pie', 'radar'].includes(value)
  },
  chartData: {
    type: Object,
    required: true
  },
  chartOptions: {
    type: Object,
    default: () => ({})
  },
  title: {
    type: String,
    default: ''
  },
  height: {
    type: Number,
    default: 300
  },
  periods: {
    type: Array,
    default: () => []
  },
  activePeriod: {
    type: String,
    default: ''
  },
  showLegend: {
    type: Boolean,
    default: true
  },
  legendItems: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['period-change'])

const chartRef = ref(null)
let chartInstance = null

onMounted(() => {
  if (chartRef.value) {
    createChart()
  }
})

onUnmounted(() => {
  if (chartInstance) {
    chartInstance.destroy()
  }
})

watch(() => props.chartData, () => {
  if (chartInstance) {
    chartInstance.data = props.chartData
    chartInstance.update()
  }
}, { deep: true })

watch(() => props.chartOptions, () => {
  if (chartInstance) {
    chartInstance.options = { ...chartInstance.options, ...props.chartOptions }
    chartInstance.update()
  }
}, { deep: true })

const createChart = () => {
  if (chartInstance) {
    chartInstance.destroy()
  }

  const ctx = chartRef.value.getContext('2d')
  chartInstance = new Chart(ctx, {
    type: props.chartType,
    data: props.chartData,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      ...props.chartOptions
    }
  })
}
</script>

<style scoped>
.chart-container {
  position: relative;
}
</style> 