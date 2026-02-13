@extends('layouts.admin')

@section('title', 'Finot | Admin Dashboard')
@section('page-label', 'Live analytics')
@section('page-title', 'Attendance Dashboard')
@section('page-subtitle', 'Real-time attendance monitoring and analytics')

@section('content')
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <div class="glass card-accent rounded-2xl p-5 shadow-glow">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-300 text-sm">Total Students</p>
                <i class="fas fa-users text-neon"></i>
            </div>
            <p class="text-3xl text-white font-medium" id="total-students">1,247</p>
            <p class="text-xs text-slate-400 mt-2">+32 this month</p>
        </div>
        <div class="glass card-accent rounded-2xl p-5 shadow-glow">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-300 text-sm">Active Classes</p>
                <i class="fas fa-chalkboard text-mint"></i>
            </div>
            <p class="text-3xl text-white font-medium" id="active-classes">24</p>
            <p class="text-xs text-slate-400 mt-2">3 running live</p>
        </div>
        <div class="glass card-accent rounded-2xl p-5 shadow-glow">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-300 text-sm">Today's Attendance</p>
                <i class="fas fa-wave-square text-primary"></i>
            </div>
            <p class="text-3xl text-white font-medium" id="today-attendance">89%</p>
            <p class="text-xs text-slate-400 mt-2">Updating every 5s</p>
        </div>
        <div class="glass card-accent rounded-2xl p-5 shadow-glow">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-300 text-sm">Alerts</p>
                <i class="fas fa-radiation text-amber-300"></i>
            </div>
            <p class="text-3xl text-white font-medium" id="alerts-count">12</p>
            <p class="text-xs text-slate-400 mt-2">4 critical, 8 minor</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-8">
        <div class="xl:col-span-2 glass rounded-2xl p-6 shadow-glow">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Signal</p>
                    <h3 class="text-lg text-white font-medium">Weekly Attendance Trend</h3>
                </div>
                <select class="glass border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option>This Week</option>
                    <option>Last Week</option>
                    <option>This Month</option>
                </select>
            </div>
            <canvas id="attendanceChart" height="280"></canvas>
        </div>
        <div class="glass rounded-2xl p-6 shadow-glow">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Pulse</p>
            <h3 class="text-lg text-white font-medium mb-4">Class Performance</h3>
            <div class="space-y-5">
                <div>
                    <div class="flex justify-between text-sm text-slate-300">
                        <span>Grade 10A</span><span class="text-neon font-medium">94%</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-white/10 mt-2">
                        <div class="h-2 rounded-full bg-gradient-to-r from-neon to-mint" style="width: 94%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm text-slate-300">
                        <span>Grade 9B</span><span class="text-primary font-medium">87%</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-white/10 mt-2">
                        <div class="h-2 rounded-full bg-gradient-to-r from-primary to-neon" style="width: 87%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm text-slate-300">
                        <span>Grade 11C</span><span class="text-amber-300 font-medium">76%</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-white/10 mt-2">
                        <div class="h-2 rounded-full bg-gradient-to-r from-amber-300 to-pink-400" style="width: 76%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-8">
        <div class="xl:col-span-2 glass rounded-2xl p-6 shadow-glow">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Stream</p>
                    <h3 class="text-lg text-white font-medium">Recent Activity</h3>
                </div>
                <span class="px-3 py-1 rounded-full text-xs bg-white/10 text-neon border border-neon/40">Live feed</span>
            </div>
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 mt-2 shadow-glow"></span>
                    <div>
                        <p class="text-white font-medium">Attendance session opened for Grade 10A Mathematics</p>
                        <p class="text-xs text-slate-400">2 minutes ago</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="h-2.5 w-2.5 rounded-full bg-primary mt-2 shadow-glow"></span>
                    <div>
                        <p class="text-white font-medium">3 students marked as late in Grade 9B Science</p>
                        <p class="text-xs text-slate-400">15 minutes ago</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-300 mt-2 shadow-glow"></span>
                    <div>
                        <p class="text-white font-medium">Alert: Student ID #1245 has 5 consecutive absences</p>
                        <p class="text-xs text-slate-400">1 hour ago</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="glass rounded-2xl p-6 shadow-glow">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Timeline</p>
            <h3 class="text-lg text-white font-medium mb-5">Today</h3>
            <div class="space-y-4 text-sm">
                <div class="flex items-start gap-3">
                    <span class="mt-1 h-1.5 w-12 rounded-full bg-gradient-to-r from-neon to-mint"></span>
                    <div>
                        <p class="text-white">Morning roll calls synced</p>
                        <p class="text-xs text-slate-400">08:15</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-1 h-1.5 w-12 rounded-full bg-gradient-to-r from-primary to-neon"></span>
                    <div>
                        <p class="text-white font-medium">AI anomaly scan completed</p>
                        <p class="text-xs text-slate-400">10:00</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-1 h-1.5 w-12 rounded-full bg-gradient-to-r from-amber-300 to-pink-400"></span>
                    <div>
                        <p class="text-white font-medium">Guardians notified for critical absences</p>
                        <p class="text-xs text-slate-400">11:20</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-1 h-1.5 w-12 rounded-full bg-gradient-to-r from-mint to-neon"></span>
                    <div>
                        <p class="text-white font-medium">Data snapshot queued for export</p>
                        <p class="text-xs text-slate-400">12:45</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            datasets: [{
                label: 'Attendance Rate',
                data: [85, 89, 92, 87, 91],
                borderColor: '#22d3ee',
                borderWidth: 3,
                backgroundColor: 'rgba(34, 211, 238, 0.12)',
                tension: 0.45,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#7c3aed',
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#cbd5e1' }, grid: { color: 'rgba(255,255,255,0.04)' } },
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { color: '#cbd5e1', callback: value => value + '%' },
                    grid: { color: 'rgba(255,255,255,0.04)' }
                }
            }
        }
    });

    setInterval(() => {
        document.getElementById('today-attendance').textContent =
            (85 + Math.floor(Math.random() * 10)) + '%';
    }, 5000);
</script>
@endpush
