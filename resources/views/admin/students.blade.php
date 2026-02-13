@extends('layouts.admin')

@section('title', 'Finot | Students')
@section('page-label', 'Directory')
@section('page-title', 'Students')
@section('page-subtitle', 'Search students and review attendance history.')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 md:gap-6">
        <div class="lg:col-span-5 glass rounded-2xl p-4 md:p-6 shadow-glow">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Search</p>
                    <h3 class="text-lg text-white font-medium">Student directory</h3>
                </div>
                <button id="students-refresh" class="h-10 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center gap-2">
                    <i class="fas fa-rotate"></i><span class="text-sm">Refresh</span>
                </button>
            </div>

            <div class="relative mb-3">
                <input id="students-q" type="text" placeholder="Search by name or ID" class="w-full rounded-xl bg-white/5 border border-white/10 px-4 py-2.5 text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-neon/60" />
                <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            </div>

            <div class="flex items-center gap-3 mb-3">
                <div class="flex-1">
                    <label class="text-xs text-slate-400">Filter by class</label>
                    <select id="students-class" class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                        <option value="">All classes</option>
                    </select>
                </div>
            </div>

            <div id="students-list" class="space-y-2 min-h-[340px]">
                <p class="text-slate-400 text-sm">Type to search students.</p>
            </div>

            <div class="flex items-center justify-between mt-4">
                <button id="students-prev" class="h-10 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center gap-2" disabled>
                    <i class="fas fa-angle-left"></i><span class="text-sm">Prev</span>
                </button>
                <p class="text-xs text-slate-400" id="students-page">—</p>
                <button id="students-next" class="h-10 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center gap-2" disabled>
                    <span class="text-sm">Next</span><i class="fas fa-angle-right"></i>
                </button>
            </div>
        </div>

        <div class="lg:col-span-7 glass rounded-2xl p-4 md:p-6 shadow-glow">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Details</p>
                    <h3 class="text-lg text-white font-medium" id="student-name">Select a student</h3>
                    <p class="text-sm text-slate-400 mt-1" id="student-meta">Attendance summary and recent records.</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-400">ID</p>
                    <p class="text-white text-sm font-medium" id="student-id">—</p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                <div class="glass rounded-xl p-3 border border-white/5">
                    <p class="text-xs text-slate-400">Present (30d)</p>
                    <p class="text-white text-sm font-medium mt-1" id="student-present">—</p>
                </div>
                <div class="glass rounded-xl p-3 border border-white/5">
                    <p class="text-xs text-slate-400">Absent (30d)</p>
                    <p class="text-white text-sm font-medium mt-1" id="student-absent">—</p>
                </div>
                <div class="glass rounded-xl p-3 border border-white/5">
                    <p class="text-xs text-slate-400">Permission (30d)</p>
                    <p class="text-white text-sm font-medium mt-1" id="student-permission">—</p>
                </div>
                <div class="glass rounded-xl p-3 border border-white/5">
                    <p class="text-xs text-slate-400">Total (30d)</p>
                    <p class="text-white text-sm font-medium mt-1" id="student-total">—</p>
                </div>
            </div>

            <div class="mt-6">
                <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Recent</p>
                <div id="student-recent" class="mt-2 space-y-2 min-h-[260px]">
                    <p class="text-slate-400 text-sm">No student selected.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const sState = {
        q: '',
        classId: '',
        limit: 30,
        offset: 0,
        rows: [],
        selected: null,
        attendance: null,
        pending: 0,
        classes: [],
    };

    const renderList = () => {
        const wrap = document.getElementById('students-list');
        if (!sState.q && !sState.classId) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm">Type to search students.</p>';
            return;
        }
        if (!sState.rows.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm">No students found.</p>';
            return;
        }
        wrap.innerHTML = sState.rows.map(r => `
            <button class="w-full text-left glass rounded-xl px-4 py-3 border border-white/5 hover:bg-white/10 transition ${sState.selected && String(sState.selected.id) === String(r.id) ? 'bg-white/10' : ''}" data-student="${r.id}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-white">${r.full_name || 'Student'}</p>
                        <p class="text-xs text-slate-400">${r.gender ? r.gender : '—'}${r.current_grade ? ` • Grade ${r.current_grade}` : ''}</p>
                    </div>
                    <span class="text-xs text-slate-300">#${r.id}</span>
                </div>
            </button>
        `).join('');

        document.querySelectorAll('[data-student]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-student');
                const found = sState.rows.find(x => String(x.id) === String(id));
                if (found) selectStudent(found);
            });
        });
    };

    const renderPager = () => {
        document.getElementById('students-page').textContent = `${sState.offset + 1} - ${sState.offset + sState.rows.length}`;
        const active = !!(sState.q || sState.classId);
        document.getElementById('students-prev').disabled = !active || sState.offset === 0;
        document.getElementById('students-next').disabled = !active || sState.rows.length < sState.limit;
    };

    const renderDetail = () => {
        const s = sState.selected;
        if (!s) {
            document.getElementById('student-name').textContent = 'Select a student';
            document.getElementById('student-meta').textContent = 'Attendance summary and recent records.';
            document.getElementById('student-id').textContent = '—';
            document.getElementById('student-present').textContent = '—';
            document.getElementById('student-absent').textContent = '—';
            document.getElementById('student-permission').textContent = '—';
            document.getElementById('student-total').textContent = '—';
            document.getElementById('student-recent').innerHTML = '<p class="text-slate-400 text-sm">No student selected.</p>';
            return;
        }

        document.getElementById('student-name').textContent = s.full_name || 'Student';
        document.getElementById('student-id').textContent = s.id;
        document.getElementById('student-meta').textContent = `${s.gender ? s.gender : '—'}${s.current_grade ? ` • Grade ${s.current_grade}` : ''}`;

        const att = sState.attendance;
        if (!att) {
            document.getElementById('student-present').textContent = '—';
            document.getElementById('student-absent').textContent = '—';
            document.getElementById('student-permission').textContent = '—';
            document.getElementById('student-total').textContent = '—';
            document.getElementById('student-recent').innerHTML = '<p class="text-slate-400 text-sm">Loading attendance...</p>';
            return;
        }

        document.getElementById('student-present').textContent = att.summary.present;
        document.getElementById('student-absent').textContent = att.summary.absent;
        document.getElementById('student-permission').textContent = att.summary.permission;
        document.getElementById('student-total').textContent = att.summary.total;

        const pill = (st) => {
            const map = { present: 'text-neon', absent: 'text-slate-300', permission: 'text-mint' };
            return `<span class="px-2.5 py-1 rounded-full bg-white/5 text-xs ${map[st] || 'text-slate-300'}">${(st || '').toString().toUpperCase()}</span>`;
        };

        const rows = att.recent || [];
        const recentWrap = document.getElementById('student-recent');
        if (!rows.length) {
            recentWrap.innerHTML = '<p class="text-slate-400 text-sm">No attendance records.</p>';
            return;
        }

        recentWrap.innerHTML = rows.slice(0, 40).map(r => `
            <div class="glass rounded-xl px-4 py-3 border border-white/5 flex items-center justify-between gap-3">
                <div>
                    <p class="text-white text-sm">${r.attendance_date || '—'} • ${r.class_name || ''}</p>
                    <p class="text-xs text-slate-400">${r.workflow_status === 'submitted' ? 'Submitted' : 'Draft'}${r.note ? ` • ${r.note}` : ''}</p>
                </div>
                ${pill(r.status)}
            </div>
        `).join('');
    };

    const loadStudents = async () => {
        const my = ++sState.pending;
        if (!sState.q && !sState.classId) {
            sState.rows = [];
            renderList();
            renderPager();
            return;
        }

        document.getElementById('students-list').innerHTML = '<p class="text-slate-400 text-sm">Searching...</p>';
        try {
            const qp = new URLSearchParams();
            if (sState.q) qp.set('q', sState.q);
            if (sState.classId) qp.set('class_id', sState.classId);
            qp.set('limit', String(sState.limit));
            qp.set('offset', String(sState.offset));
            const url = `/api/v1/students?${qp.toString()}`;
            const res = await fetch(url);
            const json = await res.json();
            if (my !== sState.pending) return; // drop stale response
            sState.rows = json.data || [];
        } catch {
            sState.rows = [];
        }
        renderList();
        renderPager();
    };

    const selectStudent = async (student) => {
        sState.selected = student;
        sState.attendance = null;
        renderList();
        renderDetail();

        try {
            const qp = new URLSearchParams();
            qp.set('limit', '60');
            if (sState.classId) qp.set('class_id', sState.classId);
            const res = await fetch(`/api/v1/students/${student.id}/attendance?${qp.toString()}`);
            if (!res.ok) throw new Error('fail');
            sState.attendance = await res.json();
        } catch {
            sState.attendance = { summary: { present: 0, absent: 0, permission: 0, total: 0 }, recent: [] };
        }
        renderDetail();
    };

    const loadClasses = async () => {
        try {
            const res = await fetch('/api/v1/classes');
            const json = await res.json();
            const rows = (json.data || json || []);
            sState.classes = rows.map(c => ({
                id: c.id,
                name: c.name || `Grade ${c.grade || ''}${c.section || ''}`.trim(),
            }));
            document.getElementById('students-class').innerHTML =
                '<option value="">All classes</option>' +
                sState.classes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        } catch {
            // leave as default
        }
    };

    let t = null;
    document.getElementById('students-q')?.addEventListener('input', (e) => {
        clearTimeout(t);
        t = setTimeout(() => {
            sState.q = (e.target.value || '').trim();
            sState.offset = 0;
            loadStudents();
        }, 250);
    });

    document.getElementById('students-class')?.addEventListener('change', (e) => {
        sState.classId = e.target.value || '';
        sState.offset = 0;
        sState.selected = null;
        sState.attendance = null;
        renderDetail();
        loadStudents();
    });

    document.getElementById('students-refresh')?.addEventListener('click', () => loadStudents());
    document.getElementById('students-prev')?.addEventListener('click', () => {
        if (sState.offset === 0) return;
        sState.offset = Math.max(0, sState.offset - sState.limit);
        loadStudents();
    });
    document.getElementById('students-next')?.addEventListener('click', () => {
        sState.offset = sState.offset + sState.limit;
        loadStudents();
    });

    renderPager();
    renderDetail();
    loadClasses();
</script>
@endpush
