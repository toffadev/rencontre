<template>
    <div class="bg-white p-4 rounded-lg shadow-sm">
        <h3 class="text-sm font-medium text-gray-700 mb-4">Taux de rétention</h3>
        <div class="h-48 w-full">
            <apexchart 
                type="bar" 
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
    name: 'Taux de rétention',
    data: props.data.map(item => item.rate)
}])

const chartOptions = {
    chart: {
        type: 'bar',
        toolbar: {
            show: false
        },
        fontFamily: 'inherit'
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            horizontal: false,
            columnWidth: '60%',
            endingShape: 'rounded'
        }
    },
    colors: ['#8B5CF6'],
    dataLabels: {
        enabled: false
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
        categories: props.data.map(item => item.period),
        labels: {
            style: {
                colors: '#6B7280',
                fontSize: '12px'
            }
        }
    },
    yaxis: {
        max: 100,
        labels: {
            formatter: (value) => `${value}%`,
            style: {
                colors: '#6B7280',
                fontSize: '12px'
            }
        }
    },
    tooltip: {
        theme: 'light',
        y: {
            formatter: (value) => `${value}%`
        },
        style: {
            fontSize: '12px'
        }
    }
}
</script>