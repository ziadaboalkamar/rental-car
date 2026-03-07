<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Car,
    Users,
    DollarSign,
    Calendar,
    Clock,
    CheckCircle2,
    LayoutDashboard,
    TrendingUp,
    Layers,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

// ────────────────────── Props ──────────────────────────────────────────────
const props = defineProps<{
    stats: {
        total_cars: number;
        available_cars: number;
        active_reservations: number;
        pending_reservations: number;
        total_reservations: number;
        total_revenue: number;
        total_clients: number;
    };
    reservationsByStatus: Array<{
        status: string;
        label: string;
        count: number;
        color: string;
    }>;
    fleetStatus: Array<{
        status: string;
        label: string;
        count: number;
        color: string;
    }>;
    monthlyRevenue: Array<{
        month: string;
        revenue: number;
    }>;
    recentReservations: Array<{
        id: number;
        reservation_number: string;
        client_name: string | null;
        car_name: string;
        branch_name: string;
        start_date: string | null;
        end_date: string | null;
        total_amount: number;
        status: string;
        status_color: string;
    }>;
    topCars: Array<{
        id: number;
        name: string;
        price_per_day: number;
        status: string;
        status_label: string;
        status_color: string;
        completed_count: number;
    }>;
    branches: Array<{ id: number; name: string }>;
    filters: { branch_id: number | null };
    canAccessAllBranches: boolean;
}>();

// ────────────────────── Helpers ────────────────────────────────────────────
const page = usePage<any>();
const currency = computed(() => page.props.currency_symbol ?? '$');

const fmt = (n: number) =>
    new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);

const fmtCurrency = (n: number) => `${currency.value}${fmt(n)}`;

const fmtDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

// ────────────────────── Branch filter ──────────────────────────────────────
const selectedBranch = ref<number | null>(props.filters.branch_id ?? null);
const applyBranchFilter = () => {
    router.get(window.location.pathname, { branch_id: selectedBranch.value ?? undefined }, { preserveState: true });
};

// ────────────────────── Monthly Revenue chart ──────────────────────────────
const maxRevenue = computed(() =>
    Math.max(...props.monthlyRevenue.map((m) => m.revenue), 1),
);
const barHeight = (revenue: number) =>
    Math.max(4, Math.round((revenue / maxRevenue.value) * 160));

// ────────────────────── Reservation status chart ───────────────────────────
const totalResCount = computed(() =>
    props.reservationsByStatus.reduce((sum, s) => sum + s.count, 0),
);
const statusBarWidths = computed(() =>
    props.reservationsByStatus.map((s) => ({
        ...s,
        pct: totalResCount.value > 0 ? Math.round((s.count / totalResCount.value) * 100) : 0,
    })),
);

// ────────────────────── KPI cards config ───────────────────────────────────
const kpiCards = computed(() => [
    {
        title: 'Total Cars',
        value: props.stats.total_cars,
        sub: `${props.stats.available_cars} available`,
        icon: Car,
        accent: '#3B82F6',
        bg: 'rgba(59,130,246,0.1)',
    },
    {
        title: 'Total Revenue',
        value: fmtCurrency(props.stats.total_revenue),
        sub: 'All completed payments',
        icon: DollarSign,
        accent: '#10B981',
        bg: 'rgba(16,185,129,0.1)',
    },
    {
        title: 'Active Reservations',
        value: props.stats.active_reservations,
        sub: `${props.stats.pending_reservations} pending`,
        icon: Calendar,
        accent: '#F59E0B',
        bg: 'rgba(245,158,11,0.1)',
    },
    {
        title: 'Total Reservations',
        value: props.stats.total_reservations,
        sub: 'All time bookings',
        icon: CheckCircle2,
        accent: '#8B5CF6',
        bg: 'rgba(139,92,246,0.1)',
    },
    {
        title: 'Total Clients',
        value: props.stats.total_clients,
        sub: 'Registered clients',
        icon: Users,
        accent: '#EC4899',
        bg: 'rgba(236,72,153,0.1)',
    },
    {
        title: 'Available Cars',
        value: props.stats.available_cars,
        sub: `of ${props.stats.total_cars} total`,
        icon: TrendingUp,
        accent: '#06B6D4',
        bg: 'rgba(6,182,212,0.1)',
    },
]);
</script>

