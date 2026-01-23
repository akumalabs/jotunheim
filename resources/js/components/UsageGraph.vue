<template>
    <div class="glass-card border border-secondary-700/50 rounded-xl p-6">
        <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4">
        <h3 class="text-sm font-medium text-gray-300 mb-3">{{ title }}</h3>
        <div v-if="isLoading" class="h-64 flex items-center justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        </div>
        <div v-else-if="chartData" class="h-64 relative">
            <Line ref="chartRef" :data="chartData" :options="chartOptions" @dblclick="resetZoom" />
            <div class="absolute top-0 right-0 text-xs text-gray-500 p-2">
                <span class="mr-3">Ctrl+Scroll: Zoom</span>
                <span>Shift+Drag: Pan</span>
            </div>
        </div>    <div v-else class="flex items-center justify-center h-full text-secondary-500">
                No data available
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
    TimeScale
} from 'chart.js';
import 'chartjs-adapter-date-fns';
import zoomPlugin from 'chartjs-plugin-zoom';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
    TimeScale,
    zoomPlugin
);

const chartRef = ref(null);

const resetZoom = () => {
    if (chartRef.value?.chart) {
        chartRef.value.chart.resetZoom();
    }
};

const props = defineProps<{
    title: string;
    data: any[];
    valueKey: string | string[];
    label: string | string[];
    color: string | string[];
    isLoading?: boolean;
    unit?: 'mbps' | 'percent' | 'mbs' | 'bytes'; // Unit type for formatting
    maxValue?: number; // For calculating percentages (e.g., maxmem for memory)
}>();

// Format value based on unit type
const formatValue = (value: number): string => {
    if (!props.unit) return value.toFixed(2);
    
    switch (props.unit) {
        case 'mbps': // Network: bytes/s to Mbps
            return (value * 8 / 1000000).toFixed(2);
        case 'percent':
            // If maxValue provided (memory case), calculate percentage
            if (props.maxValue) {
                return ((value / props.maxValue) * 100).toFixed(1);
            }
            // Otherwise CPU case: already decimal 0-1
            return (value * 100).toFixed(1);
        case 'mbs': // Disk I/O: bytes/s to MB/s
            return (value / 1048576).toFixed(2);
        case 'bytes':
            return value.toFixed(0);
        default:
            return value.toFixed(2);
    }
};

// Get unit suffix
const getUnitSuffix = (): string => {
    switch (props.unit) {
        case 'mbps': return ' Mbps';
        case 'percent': return '%';
        case 'mbs': return ' MB/s';
        case 'bytes': return ' B';
        default: return '';
    }
};

