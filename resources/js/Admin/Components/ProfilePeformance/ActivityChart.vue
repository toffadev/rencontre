<template>
    <div class="bg-white p-4 rounded-lg shadow-sm">
        <h3 class="text-sm font-medium text-gray-700 mb-4">Activité des messages</h3>
        <div class="h-64 w-full">
            <apexchart 
                type="area" 
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
    name: 'Messages',
    data: props.data.map(item => item.count)
}])

const chartOptions = {
    chart: {
        type: 'area',
        toolbar: {
            show: false
        },
        zoom: {
            enabled: false
        },
        fontFamily: 'inherit'
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        curve: 'smooth',
        width: 2
    },
    colors: ['#EC4899'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.2,
            stops: [0, 90, 100]
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
            }
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
    tooltip: {
        x: {
            format: 'dd MMM yyyy'
        },
        theme: 'light',
        style: {
            fontSize: '12px'
        }
    }
}
</script>