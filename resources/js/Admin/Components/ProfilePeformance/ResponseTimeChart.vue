<template>
    <div class="bg-white p-4 rounded-lg shadow-sm">
        <h3 class="text-sm font-medium text-gray-700 mb-4">Temps de réponse moyen</h3>
        <div class="h-48 w-full">
            <apexchart 
                type="line" 
                :options="chartOptions" 
                :series="series" 
                height="100%" 
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import VueApexCharts from 'vue3-apexcharts'

const props = defineProps({
    data: {
        type: Array,
        required: true
    }
})

const series = computed(() => [{
    name: 'Temps de réponse (min)',
    data: props.data.map(item => item.time)
}])

const chartOptions = {
    chart: {
        type: 'line',
        toolbar: {
            show: false
        },
        zoom: {
            enabled: false
        },
        fontFamily: 'inherit'
    },
    stroke: {
        curve: 'smooth',
        width: 2
    },
    colors: ['#10B981'],
    markers: {
        size: 4,
        strokeColors: '#10B981',
        strokeWidth: 2,
        hover: {
            size: 6
        }
    },
    grid: {
        borderColor: '#E5E7EB',
        strokeDashArray: 4,
        xaxis: {
            lines: {
                show: true
            }
        }
    },
    xaxis: {
        categories: props.data.map(item => item.date),
        type: 'datetime',
        labels: {
            style: {
                colors: '#6B7280',
                fontSize: '12px'
            }
        }
    },
    yaxis: {
        labels: {
            style: {
                colors: '#6B7280',
                fontSize: '12px'
            },
            formatter: (value) => `${value} min`
        }
    },
    tooltip: {
        theme: 'light',
        y: {
            formatter: (value) => `${value} min`
        },
        style: {
            fontSize: '12px'
        }
    }
}
</script>