@extends('layouts.admin')

@section('title', 'Finot | Attendance')
@section('page-label', 'Review')
@section('page-title', 'Attendance')
@section('page-subtitle', 'Select a class, then pick a date to view or edit attendance.')

@push('head')
<style>
    /* Horizontal mobile scroller without visible scrollbar */
    .no-scrollbar { scrollbar-width: none; -ms-overflow-style: none; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
</style>
@endpush

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 md:gap-6">
        <div class="lg:col-span-4 glass rounded-2xl p-4 md:p-6 shadow-glow space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Step 1</p>
                    <h3 class="text-lg text-white font-medium">Pick Class</h3>
                </div>
                <button id="att-refresh-classes" type="button" class="h-10 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center gap-2">
                    <i class="fas fa-rotate"></i><span class="text-sm">Refresh</span>
                </button>
            </div>

            <div>
                <label class="text-xs text-slate-400">Class</label>
                <select id="att-class" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option value="">Loading...</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-400">From</label>
                    <input id="att-from" type="date" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" />
                </div>
                <div>
                    <label class="text-xs text-slate-400">To</label>
                    <input id="att-to" type="date" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" />
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Step 2</p>
                    <h3 class="text-lg text-white font-medium">Pick Date</h3>
                </div>
                <button id="att-refresh-sessions" type="button" class="h-9 px-3 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center gap-2" disabled>
                    <i class="fas fa-rotate"></i><span class="text-xs">Refresh</span>
                </button>
            </div>

            <div id="att-session-list" class="flex gap-2 overflow-x-auto no-scrollbar py-1 lg:block lg:space-y-2 lg:overflow-visible lg:py-0 min-h-[96px] lg:min-h-[320px]">
                <p class="text-slate-400 text-sm">Select a class to load sessions.</p>
            </div>
        </div>

        <div class="lg:col-span-8 glass rounded-2xl p-4 md:p-6 shadow-glow flex flex-col gap-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Details</p>
                    <h3 class="text-lg text-white font-medium" id="att-title">Select a session</h3>
                    <p class="text-sm text-slate-400 mt-1" id="att-subtitle">Pick a class and date to view attendance.</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-400">Last refresh</p>
                    <p class="text-white text-sm" id="last-sync">--</p>
                </div>
            </div>

            <div id="selected-chip" class="hidden lg:flex items-center justify-between gap-3 glass rounded-xl p-3 border border-white/5">
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full bg-white/5 text-xs text-slate-200" id="chip-class">Class: --</span>
                    <span class="px-3 py-1 rounded-full bg-white/5 text-xs text-slate-200" id="chip-date">Date: --</span>
                    <span class="px-3 py-1 rounded-full bg-white/5 text-xs text-slate-200" id="chip-workflow">--</span>
                </div>
                <span class="text-xs text-slate-400">Tip: On mobile, use the bottom bar for actions.</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="glass rounded-xl p-3 border border-white/5">
                    <p class="text-xs text-slate-400">Present</p>
                    <p class="text-2xl text-white font-medium" id="stat-present">0</p>
                </div>
                <div class="glass rounded-xl p-3 border border-white/5">
                    <p class="text-xs text-slate-400">Permission</p>
                    <p class="text-2xl text-white font-medium" id="stat-permission">0</p>
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

            <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                <div class="relative flex-1">
                    <input id="att-student-search" type="text" placeholder="Search students" class="w-full rounded-xl bg-white/5 border border-white/10 px-4 py-3 text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-neon/60" disabled />
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                </div>

                <div class="flex items-center gap-2 sm:justify-end">
                    <button id="export-csv" type="button" class="h-11 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center justify-center gap-2" disabled>
                        <i class="fas fa-file-csv text-amber-300"></i><span class="hidden sm:inline text-sm">CSV</span>
                    </button>

                    <label class="h-11 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring inline-flex items-center gap-2 select-none disabled:opacity-40 disabled:cursor-not-allowed"
                           id="edit-wrap">
                        <input id="edit-toggle" type="checkbox" class="h-5 w-5 accent-cyan-300" disabled />
                        <span class="hidden sm:inline text-sm">Edit</span>
                        <i class="fas fa-pen-to-square sm:hidden"></i>
                    </label>

                    <button id="delete-session" type="button" class="h-11 px-4 rounded-xl glass text-red-200 hover:text-white transition shadow-ring flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-trash"></i><span class="hidden sm:inline text-sm">Delete</span>
                    </button>
                </div>
            </div>

            <div class="glass rounded-2xl border border-white/5 overflow-hidden">
                <div class="hidden lg:grid grid-cols-12 px-4 py-3 bg-white/5 text-xs text-slate-300">
                    <span class="col-span-6">Student</span>
                    <span class="col-span-2">Status</span>
                    <span class="col-span-4 text-right">Actions</span>
                </div>
                <div id="att-roster" class="divide-y divide-white/5 min-h-[260px] max-h-[56vh] overflow-auto lg:max-h-none lg:overflow-visible">
                    <p class="text-slate-400 text-sm px-4 py-3">Select a session to view roster.</p>
                </div>
            </div>

            <div class="hidden lg:grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button id="save-attendance" type="button" class="h-11 px-4 rounded-xl neon-pill text-sm font-medium flex items-center justify-center gap-2" disabled>
                    <i class="fas fa-floppy-disk"></i><span>Save</span>
                </button>
                <button id="refresh-roster" type="button" class="h-11 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center justify-center gap-2" disabled>
                    <i class="fas fa-rotate text-primary"></i><span>Refresh</span>
                </button>
            </div>

            <div id="lock-banner" class="hidden glass rounded-xl p-4 border border-white/5">
                <p class="text-white text-sm font-medium">This attendance is locked</p>
                <p class="text-slate-400 text-sm mt-1" id="lock-banner-copy">Submitted more than 7 days ago.</p>
            </div>
        </div>
    </div>

    {{-- Mobile bottom actions bar (fixed) --}}
    <div class="lg:hidden h-24"></div>
    <div id="att-mobile-bar" class="lg:hidden fixed left-0 right-0 bottom-0 z-50">
        <div class="mx-auto max-w-3xl px-3" style="padding-bottom: env(safe-area-inset-bottom);">
            <div class="glass rounded-2xl border border-white/10 shadow-glow px-2 py-2">
                <div class="grid grid-cols-5 gap-1">
                    <button id="m-att-csv" type="button" class="h-12 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex flex-col items-center justify-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-file-csv text-amber-300"></i>
                        <span class="text-[11px] leading-none">CSV</span>
                    </button>
                    <button id="m-att-edit" type="button" class="h-12 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex flex-col items-center justify-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-pen-to-square"></i>
                        <span class="text-[11px] leading-none">Edit</span>
                    </button>
                    <button id="m-att-save" type="button" class="h-12 rounded-xl neon-pill text-sm font-medium flex flex-col items-center justify-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-floppy-disk"></i>
                        <span class="text-[11px] leading-none">Save</span>
                    </button>
                    <button id="m-att-refresh" type="button" class="h-12 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex flex-col items-center justify-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-rotate text-primary"></i>
                        <span class="text-[11px] leading-none">Refresh</span>
                    </button>
                    <button id="m-att-delete" type="button" class="h-12 rounded-xl glass text-red-200 hover:text-white transition shadow-ring flex flex-col items-center justify-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-trash"></i>
                        <span class="text-[11px] leading-none">Delete</span>
                    </button>
                </div>
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
        rosterFiltered: [],
        currentClass: null,
        currentSession: null,
        dirty: {},
        locked: false,
        editableUntil: null,
        workflowStatus: null,
        editMode: false,
    };

    const setSync = () => {
        const nowIso = new Date().toISOString();
        document.getElementById('last-sync').textContent = window.FinotDate ? window.FinotDate.formatDateTime(nowIso) : new Date().toLocaleString();
    };

    const fmtDate = (d) => {
        if (!d) return '';
        const s = String(d);
        const ymd = s.length >= 10 ? s.slice(0, 10) : s;
        return window.FinotDate ? window.FinotDate.formatDate(ymd) : ymd;
    };

    const showLock = (locked, editableUntil) => {
        const banner = document.getElementById('lock-banner');
        const copy = document.getElementById('lock-banner-copy');
        if (!locked) { banner.classList.add('hidden'); return; }
        banner.classList.remove('hidden');
        copy.textContent = editableUntil ? `Locked after ${editableUntil}.` : 'Submitted more than 7 days ago.';
    };

    const statusPill = (status) => {
        const palette = { present: 'text-neon', permission: 'text-mint', absent: 'text-slate-300', unmarked: 'text-amber-300' };
        const copy = (status || 'absent').toString();
        const label = copy === 'unmarked' ? 'Unmarked' : (copy.charAt(0).toUpperCase() + copy.slice(1));
        return `<span class="px-3 py-1 rounded-full bg-white/5 ${palette[copy] || ''} text-xs">${label}</span>`;
    };

    const computeStats = () => {
        const total = attState.rosterFiltered.length;
        const present = attState.rosterFiltered.filter(s => s.display_status === 'present').length;
        const permission = attState.rosterFiltered.filter(s => s.display_status === 'permission').length;
        const absent = attState.rosterFiltered.filter(s => s.display_status === 'absent').length;
        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-present').textContent = present;
        document.getElementById('stat-permission').textContent = permission;
        document.getElementById('stat-absent').textContent = absent;
    };

    const updateEditAvailability = () => {
        const toggle = document.getElementById('edit-toggle');
        const canEdit = attState.currentSession
            && (String(attState.workflowStatus || '').toLowerCase() === 'submitted')
            && !attState.locked;

        toggle.disabled = !canEdit;
        if (!canEdit) {
            toggle.checked = false;
            attState.editMode = false;
        }

        document.getElementById('save-attendance').disabled = !attState.editMode || Object.keys(attState.dirty).length === 0;
        syncMobileBar();
    };

    const markDirty = (studentId, status) => {
        attState.dirty[String(studentId)] = status;
        document.getElementById('save-attendance').disabled = !attState.editMode || Object.keys(attState.dirty).length === 0;
        syncMobileBar();
    };

    const syncMobileBar = () => {
        const byId = (id) => document.getElementById(id);
        const csv = byId('export-csv');
        const edit = byId('edit-toggle');
        const save = byId('save-attendance');
        const refresh = byId('refresh-roster');
        const del = byId('delete-session');

        const mCsv = byId('m-att-csv');
        const mEdit = byId('m-att-edit');
        const mSave = byId('m-att-save');
        const mRefresh = byId('m-att-refresh');
        const mDelete = byId('m-att-delete');

        if (!mCsv || !csv) return;

        mCsv.disabled = !!csv.disabled;
        mSave.disabled = !!save.disabled;
        mRefresh.disabled = !!refresh.disabled;
        mDelete.disabled = !!del.disabled;

        mEdit.disabled = !!edit.disabled;
        mEdit.classList.toggle('ring-2', !!edit.checked);
        mEdit.classList.toggle('ring-neon/60', !!edit.checked);
    };

    const renderRoster = () => {
        const wrap = document.getElementById('att-roster');
        if (!attState.currentSession) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Select a session to view roster.</p>';
            attState.rosterFiltered = [];
            computeStats();
            syncMobileBar();
            return;
        }

        if (!attState.rosterFiltered.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No students found.</p>';
            computeStats();
            syncMobileBar();
            return;
        }

        wrap.innerHTML = attState.rosterFiltered.map(student => `
            <div class="grid grid-cols-1 lg:grid-cols-12 px-4 py-3 gap-3 items-center">
                <div class="lg:col-span-6">
                    <p class="text-white">${student.name}</p>
                    <p class="text-xs text-slate-400">ID: ${student.id}</p>
                </div>
                <div class="lg:col-span-2">${statusPill(student.display_status)}</div>
                <div class="lg:col-span-4 flex flex-wrap gap-2 justify-start lg:justify-end">
                    ${attState.editMode ? ['present','permission','absent'].map(state => `
                        <button type="button" data-student="${student.id}" data-status="${state}" class="px-3 py-1 rounded-lg bg-white/5 text-xs ${student.status===state?'text-neon':'text-slate-200'} hover:text-white transition">
                            ${state[0].toUpperCase() + state.slice(1)}
                        </button>`).join('') : '<span class="text-xs text-slate-400">View only</span>'}
                </div>
            </div>
        `).join('');

        if (attState.editMode) {
            document.querySelectorAll('[data-student][data-status]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const sid = btn.getAttribute('data-student');
                    const status = btn.getAttribute('data-status');
                    const target = attState.roster.find(s => String(s.id) === String(sid));
                    if (!target) return;
                    target.status = status;
                    target.display_status = status;
                    applyStudentFilter();
                    markDirty(sid, status);
                });
            });
        }

        computeStats();
        syncMobileBar();
    };

    const renderSessions = () => {
        const wrap = document.getElementById('att-session-list');
        if (!attState.currentClass) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm">Select a class to load sessions.</p>';
            return;
        }
        if (!attState.sessions.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm">No sessions found for this class in the selected range.</p>';
            return;
        }
        const pill = (s) => {
            const st = (s.workflow_status || 'draft').toLowerCase();
            const map = { draft: 'text-amber-300', submitted: 'text-mint' };
            return `<span class="px-2.5 py-1 rounded-full bg-white/5 text-xs ${map[st] || 'text-slate-300'}">${st.toUpperCase()}</span>`;
        };
        wrap.innerHTML = attState.sessions.map(s => `
            <button type="button" class="shrink-0 w-64 lg:w-full text-left glass rounded-xl px-4 py-4 border border-white/5 hover:bg-white/10 transition ${attState.currentSession && String(attState.currentSession.id) === String(s.id) ? 'bg-white/10' : ''}" data-session="${s.id}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-white">${fmtDate(s.attendance_date) || '--'}</p>
                        <p class="text-xs text-slate-400">Session #${s.id}</p>
                    </div>
                    ${pill(s)}
                </div>
            </button>
        `).join('');

        document.querySelectorAll('[data-session]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-session');
                const s = attState.sessions.find(x => String(x.id) === String(id));
                if (s) selectSession(s);
            });
        });
    };

    const applyStudentFilter = () => {
        const q = (document.getElementById('att-student-search').value || '').toLowerCase().trim();
        attState.rosterFiltered = attState.roster.filter(s => (s.name || '').toLowerCase().includes(q));
        renderRoster();
    };

    const loadClasses = async () => {
        const select = document.getElementById('att-class');
        select.innerHTML = '<option value="">Loading...</option>';

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

        select.innerHTML = '<option value="">Select class</option>' + attState.classes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    };

    const loadSessions = async (classId) => {
        document.getElementById('att-session-list').innerHTML = '<p class="text-slate-400 text-sm">Loading sessions...</p>';
        const from = document.getElementById('att-from').value;
        const to = document.getElementById('att-to').value;
        const qs = new URLSearchParams();
        if (from) qs.set('from', from);
        if (to) qs.set('to', to);
        // Admin view: submitted only.
        qs.set('workflow_status', 'submitted');
        const url = `/api/v1/classes/${classId}/sessions` + (qs.toString() ? `?${qs.toString()}` : '');

        try {
            const res = await fetch(url);
            const json = await res.json();
            const sessions = Array.isArray(json?.data) ? json.data : (Array.isArray(json) ? json : []);
            attState.sessions = sessions.map(s => ({
                id: s.id,
                attendance_date: s.attendance_date || null,
                workflow_status: s.workflow_status || 'draft',
            }));
        } catch {
            attState.sessions = [];
        }

        renderSessions();
        setSync();
        syncMobileBar();
    };

    const loadRoster = async (sessionId) => {
        document.getElementById('att-roster').innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Loading roster...</p>';
        attState.roster = [];
        attState.rosterFiltered = [];
        attState.dirty = {};
        document.getElementById('save-attendance').disabled = true;

        try {
            const res = await fetch(`/api/v1/sessions/${sessionId}/students`);
            const json = await res.json();
            attState.locked = !!json.session?.locked;
            attState.editableUntil = json.session?.editable_until || null;
            attState.workflowStatus = json.session?.workflow_status || null;
            showLock(attState.locked, attState.editableUntil);

            const wf = String(attState.workflowStatus || 'draft').toLowerCase();
            const rows = json.students || [];

            attState.roster = rows.map(s => {
                const has = !!s.attendance_status;
                const status = has ? s.attendance_status : 'absent';
                const display = has ? s.attendance_status : (wf === 'submitted' ? 'absent' : 'unmarked');
                return {
                    id: s.student_id,
                    name: s.full_name,
                    status,
                    display_status: display,
                };
            });
            attState.rosterFiltered = attState.roster;
        } catch {
            attState.roster = [];
            attState.rosterFiltered = [];
        }

        document.getElementById('att-student-search').disabled = false;
        document.getElementById('export-csv').disabled = false;
        document.getElementById('refresh-roster').disabled = false;
        document.getElementById('delete-session').disabled = false;

        updateEditAvailability();
        renderRoster();
        setSync();
        syncMobileBar();
    };

    const selectClass = async (classId) => {
        const found = attState.classes.find(c => String(c.id) === String(classId));
        attState.currentClass = found || null;

        attState.currentSession = null;
        attState.sessions = [];
        attState.roster = [];
        attState.rosterFiltered = [];
        attState.dirty = {};
        attState.locked = false;
        attState.editableUntil = null;
        attState.workflowStatus = null;
        attState.editMode = false;
        document.getElementById('edit-toggle').checked = false;
        showLock(false, null);

        document.getElementById('att-title').textContent = 'Select a session';
        document.getElementById('att-subtitle').textContent = found ? `Class: ${found.name}` : 'Pick a class and date to view attendance.';
        document.getElementById('att-roster').innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Select a session to view roster.</p>';
        computeStats();

        document.getElementById('att-refresh-sessions').disabled = !found;
        document.getElementById('delete-session').disabled = true;
        syncMobileBar();

        if (found) {
            await loadSessions(found.id);
            if (attState.sessions.length) {
                await selectSession(attState.sessions[0]);
            }
        } else {
            document.getElementById('att-session-list').innerHTML = '<p class="text-slate-400 text-sm">Select a class to load sessions.</p>';
        }
        syncMobileBar();
    };

    const selectSession = async (s) => {
        attState.currentSession = s;
        attState.editMode = false;
        document.getElementById('edit-toggle').checked = false;
        renderSessions();

        const className = attState.currentClass ? attState.currentClass.name : '';
        const date = fmtDate(s.attendance_date);
        const wf = (s.workflow_status || '').toString().toUpperCase();

        document.getElementById('att-title').textContent = className || 'Attendance';
        document.getElementById('att-subtitle').textContent = `${className} | ${date} | ${wf}`;
        // Selected chips (desktop helper)
        document.getElementById('selected-chip').classList.toggle('hidden', !className);
        document.getElementById('chip-class').textContent = `Class: ${className || '--'}`;
        document.getElementById('chip-date').textContent = `Date: ${date || '--'}`;
        document.getElementById('chip-workflow').textContent = wf || '--';

        await loadRoster(s.id);
        syncMobileBar();

        // Scroll details into view after selecting a session on mobile.
        if (window.innerWidth < 1024) {
            setTimeout(() => document.getElementById('att-title')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
        }
    };

    const saveAttendance = async () => {
        if (!attState.currentSession) return;
        if (!attState.editMode) return;
        if (attState.locked) return alert('Attendance is locked (submitted more than 7 days ago)');
        const entries = Object.entries(attState.dirty);
        if (!entries.length) return;

        try {
            const updates = entries.map(([studentId, status]) => ({ student_id: Number(studentId), status }));
            const res = await fetch(`/api/v1/sessions/${attState.currentSession.id}/students`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ updates })
            });
            if (!res.ok) throw new Error('fail');
            attState.dirty = {};
            document.getElementById('save-attendance').disabled = true;
            setSync();
            syncMobileBar();
        } catch {
            alert('Failed to save some changes');
        }
    };

    const exportCsv = async () => {
        if (!attState.currentSession) return;
        try {
            const res = await fetch(`/api/v1/sessions/${attState.currentSession.id}/export`, { headers: { 'Accept': 'text/csv' } });
            if (!res.ok) throw new Error('export failed');
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const dispo = res.headers.get('Content-Disposition') || '';
            const m = dispo.match(/filename=\"?([^\";]+)\"?/i);
            a.download = m ? m[1] : `attendance_session_${attState.currentSession.id}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch {
            alert('Export failed');
        }
    };

    const deleteSession = async () => {
        if (!attState.currentSession || !attState.currentClass) return;
        const className = attState.currentClass.name || 'Class';
        const date = fmtDate(attState.currentSession.attendance_date) || '--';
        if (!confirm(`Delete attendance for ${className} on ${date}?\n\nThis will delete the session and all student marks.`)) return;

        try {
            const res = await fetch(`/api/v1/sessions/${attState.currentSession.id}`, { method: 'DELETE' });
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Delete failed');

            // Clear current view then reload sessions.
            attState.currentSession = null;
            attState.roster = [];
            attState.rosterFiltered = [];
            attState.dirty = {};
            document.getElementById('att-title').textContent = 'Select a session';
            document.getElementById('att-subtitle').textContent = 'Pick a class and date to view attendance.';
            document.getElementById('att-student-search').value = '';
            document.getElementById('att-student-search').disabled = true;
            document.getElementById('export-csv').disabled = true;
            document.getElementById('refresh-roster').disabled = true;
            document.getElementById('delete-session').disabled = true;
            document.getElementById('edit-toggle').checked = false;
            document.getElementById('edit-toggle').disabled = true;
            document.getElementById('save-attendance').disabled = true;
            showLock(false, null);
            document.getElementById('att-roster').innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Select a session to view roster.</p>';
            computeStats();
            syncMobileBar();

            await loadSessions(attState.currentClass.id);
        } catch (e) {
            alert(e?.message || 'Delete failed');
        }
    };

    document.getElementById('att-student-search')?.addEventListener('input', applyStudentFilter);
    document.getElementById('att-refresh-classes')?.addEventListener('click', loadClasses);
    document.getElementById('att-refresh-sessions')?.addEventListener('click', () => attState.currentClass && loadSessions(attState.currentClass.id));
    document.getElementById('att-from')?.addEventListener('change', () => attState.currentClass && loadSessions(attState.currentClass.id));
    document.getElementById('att-to')?.addEventListener('change', () => attState.currentClass && loadSessions(attState.currentClass.id));
    document.getElementById('save-attendance')?.addEventListener('click', saveAttendance);
    document.getElementById('refresh-roster')?.addEventListener('click', () => attState.currentSession && loadRoster(attState.currentSession.id));
    document.getElementById('export-csv')?.addEventListener('click', exportCsv);
    document.getElementById('delete-session')?.addEventListener('click', deleteSession);

    document.getElementById('edit-toggle')?.addEventListener('change', (e) => {
        attState.editMode = !!e.target.checked;
        if (!attState.editMode) {
            attState.dirty = {};
            document.getElementById('save-attendance').disabled = true;
        }
        renderRoster();
        syncMobileBar();
    });

    document.getElementById('att-class')?.addEventListener('change', (e) => selectClass(e.target.value));


    const boot = async () => {
        const today = new Date().toISOString().slice(0,10);
        document.getElementById('att-to').value = today;
        document.getElementById('att-from').value = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0,10);

        await loadClasses();

        const params = new URLSearchParams(window.location.search);
        const classId = params.get('class_id');
        const sessionId = params.get('session_id');

        if (classId) {
            document.getElementById('att-class').value = classId;
            await selectClass(classId);
            if (sessionId) {
                const ses = attState.sessions.find(s => String(s.id) === String(sessionId));
                if (ses) await selectSession(ses);
            }
        }

        // Mobile bar wiring (mirror desktop controls).
        document.getElementById('m-att-csv')?.addEventListener('click', () => document.getElementById('export-csv')?.click());
        document.getElementById('m-att-save')?.addEventListener('click', () => document.getElementById('save-attendance')?.click());
        document.getElementById('m-att-refresh')?.addEventListener('click', () => document.getElementById('refresh-roster')?.click());
        document.getElementById('m-att-delete')?.addEventListener('click', () => document.getElementById('delete-session')?.click());
        document.getElementById('m-att-edit')?.addEventListener('click', () => {
            const t = document.getElementById('edit-toggle');
            if (!t || t.disabled) return;
            t.checked = !t.checked;
            t.dispatchEvent(new Event('change'));
        });

        syncMobileBar();
    };

    document.addEventListener('finot:dateprefs', () => {
        renderSessions();
        if (attState.currentSession) {
            const className = attState.currentClass ? attState.currentClass.name : '';
            const date = fmtDate(attState.currentSession.attendance_date);
            const wf = (attState.currentSession.workflow_status || '').toString().toUpperCase();
            document.getElementById('att-subtitle').textContent = `${className} | ${date} | ${wf}`;
            document.getElementById('chip-date').textContent = `Date: ${date || '--'}`;
            renderRoster();
        }
        setSync();
    });

    boot();
</script>
@endpush
