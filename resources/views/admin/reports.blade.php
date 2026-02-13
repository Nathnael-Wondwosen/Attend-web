@extends('layouts.admin')

@section('title', 'Finot | Reports')
@section('page-label', 'Reports')
@section('page-title', 'Reports')
@section('page-subtitle', 'Daily, range, and trend reports with CSV export.')

@section('content')
    <div class="glass rounded-2xl p-4 md:p-6 shadow-glow space-y-5">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-5">
                <label class="text-xs text-slate-400">Class</label>
                <select id="rep-class" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option value="">Loading...</option>
                </select>
            </div>
            <div class="lg:col-span-3">
                <label class="text-xs text-slate-400">Report type</label>
                <select id="rep-type" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option value="daily">Daily (Roster status)</option>
                    <option value="range">Range (Per-student summary)</option>
                    <option value="trend">Trend (Per-day chart)</option>
                </select>
            </div>

            <div class="lg:col-span-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div id="rep-date-wrap">
                    <label class="text-xs text-slate-400">Date</label>
                    <input id="rep-date" type="date" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" />
                </div>
                <div id="rep-range-wrap" class="hidden grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-slate-400">From</label>
                        <input id="rep-from" type="date" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-400">To</label>
                        <input id="rep-to" type="date" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" />
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <button id="rep-load" class="flex-1 h-11 rounded-xl neon-pill text-sm font-medium">Load</button>
                    <button id="rep-csv" class="h-11 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring">CSV</button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="glass rounded-xl p-3 border border-white/5">
                <p class="text-xs text-slate-400">Present</p>
                <p class="text-2xl text-white font-medium" id="rep-present">—</p>
            </div>
            <div class="glass rounded-xl p-3 border border-white/5">
                <p class="text-xs text-slate-400">Permission</p>
                <p class="text-2xl text-white font-medium" id="rep-permission">—</p>
            </div>
            <div class="glass rounded-xl p-3 border border-white/5">
                <p class="text-xs text-slate-400">Absent</p>
                <p class="text-2xl text-white font-medium" id="rep-absent">—</p>
            </div>
            <div class="glass rounded-xl p-3 border border-white/5">
                <p class="text-xs text-slate-400">Unmarked</p>
                <p class="text-2xl text-white font-medium" id="rep-unmarked">—</p>
            </div>
            <div class="glass rounded-xl p-3 border border-white/5">
                <p class="text-xs text-slate-400">Total</p>
                <p class="text-2xl text-white font-medium" id="rep-total">—</p>
            </div>
        </div>

        <div id="rep-session-meta" class="text-xs text-slate-400"></div>

        <div id="rep-trend-card" class="glass rounded-2xl p-4 md:p-6 border border-white/5 hidden">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Trend</p>
                    <h3 class="text-lg text-white font-medium">Attendance over time</h3>
                </div>
            </div>
            <div class="h-[260px]">
                <canvas id="rep-chart"></canvas>
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/5 overflow-hidden">
            <div class="hidden lg:grid grid-cols-12 px-4 py-3 bg-white/5 text-xs text-slate-300" id="rep-header">
                <span class="col-span-6">Student</span>
                <span class="col-span-3">ID</span>
                <span class="col-span-3 text-right">Status</span>
            </div>
            <div id="rep-rows" class="divide-y divide-white/5 min-h-[200px]">
                <p class="text-slate-400 text-sm px-4 py-3">Select a class and load a report.</p>
            </div>
        </div>

        <p id="rep-error" class="text-sm text-red-300"></p>
    </div>
@endsection

