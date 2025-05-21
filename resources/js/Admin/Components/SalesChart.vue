<template>
  <div>
    <ChartComponent
      chartType="line"
      :chartData="chartData"
      :chartOptions="chartOptions"
      title="Ventes mensuelles"
      :periods="periods"
      :activePeriod="activePeriod"
      @period-change="changePeriod"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import ChartComponent from './ChartComponent.vue'

const activePeriod = ref('month')
const periods = ref([
  { id: 'month', label: 'Mois' },
  { id: 'year', label: 'Année' }
])

// Sample data for demonstration
const monthlyData = ref([
  8500, 9200, 10500, 12000, 12500, 13500, 14200, 14800, 13600, 14500, 15800, 17200
])

const yearlyData = ref([
  120000, 145000, 165000, 185000, 210000
])

const changePeriod = (period) => {
  activePeriod.value = period
}

const chartData = computed(() => {
  const isMonthly = activePeriod.value === 'month'
  
  return {
    labels: isMonthly
      ? ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc']
      : ['2019', '2020', '2021', '2022', '2023'],
    datasets: [{
      label: isMonthly ? 'Ventes 2023' : 'Ventes annuelles',
      data: isMonthly ? monthlyData.value : yearlyData.value,
      borderColor: '#4f46e5',
      backgroundColor: 'rgba(79, 70, 229, 0.05)',
      borderWidth: 2,
      fill: true,
      tension: 0.4
    }]
  }
})

const chartOptions = ref({
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: {
        drawBorder: false
      },
      ticks: {
        callback: function(value) {
          if (value >= 1000) {
            return value / 1000 + 'k €'
          }
          return value + ' €'
        }
      }
    },
    x: {
      grid: {
        display: false
      }
    }
  }
})
</script> 