<template>
    <Head title="Dashboard" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-6 lg:p-8">

            <!-- ── Header ─────────────────────────────────────────── -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow">
                        <LayoutDashboard class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Dashboard</h1>
                        <p class="text-sm text-muted-foreground">Your rental business at a glance</p>
                    </div>
                </div>

                <!-- Branch filter -->
                <div v-if="canAccessAllBranches && branches.length > 1" class="flex items-center gap-2">
                    <select
                        v-model="selectedBranch"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        @change="applyBranchFilter"
                    >
                        <option :value="null">All Branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
            </div>

            <!-- ── KPI Cards ───────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
                <Card
                    v-for="card in kpiCards"
                    :key="card.title"
                    class="relative overflow-hidden border-0 shadow-sm transition-shadow hover:shadow-md"
                >
                    <!-- accent stripe -->
                    <div
                        class="absolute inset-x-0 top-0 h-1 rounded-t-xl"
                        :style="{ background: card.accent }"
                    />
                    <CardHeader class="pb-2 pt-4">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-xs font-medium text-muted-foreground">{{ card.title }}</CardTitle>
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg"
                                :style="{ background: card.bg }"
                            >
                                <component :is="card.icon" class="h-4 w-4" :style="{ color: card.accent }" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ card.value }}</div>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ card.sub }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Charts Row ──────────────────────────────────────── -->
            <div class="grid gap-4 lg:grid-cols-2">

                <!-- Monthly Revenue Bar Chart -->
                <Card class="border-0 shadow-sm">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <TrendingUp class="h-4 w-4 text-primary" />
                            <CardTitle class="text-base">Monthly Revenue</CardTitle>
                        </div>
                        <p class="text-xs text-muted-foreground">Last 6 months</p>
                    </CardHeader>
                    <CardContent>
                        <div class="flex h-44 items-end gap-2 px-2">
                            <div
                                v-for="item in monthlyRevenue"
                                :key="item.month"
                                class="group flex flex-1 flex-col items-center gap-1"
                            >
                                <!-- Tooltip -->
                                <div class="relative">
                                    <div
                                        class="absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-xs text-background group-hover:block"
                                    >
                                        {{ fmtCurrency(item.revenue) }}
                                    </div>
                                </div>
                                <!-- Bar -->
                                <div
                                    class="w-full rounded-t-md bg-primary/80 transition-all duration-300 hover:bg-primary"
                                    :style="{ height: barHeight(item.revenue) + 'px' }"
                                />
                                <!-- Label -->
                                <span class="text-center text-[10px] text-muted-foreground">
                                    {{ item.month.split(' ')[0] }}
                                </span>
                            </div>
                        </div>
                        <!-- Y-axis hint -->
                        <p class="mt-2 text-right text-xs text-muted-foreground">
                            Max {{ fmtCurrency(maxRevenue) }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Reservation Status Breakdown -->
                <Card class="border-0 shadow-sm">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <Layers class="h-4 w-4 text-primary" />
                            <CardTitle class="text-base">Reservations by Status</CardTitle>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ stats.total_reservations }} total</p>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Stacked bar -->
                        <div class="flex h-5 w-full overflow-hidden rounded-full">
                            <div
                                v-for="seg in statusBarWidths.filter((s) => s.pct > 0)"
                                :key="seg.status"
                                :style="{ width: seg.pct + '%', background: seg.color }"
                                class="transition-all duration-500"
                                :title="`${seg.label}: ${seg.count}`"
                            />
                            <div
                                v-if="totalResCount === 0"
                                class="w-full rounded-full bg-muted"
                            />
                        </div>

                        <!-- Legend -->
                        <div class="grid grid-cols-2 gap-2">
                            <div
                                v-for="seg in statusBarWidths"
                                :key="seg.status"
                                class="flex items-center justify-between gap-2 rounded-lg border p-2"
                            >
                                <div class="flex items-center gap-2">
                                    <div class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: seg.color }" />
                                    <span class="text-xs capitalize">{{ seg.label }}</span>
                                </div>
                                <span class="text-sm font-semibold">{{ seg.count }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Fleet Status ────────────────────────────────────── -->
            <Card class="border-0 shadow-sm">
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Car class="h-4 w-4 text-primary" />
                        <CardTitle class="text-base">Fleet Status</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-3">
                        <div
                            v-for="fs in fleetStatus"
                            :key="fs.status"
                            class="flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors"
                            :style="{ borderColor: fs.color, color: fs.color, background: fs.color + '15' }"
                        >
                            <span
                                class="h-2 w-2 rounded-full"
                                :style="{ background: fs.color }"
                            />
                            {{ fs.label }}
                            <span class="ml-1 font-bold">{{ fs.count }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Tables Row ──────────────────────────────────────── -->
            <div class="grid gap-4 lg:grid-cols-2">

                <!-- Recent Reservations -->
                <Card class="border-0 shadow-sm">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Clock class="h-4 w-4 text-primary" />
                                <CardTitle class="text-base">Recent Reservations</CardTitle>
                            </div>
                            <Link
                                :href="`/admin/reservations`"
                                class="text-xs text-primary hover:underline"
                            >View all →</Link>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="recentReservations.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            No reservations yet.
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-4 py-2 text-left text-xs text-muted-foreground">Client</th>
                                    <th class="px-4 py-2 text-left text-xs text-muted-foreground">Car</th>
                                    <th class="px-4 py-2 text-left text-xs text-muted-foreground">Dates</th>
                                    <th class="px-4 py-2 text-right text-xs text-muted-foreground">Amount</th>
                                    <th class="px-4 py-2 text-left text-xs text-muted-foreground">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="res in recentReservations"
                                    :key="res.id"
                                    class="border-b last:border-0 transition-colors hover:bg-muted/40"
                                >
                                    <td class="px-4 py-3 font-medium">{{ res.client_name ?? '—' }}</td>
                                    <td class="max-w-[120px] truncate px-4 py-3 text-muted-foreground">{{ res.car_name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">
                                        {{ fmtDate(res.start_date) }}<br>{{ fmtDate(res.end_date) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">
                                        {{ fmtCurrency(res.total_amount) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :style="{ background: res.status_color + '20', color: res.status_color }"
                                        >
                                            {{ res.status.replace('_', ' ') }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <!-- Top Cars -->
                <Card class="border-0 shadow-sm">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Car class="h-4 w-4 text-primary" />
                                <CardTitle class="text-base">Top Performing Cars</CardTitle>
                            </div>
                            <Link
                                :href="`/admin/cars`"
                                class="text-xs text-primary hover:underline"
                            >View all →</Link>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="topCars.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            No car data yet.
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-4 py-2 text-left text-xs text-muted-foreground">#</th>
                                    <th class="px-4 py-2 text-left text-xs text-muted-foreground">Car</th>
                                    <th class="px-4 py-2 text-left text-xs text-muted-foreground">Status</th>
                                    <th class="px-4 py-2 text-right text-xs text-muted-foreground">Price/Day</th>
                                    <th class="px-4 py-2 text-right text-xs text-muted-foreground">Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(car, idx) in topCars"
                                    :key="car.id"
                                    class="border-b last:border-0 transition-colors hover:bg-muted/40"
                                >
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                                            :class="idx === 0 ? 'bg-yellow-400/20 text-yellow-600'
                                                : idx === 1 ? 'bg-gray-300/20 text-gray-600'
                                                : idx === 2 ? 'bg-orange-400/20 text-orange-600'
                                                : 'bg-muted text-muted-foreground'"
                                        >
                                            {{ idx + 1 }}
                                        </span>
                                    </td>
                                    <td class="max-w-[130px] truncate px-4 py-3 font-medium">{{ car.name }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :style="{ background: car.status_color + '20', color: car.status_color }"
                                        >
                                            {{ car.status_label }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ fmtCurrency(car.price_per_day) }}</td>
                                    <td class="px-4 py-3 text-right font-bold">{{ car.completed_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>

        </main>
    </AdminLayout>
</template>