const chartData = computed(() => {
    if (!props.data || props.data.length === 0) return null;
    
    // Check if we have multiple datasets (e.g. In/Out or Read/Write)
    const isMultiDataset = Array.isArray(props.valueKey);
    const labels = props.data.map((point: any) => point.time * 1000); // Convert epoch to ms
    
    if (isMultiDataset) {
        const keys = props.valueKey as string[];
        const datasetLabels = props.label as string[];
        const colors = props.color as string[];
        
        return {
            labels,
            datasets: keys.map((key, index) => ({
                label: datasetLabels[index],
                data: props.data.map((point: any) => formatValue(point[key])),
                borderColor: colors[index],
                backgroundColor: (ctx: any) => {
                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, colors[index] + '40'); // 25% opacity
                    gradient.addColorStop(1, colors[index] + '00'); // 0% opacity
                    return gradient;
                },
                fill: true,
                tension: 0.3,
                pointRadius: 0,
                pointHoverRadius: 4,
                borderWidth: 2
            }))
        };
    } else {
        return {
            labels,
            datasets: [{
                label: props.label as string,
                data: props.data.map((point: any) => formatValue(point[props.valueKey as string])),
                borderColor: props.color as string,
                backgroundColor: (ctx: any) => {
                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                    const color = props.color as string;
                    gradient.addColorStop(0, color + '40'); // 25% opacity
                    gradient.addColorStop(1, color + '00'); // 0% opacity
                    return gradient;
                },
                fill: true,
                tension: 0.3,
                pointRadius: 0,
                pointHoverRadius: 4,
                borderWidth: 2
            }]
        };
    }
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: Array.isArray(props.valueKey),
            labels: {
                color: '#94a3b8',
                font: { size: 12 }
            }
        },
        tooltip: {
            mode: 'index' as const,
            intersect: false,
            backgroundColor: '#3b82f6', // Bright blue background (VF style)
            titleColor: '#ffffff',
            bodyColor: '#ffffff',
            borderColor: '#3b82f6',
            borderWidth: 1,
            displayColors: false, // Hide dataset color box
            callbacks: {
                title: () => '', // No separate title line
                label: (context: any) => {
                    const date = new Date(context.parsed.x);
                    const dateStr = date.toLocaleString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: false // VF uses 24h or 12h? Screenshot shows 07:45:39, likely 24h or padded 12h. Let's use 24h for standard tech. 
                        // Wait, screenshot says 07:45:39. If it was PM it would match? 
                        // Let's stick to the user's LocaleString format but add seconds.
                    });
                    
                    const value = context.parsed.y.toFixed(1) + getUnitSuffix();
                    return `(${dateStr}, ${value})`;
                }
            }
        },
        zoom: {
            zoom: {
                wheel: {
                    enabled: true,
                    modifierKey: 'ctrl' as const,
                },
                pinch: {
                    enabled: true
                },
                mode: 'x' as const,
            },
            pan: {
                enabled: true,
                mode: 'x' as const,
                modifierKey: 'shift' as const,
            },
            limits: {
                x: { min: 'original' as const, max: 'original' as const },
            },
        }
    },
    scales: {
        x: {
            type: 'time',
            time: {
                displayFormats: {
                    minute: 'h:mm a',
                    hour: 'h:mm a',
                    day: 'MMM d',
                },
                tooltipFormat: 'MMM d, yyyy h:mm a'
            },
            grid: {
                display: false,
                drawBorder: false,
            },
            ticks: {
                color: '#64748b',
                font: { size: 11 },
                maxTicksLimit: 6, // 6 range indicator (VF style)
                autoSkip: true,
                maxRotation: 0,
                minRotation: 0,
                callback: function(value: any, index: number, ticks: any) {
                    const date = new Date(value);
                    const timeStr = date.toLocaleTimeString('en-US', { 
                        hour: 'numeric', 
                        minute: '2-digit',
                        hour12: true 
                    });
                    
                    // Show full date ONLY on the first tick (VF style "Jan 22, 2026")
                    if (index === 0) {
                        const dateStr = date.toLocaleDateString('en-US', { 
                            month: 'short', 
                            day: 'numeric',
                            year: 'numeric'
                        });
                        return [dateStr, timeStr]; // Multi-line: Date on top, time below
                    }
                    
                    return timeStr; // Just time for others
                }
            }
        },
        y: {
            grid: {
                display: false, // No horizontal grid lines (VF style)
                drawBorder: false,
            },
            ticks: {
                color: '#64748b',
                font: { size: 11 },
                callback: (value: any) => {
                    // For percentages, only show 0%, 50%, 100% (VirtFusion style)
                    if (props.unit === 'percent') {
                        if (value === 0 || value === 50 || value === 100) {
                            return value + '%';
                        }
                        return '';
                    }
                    return value.toFixed(1) + getUnitSuffix();
                },
                // For percentages: show exactly 0, 50, 100
                stepSize: props.unit === 'percent' ? 50 : undefined,
                maxTicksLimit: props.unit !== 'percent' ? 6 : undefined, // 6 unit markers for Network/Disk
            },
            beginAtZero: true,
            // Limit memory percentage to 0-100%
            max: props.unit === 'percent' ? 100 : undefined,
        }
    },
}));
</script>
