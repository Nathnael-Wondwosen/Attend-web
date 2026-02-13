@extends('layouts.admin')

@section('title', 'Finot | Attendance')
@section('page-label', 'Control room')
@section('page-title', 'Attendance Control')
@section('page-subtitle', 'Open sessions, mark status, and sync in real time')

@section('content')
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6">
        <div class="xl:col-span-2 glass rounded-2xl p-4 md:p-6 shadow-glow flex flex-col gap-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="flex flex-col gap-2">
                    <label class="text-xs text-slate-400">Class</label>
                    <select id="att-class" class="rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                        <option value="">Select class</option>
                    </select>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-xs text-slate-400">Session</label>
                    <div class="flex gap-2">
                        <select id="att-session" class="flex-1 rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                            <option value="">Select session</option>
                        </select>
                        <button id="start-session" class="px-3 rounded-xl neon-pill text-sm font-medium">Start</button>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-xs text-slate-400">Date</label>
                    <input id="att-date" type="date" class="rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" />
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="glass rounded-xl p-3 border border-white/5">
                    <p class="text-xs text-slate-400">Present</p>
                    <p class="text-2xl text-white font-medium" id="stat-present">0</p>
                </div>
                <div class="glass rounded-xl p-3 border border-white/5">
                    <p class="text-xs text-slate-400">Late</p>
                    <p class="text-2xl text-white font-medium" id="stat-late">0</p>
                </div>
                <div class="glass rounded-xl p-3 border border-white/5">
                    <p class="text-xs text-slate-400">Absent</p>
                    <p class="text-2xl text-white font-medium" id="stat-absent">0</p>
                </div>
                <div class="glass rounded-xl p-3 border border-white/5">
                    <p class="text-xs text-slate-400">Total</p>
                    <p class="text-2xl text-white font-medium" id="stat-total">0</p>
                </div>
            </div>

            <div class="glass rounded-2xl border border-white/5 overflow-hidden">
                <div class="hidden lg:grid grid-cols-12 px-4 py-3 bg-white/5 text-xs text-slate-300">
                    <span class="col-span-5">Student</span>
                    <span class="col-span-3">ID</span>
                    <span class="col-span-2">Status</span>
                    <span class="col-span-2 text-right">Action</span>
                </div>
                <div id="att-roster" class="divide-y divide-white/5 min-h-[200px]">
                    <p class="text-slate-400 text-sm px-4 py-3">Choose a class and session to load roster.</p>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-4 md:p-6 shadow-glow flex flex-col gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Controls</p>
                <h3 class="text-lg text-white font-medium">Quick actions</h3>
            </div>
            <div class="space-y-3 text-sm text-slate-200">
                <button id="mark-all-present" class="w-full glass rounded-xl px-4 py-3 text-left hover:bg-white/10 transition flex items-center gap-3">
                    <i class="fas fa-user-check text-neon"></i><span>Mark all present</span>
                </button>
                <button id="save-attendance" class="w-full neon-pill rounded-xl px-4 py-3 text-left flex items-center gap-3">
                    <i class="fas fa-floppy-disk"></i><span>Save updates</span>
                </button>
                <button id="refresh-session" class="w-full glass rounded-xl px-4 py-3 text-left hover:bg-white/10 transition flex items-center gap-3">
                    <i class="fas fa-rotate text-primary"></i><span>Refresh session</span>
                </button>
                <button id="export-session" class="w-full glass rounded-xl px-4 py-3 text-left hover:bg-white/10 transition flex items-center gap-3">
                    <i class="fas fa-file-export text-amber-300"></i><span>Export CSV</span>
                </button>
            </div>
            <div class="glass rounded-xl p-4 border border-white/5">
                <p class="text-xs text-slate-400 mb-2">Last sync</p>
                <p class="text-white text-sm" id="last-sync">—</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const attState = {
        classes: [],
        sessions: [],
        roster: [],
        currentClass: null,
        currentSession: null,
    };

    const setSync = () => {
        document.getElementById('last-sync').textContent = new Date().toLocaleString();
    };

    const statusPill = (status) => {
        const palette = { present: 'text-neon', late: 'text-amber-300', absent: 'text-slate-300' };
        const copy = status.charAt(0).toUpperCase() + status.slice(1);
        return `<span class="px-3 py-1 rounded-full bg-white/5 ${palette[status] || ''} text-xs">${copy}</span>`;
    };

    const computeStats = () => {
        const total = attState.roster.length;
        const present = attState.roster.filter(s => s.status === 'present').length;
        const late = attState.roster.filter(s => s.status === 'late').length;
        const absent = attState.roster.filter(s => s.status === 'absent').length;
        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-present').textContent = present;
        document.getElementById('stat-late').textContent = late;
        document.getElementById('stat-absent').textContent = absent;
    };

    const renderRoster = () => {
        const wrap = document.getElementById('att-roster');
        if (!attState.roster.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No students found for this session.</p>';
            computeStats();
            return;
        }
        wrap.innerHTML = attState.roster.map(student => `
            <div class="grid grid-cols-1 lg:grid-cols-12 px-4 py-3 gap-3 items-center">
                <div class="lg:col-span-5">
                    <p class="text-white">${student.name}</p>
                    <p class="text-xs text-slate-400">${student.grade || ''}</p>
                </div>
                <div class="lg:col-span-3 text-slate-200">${student.id || '—'}</div>
                <div class="lg:col-span-2">${statusPill(student.status)}</div>
                <div class="lg:col-span-2 flex gap-2 justify-end">
                    ${['present','late','absent'].map(state => `
                        <button data-student="${student.id}" data-status="${state}" class="px-3 py-1 rounded-lg bg-white/5 text-xs ${student.status===state?'text-neon':'text-slate-200'} hover:text-white transition">
                            ${state[0].toUpperCase() + state.slice(1)}
                        </button>`).join('')}
                </div>
            </div>
        `).join('');
        document.querySelectorAll('[data-student][data-status]').forEach(btn => {
            btn.addEventListener('click', () => {
                const sid = btn.getAttribute('data-student');
                const status = btn.getAttribute('data-status');
                const target = attState.roster.find(s => String(s.id) === String(sid));
                if (target) { target.status = status; renderRoster(); computeStats(); markDirty(sid, status); }
            });
        });
        computeStats();
    };

    const markDirty = (studentId, status) => {
        if (!attState.dirty) attState.dirty = {};
        attState.dirty[studentId] = status;
    };

    const loadClasses = async () => {
        try {
            const res = await fetch('/api/v1/classes');
            const json = await res.json();
            attState.classes = (json.data || json || []).map(c => ({
                id: c.id,
                name: c.name || `Grade ${c.grade || ''}${c.section || ''}`.trim(),
            }));
        } catch {
            attState.classes = [];
        }
        const select = document.getElementById('att-class');
        select.innerHTML = '<option value="">Select class</option>' + attState.classes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    };

    const loadSessions = async (classId) => {
        attState.sessions = [];
        document.getElementById('att-session').innerHTML = '<option value="">Loading...</option>';
        try {
            const res = await fetch(`/api/v1/classes/${classId}/sessions`);
            const json = await res.json();
            attState.sessions = (json.data || json || []).map(s => ({ id: s.id, date: s.date || s.created_at, status: s.status || 'open' }));
        } catch {
            attState.sessions = [];
        }
        const select = document.getElementById('att-session');
        if (!attState.sessions.length) {
            select.innerHTML = '<option value="">No open sessions</option>';
        } else {
            select.innerHTML = attState.sessions.map(s => `<option value="${s.id}">Session ${s.id} • ${s.date || ''}</option>`).join('');
        }
    };

    const loadRoster = async (sessionId) => {
        attState.roster = [];
        document.getElementById('att-roster').innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Loading roster...</p>';
        try {
            const res = await fetch(`/api/v1/sessions/${sessionId}/students`);
            const json = await res.json();
            attState.roster = (json.data || json || []).map(s => ({
                id: s.id,
                name: s.full_name || s.name || `${s.first_name || ''} ${s.last_name || ''}`.trim(),
                grade: s.current_grade || '',
                status: s.pivot?.status || s.status || 'present',
            }));
        } catch {
            attState.roster = [];
        }
        renderRoster();
        setSync();
    };

    const startSession = async () => {
        const classId = attState.currentClass;
        if (!classId) return alert('Select a class first');
        try {
            const res = await fetch(`/api/v1/classes/${classId}/sessions`, { method: 'POST' });
            if (!res.ok) throw new Error('fail');
            await loadSessions(classId);
            const first = attState.sessions[0];
            if (first) {
                document.getElementById('att-session').value = first.id;
                attState.currentSession = first.id;
                loadRoster(first.id);
            }
        } catch {
            alert('Could not start session');
        }
    };

    const saveAttendance = async () => {
        if (!attState.currentSession || !attState.dirty) return;
        const entries = Object.entries(attState.dirty);
        try {
            await Promise.all(entries.map(([studentId, status]) => fetch(`/api/v1/sessions/${attState.currentSession}/students/${studentId}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status })
            })));
            attState.dirty = {};
            setSync();
        } catch {
            alert('Failed to save some changes');
        }
    };

    const markAllPresent = () => {
        attState.roster = attState.roster.map(s => ({ ...s, status: 'present' }));
        attState.dirty = Object.fromEntries(attState.roster.map(s => [s.id, 'present']));
        renderRoster();
    };

    // Event wiring
    document.getElementById('att-class')?.addEventListener('change', (e) => {
        const id = e.target.value;
        attState.currentClass = id;
        attState.currentSession = null;
        attState.roster = [];
        renderRoster();
        if (id) loadSessions(id);
    });

    document.getElementById('att-session')?.addEventListener('change', (e) => {
        const id = e.target.value;
        attState.currentSession = id;
        if (id) loadRoster(id);
    });

    document.getElementById('start-session')?.addEventListener('click', startSession);
    document.getElementById('save-attendance')?.addEventListener('click', saveAttendance);
    document.getElementById('refresh-session')?.addEventListener('click', () => attState.currentSession && loadRoster(attState.currentSession));
    document.getElementById('export-session')?.addEventListener('click', () => alert('Export coming soon'));
    document.getElementById('mark-all-present')?.addEventListener('click', markAllPresent);

    // init
    loadClasses();
</script>
@endpush