@push('scripts')
<script>
    const rep = {
        classes: [],
        type: 'daily',
        rows: [],
        counts: null,
        session: null,
        trend: [],
        chart: null,
    };

    const $ = (id) => document.getElementById(id);

    const statusPill = (status) => {
        const map = { present: 'text-neon', permission: 'text-mint', absent: 'text-slate-300', unmarked: 'text-amber-300' };
        const s = (status || '—').toString();
        const copy = s === 'unmarked' ? 'Unmarked' : (s.charAt(0).toUpperCase() + s.slice(1));
        return `<span class="px-3 py-1 rounded-full bg-white/5 ${map[s] || 'text-slate-300'} text-xs">${copy}</span>`;
    };

    const setCounts = (c) => {
        $('rep-present').textContent = c?.present ?? '—';
        $('rep-permission').textContent = c?.permission ?? '—';
        $('rep-absent').textContent = c?.absent ?? '—';
        $('rep-unmarked').textContent = c?.unmarked ?? '—';
        $('rep-total').textContent = c?.total ?? '—';
    };

    const setMeta = (txt) => { $('rep-session-meta').textContent = txt || ''; };

    const setHeader = (type) => {
        const header = $('rep-header');
        if (type === 'daily') {
            header.innerHTML = `
                <span class="col-span-6">Student</span>
                <span class="col-span-3">ID</span>
                <span class="col-span-3 text-right">Status</span>
            `;
        } else if (type === 'range') {
            header.innerHTML = `
                <span class="col-span-5">Student</span>
                <span class="col-span-2">Present</span>
                <span class="col-span-2">Absent</span>
                <span class="col-span-2">Permission</span>
                <span class="col-span-1 text-right">%</span>
            `;
        } else {
            header.innerHTML = `
                <span class="col-span-4">Date</span>
                <span class="col-span-2">Present</span>
                <span class="col-span-2">Absent</span>
                <span class="col-span-2">Permission</span>
                <span class="col-span-2 text-right">%</span>
            `;
        }
    };

    const renderDaily = () => {
        const wrap = $('rep-rows');
        if (!rep.rows.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No roster rows for this class/date.</p>';
            return;
        }
        wrap.innerHTML = rep.rows.map(r => `
            <div class="grid grid-cols-1 lg:grid-cols-12 px-4 py-3 gap-2 items-center">
                <div class="lg:col-span-6">
                    <p class="text-white">${r.full_name}</p>
                </div>
                <div class="lg:col-span-3 text-slate-200">${r.student_id}</div>
                <div class="lg:col-span-3 flex justify-end">${statusPill(r.status)}</div>
            </div>
        `).join('');
    };

    const renderRange = () => {
        const wrap = $('rep-rows');
        if (!rep.rows.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No rows for this class/range.</p>';
            return;
        }

        // Show most absent first to surface problems quickly.
        const sorted = [...rep.rows].sort((a, b) => (b.absent - a.absent) || (b.unmarked - a.unmarked) || (a.full_name || '').localeCompare(b.full_name || ''));

        wrap.innerHTML = sorted.map(r => `
            <div class="grid grid-cols-1 lg:grid-cols-12 px-4 py-3 gap-2 items-center">
                <div class="lg:col-span-5">
                    <p class="text-white">${r.full_name}</p>
                    <p class="text-xs text-slate-400">ID: ${r.student_id} • Days: ${r.total_days}</p>
                </div>
                <div class="lg:col-span-2 text-slate-200">${r.present}</div>
                <div class="lg:col-span-2 text-slate-200">${r.absent}${r.unmarked ? ` <span class="text-amber-300 text-xs">(+${r.unmarked} unmarked)</span>` : ''}</div>
                <div class="lg:col-span-2 text-slate-200">${r.permission}</div>
                <div class="lg:col-span-1 flex lg:justify-end text-slate-200">${(r.present_rate ?? '—')}${r.present_rate !== null ? '%' : ''}</div>
            </div>
        `).join('');
    };

    const renderTrend = () => {
        const wrap = $('rep-rows');
        if (!rep.trend.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No days in this range.</p>';
            return;
        }
        wrap.innerHTML = rep.trend.map(d => `
            <div class="grid grid-cols-1 lg:grid-cols-12 px-4 py-3 gap-2 items-center">
                <div class="lg:col-span-4">
                    <p class="text-white">${d.date}</p>
                    <p class="text-xs text-slate-400">${(d.workflow_status || 'draft').toUpperCase()}</p>
                </div>
                <div class="lg:col-span-2 text-slate-200">${d.present}</div>
                <div class="lg:col-span-2 text-slate-200">${d.absent}</div>
                <div class="lg:col-span-2 text-slate-200">${d.permission}</div>
                <div class="lg:col-span-2 flex lg:justify-end text-slate-200">${(d.present_rate ?? '—')}${d.present_rate !== null ? '%' : ''}</div>
            </div>
        `).join('');

        const chartWrap = $('rep-trend-card');
        chartWrap.classList.remove('hidden');

        const labels = rep.trend.map(x => x.date);
        const rates = rep.trend.map(x => x.present_rate ?? 0);
        const present = rep.trend.map(x => x.present ?? 0);
        const absent = rep.trend.map(x => x.absent ?? 0);
        const permission = rep.trend.map(x => x.permission ?? 0);

        if (rep.chart) {
            rep.chart.destroy();
            rep.chart = null;
        }

        const ctx = $('rep-chart').getContext('2d');
        rep.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Present %', data: rates, borderColor: '#22d3ee', backgroundColor: 'rgba(34,211,238,0.12)', tension: 0.25, fill: true, yAxisID: 'y' },
                    { label: 'Present', data: present, borderColor: '#34d399', tension: 0.25, yAxisID: 'y1' },
                    { label: 'Absent', data: absent, borderColor: '#94a3b8', tension: 0.25, yAxisID: 'y1' },
                    { label: 'Permission', data: permission, borderColor: '#7c3aed', tension: 0.25, yAxisID: 'y1' },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#cbd5e1' } }
                },
                scales: {
                    x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.06)' } },
                    y: { position: 'left', min: 0, max: 100, ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.06)' } },
                    y1: { position: 'right', ticks: { color: '#94a3b8' }, grid: { drawOnChartArea: false } }
                }
            }
        });
    };

    const clearView = () => {
        $('rep-error').textContent = '';
        $('rep-rows').innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Select a class and load a report.</p>';
        $('rep-trend-card').classList.add('hidden');
        setCounts(null);
        setMeta('');
        rep.rows = [];
        rep.trend = [];
        if (rep.chart) { rep.chart.destroy(); rep.chart = null; }
    };

    const loadClasses = async () => {
        const res = await fetch('/api/v1/classes');
        const json = await res.json();
        rep.classes = (json.data || json || []).map(c => ({ id: c.id, name: c.name || `Grade ${c.grade || ''}${c.section || ''}`.trim() }));
        $('rep-class').innerHTML = '<option value="">Select class</option>' + rep.classes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    };

    const exportCsv = async (endpoint, filenameFallback) => {
        try {
            const res = await fetch(endpoint, { headers: { 'Accept': 'text/csv' } });
            if (!res.ok) throw new Error('export failed');
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const dispo = res.headers.get('Content-Disposition') || '';
            const m = dispo.match(/filename=\"?([^\";]+)\"?/i);
            a.download = m ? m[1] : filenameFallback;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch {
            $('rep-error').textContent = 'Export failed';
        }
    };

    const loadReport = async () => {
        clearView();

        const classId = $('rep-class').value;
        if (!classId) return;

        const type = $('rep-type').value;
        rep.type = type;
        setHeader(type);

        $('rep-rows').innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Loading...</p>';

        try {
            if (type === 'daily') {
                const date = $('rep-date').value;
                if (!date) throw new Error('Choose a date');
                const res = await fetch(`/api/v1/reports/class/${classId}/day?date=${encodeURIComponent(date)}`);
                const json = await res.json().catch(() => null);
                if (!res.ok) throw new Error(json?.message || 'Failed');
                rep.rows = json.rows || [];
                rep.counts = json.counts || null;
                rep.session = json.session || null;
                setCounts(rep.counts);
                setMeta(rep.session ? `Session #${rep.session.id} • ${(rep.session.workflow_status || '').toUpperCase()}` : 'No session for this day');
                renderDaily();
                return;
            }

            const from = $('rep-from').value;
            const to = $('rep-to').value;
            if (!from || !to) throw new Error('Choose from/to');

            if (type === 'range') {
                const res = await fetch(`/api/v1/reports/class/${classId}/range?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);
                const json = await res.json().catch(() => null);
                if (!res.ok) throw new Error(json?.message || 'Failed');
                rep.rows = json.rows || [];
                const totals = json.totals || null;
                setCounts(totals ? { ...totals, total: totals.total } : null);
                setMeta(`Sessions: ${json.sessions?.count ?? 0} • From ${json.from} to ${json.to}`);
                renderRange();
                return;
            }

            if (type === 'trend') {
                const res = await fetch(`/api/v1/reports/class/${classId}/trend?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);
                const json = await res.json().catch(() => null);
                if (!res.ok) throw new Error(json?.message || 'Failed');
                rep.trend = json.days || [];
                const present = rep.trend.reduce((s, d) => s + (d.present || 0), 0);
                const permission = rep.trend.reduce((s, d) => s + (d.permission || 0), 0);
                const absent = rep.trend.reduce((s, d) => s + (d.absent || 0), 0);
                const unmarked = rep.trend.reduce((s, d) => s + (d.unmarked || 0), 0);
                const total = rep.trend.reduce((s, d) => s + (d.total || 0), 0);
                setCounts({ present, permission, absent, unmarked, total });
                setMeta(`Roster: ${json.roster_count ?? '—'} • From ${json.from} to ${json.to}`);
                renderTrend();
                return;
            }
        } catch (e) {
            $('rep-error').textContent = e?.message || 'Failed to load report';
            $('rep-rows').innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Failed to load.</p>';
        }
    };

    const onTypeChanged = () => {
        const type = $('rep-type').value;
        $('rep-date-wrap').classList.toggle('hidden', type !== 'daily');
        $('rep-range-wrap').classList.toggle('hidden', type === 'daily');
        clearView();
        setHeader(type);
    };

    $('rep-load')?.addEventListener('click', loadReport);
    $('rep-type')?.addEventListener('change', onTypeChanged);
    $('rep-class')?.addEventListener('change', clearView);

    $('rep-csv')?.addEventListener('click', async () => {
        const classId = $('rep-class').value;
        if (!classId) return;
        const type = $('rep-type').value;
        if (type === 'daily') {
            const date = $('rep-date').value;
            if (!date) return;
            return exportCsv(`/api/v1/reports/class/${classId}/day?date=${encodeURIComponent(date)}&format=csv`, `report_daily_class_${classId}_${date}.csv`);
        }
        const from = $('rep-from').value;
        const to = $('rep-to').value;
        if (!from || !to) return;
        if (type === 'range') {
            return exportCsv(`/api/v1/reports/class/${classId}/range?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&format=csv`, `report_range_class_${classId}_${from}_to_${to}.csv`);
        }
        if (type === 'trend') {
            return exportCsv(`/api/v1/reports/class/${classId}/trend?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&format=csv`, `report_trend_class_${classId}_${from}_to_${to}.csv`);
        }
    });

    // defaults
    const today = new Date().toISOString().slice(0,10);
    $('rep-date').value = today;
    const d7 = new Date(Date.now() - 6 * 24 * 60 * 60 * 1000).toISOString().slice(0,10);
    $('rep-from').value = d7;
    $('rep-to').value = today;

    onTypeChanged();
    loadClasses();
</script>
@endpush

