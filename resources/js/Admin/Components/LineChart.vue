<template>
    <div class="chart-container" style="position: relative; height: 300px;">
        <canvas :id="chartId" ref="chartCanvas"></canvas>
    </div>
</template>

<script>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import Chart from 'chart.js/auto';

export default {
    props: {
        chartData: {
            type: Object,
            required: true
        },
        options: {
            type: Object,
            default: () => ({})
        }
    },
    setup(props) {
        const chartCanvas = ref(null);
        const chartId = 'chart-' + Math.random().toString(36).substring(2, 9);
        let chart = null;

        const createChart = () => {
            if (chart) {
                chart.destroy();
            }

            const ctx = chartCanvas.value.getContext('2d');
            chart = new Chart(ctx, {
                type: 'line',
                data: props.chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    ...props.options
                }
            });
        };

        const updateChart = () => {
            if (!chart) return;

            chart.data = props.chartData;
            chart.options = {
                responsive: true,
                maintainAspectRatio: false,
                ...props.options
            };
            chart.update();
        };

        onMounted(() => {
            if (props.chartData && props.chartData.datasets) {
                createChart();
            }
        });

        watch(() => props.chartData, (newData) => {
            if (newData && newData.datasets) {
                if (!chart) {
                    createChart();
                } else {
                    updateChart();
                }
            }
        }, { deep: true });

        onBeforeUnmount(() => {
            if (chart) {
                chart.destroy();
            }
        });

        return {
            chartCanvas,
            chartId
        };
    }
};
</script>