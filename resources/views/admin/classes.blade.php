@extends('layouts.admin')

@section('title', 'Finot | Classes')
@section('page-label', 'Cohorts')
@section('page-title', 'Classes')
@section('page-subtitle', 'Browse classes, open rosters, and review student attendance.')

@push('head')
<style>
    .no-scrollbar { scrollbar-width: none; -ms-overflow-style: none; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
</style>
@endpush

@section('content')
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 md:gap-4">
        <div class="glass card-accent rounded-xl p-3 shadow-glow">
            <p class="text-slate-300 text-xs mb-1">Classes</p>
            <p class="text-2xl text-white font-medium leading-none" id="stat-total">—</p>
            <p class="text-xs text-slate-400 mt-1">Total in roster</p>
        </div>
        <div class="glass card-accent rounded-xl p-3 shadow-glow">
            <p class="text-slate-300 text-xs mb-1">Active Sessions</p>
            <p class="text-2xl text-white font-medium leading-none" id="stat-active">—</p>
            <p class="text-xs text-slate-400 mt-1">Classes with an open session</p>
        </div>
        <div class="glass card-accent rounded-xl p-3 shadow-glow">
            <p class="text-slate-300 text-xs mb-1">Teachers</p>
            <p class="text-2xl text-white font-medium leading-none" id="stat-teachers">—</p>
            <p class="text-xs text-slate-400 mt-1">Assigned owners</p>
        </div>
        <div class="glass card-accent rounded-xl p-3 shadow-glow">
            <p class="text-slate-300 text-xs mb-1">Avg Attendance</p>
            <p class="text-2xl text-white font-medium leading-none" id="stat-attendance">—</p>
            <p class="text-xs text-slate-400 mt-1">Last 30 days (present rate)</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 md:gap-4 mt-4">
        <div class="lg:col-span-5 glass rounded-xl p-3 md:p-4 shadow-glow">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Browse</p>
                    <h3 class="text-lg text-white font-medium">Class list</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button id="classes-refresh" class="h-9 px-3 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring flex items-center gap-2">
                        <i class="fas fa-rotate"></i><span class="text-sm">Refresh</span>
                    </button>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 mb-3">
                <div class="relative flex-1">
                    <input id="class-search" type="text" placeholder="Search by class or teacher" class="w-full rounded-lg bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-neon/60" />
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                </div>
                <select id="class-sort" class="rounded-lg bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option value="grade">Sort: Grade</option>
                    <option value="students">Sort: Students</option>
                    <option value="attendance">Sort: Attendance</option>
                </select>
            </div>

            <div class="glass rounded-xl border border-white/5 overflow-hidden">
                <div class="hidden lg:grid grid-cols-12 px-3 py-2 bg-white/5 text-[11px] text-slate-300 sticky top-0 z-10">
                    <span class="col-span-7">Class</span>
                    <span class="col-span-3">Students</span>
                    <span class="col-span-2 text-right">30d</span>
                </div>
                <div id="class-list" class="divide-y divide-white/5 min-h-[220px] max-h-[62vh] overflow-y-auto no-scrollbar">
                    <p class="text-slate-400 text-sm px-4 py-3">Loading classes...</p>
                </div>
            </div>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
                <span id="class-page-summary">0 classes</span>
                <div class="flex items-center gap-2">
                    <label for="class-page-size" class="text-slate-400">Rows</label>
                    <select id="class-page-size" class="h-8 rounded-md bg-white/5 border border-white/10 px-2 text-xs text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="40">40</option>
                        <option value="80">80</option>
                    </select>
                    <button id="class-page-prev" class="h-8 px-2.5 rounded-md glass text-slate-200 hover:text-white transition disabled:opacity-40 disabled:cursor-not-allowed" type="button">Prev</button>
                    <span id="class-page-indicator" class="min-w-[64px] text-center">1 / 1</span>
                    <button id="class-page-next" class="h-8 px-2.5 rounded-md glass text-slate-200 hover:text-white transition disabled:opacity-40 disabled:cursor-not-allowed" type="button">Next</button>
                </div>
            </div>
        </div>

        <div class="lg:col-span-7 space-y-3 md:space-y-4">
            <div class="glass rounded-xl p-3 md:p-4 shadow-glow">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Selected</p>
                        <h3 class="text-lg text-white font-medium" id="selected-class-name">Select a class</h3>
                        <p class="text-sm text-slate-400 mt-1" id="selected-class-meta">Roster and attendance details appear here.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a id="jump-attendance" href="{{ route('admin.attendance') }}" class="h-9 px-3 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring flex items-center gap-2">
                            <i class="fas fa-calendar-check"></i><span class="text-sm">Attendance</span>
                        </a>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3">
                    <div class="glass rounded-lg p-2.5 border border-white/5">
                        <p class="text-xs text-slate-400">Teacher</p>
                        <p class="text-white text-sm font-medium mt-1" id="selected-class-teacher">—</p>
                    </div>
                    <div class="glass rounded-lg p-2.5 border border-white/5">
                        <p class="text-xs text-slate-400">Students</p>
                        <p class="text-white text-sm font-medium mt-1" id="selected-class-students">—</p>
                    </div>
                    <div class="glass rounded-lg p-2.5 border border-white/5">
                        <p class="text-xs text-slate-400">Status</p>
                        <p class="text-white text-sm font-medium mt-1" id="selected-class-status">—</p>
                    </div>
                    <div class="glass rounded-lg p-2.5 border border-white/5">
                        <p class="text-xs text-slate-400">30d Present</p>
                        <p class="text-white text-sm font-medium mt-1" id="selected-class-attendance">—</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-3 md:gap-4">
                <div class="glass rounded-xl p-3 md:p-4 shadow-glow">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Roster</p>
                            <h3 class="text-lg text-white font-medium">Students</h3>
                        </div>
                        <span id="roster-count" class="text-xs text-slate-400">—</span>
                    </div>
                    <div class="relative mb-2">
                        <input id="student-search" type="text" placeholder="Search students" class="w-full rounded-lg bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-neon/60" disabled />
                        <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    </div>
                    <div id="roster-list" class="space-y-1.5 min-h-[220px] max-h-[52vh] overflow-y-auto no-scrollbar">
                        <p class="text-slate-400 text-sm">Select a class to load students.</p>
                    </div>
                </div>

                <div class="glass rounded-xl p-3 md:p-4 shadow-glow">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Student</p>
                            <h3 class="text-lg text-white font-medium" id="student-name">Select a student</h3>
                            <p class="text-sm text-slate-400 mt-1" id="student-meta">Attendance summary and recent days.</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-400">ID</p>
                            <p class="text-white text-sm font-medium" id="student-id">—</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 mt-3">
                        <div class="glass rounded-lg p-2.5 border border-white/5">
                            <p class="text-xs text-slate-400">Present (30d)</p>
                            <p class="text-white text-sm font-medium mt-1" id="student-present">—</p>
                        </div>
                        <div class="glass rounded-lg p-2.5 border border-white/5">
                            <p class="text-xs text-slate-400">Absent (30d)</p>
                            <p class="text-white text-sm font-medium mt-1" id="student-absent">—</p>
                        </div>
                        <div class="glass rounded-lg p-2.5 border border-white/5">
                            <p class="text-xs text-slate-400">Permission (30d)</p>
                            <p class="text-white text-sm font-medium mt-1" id="student-permission">—</p>
                        </div>
                        <div class="glass rounded-lg p-2.5 border border-white/5">
                            <p class="text-xs text-slate-400">Total (30d)</p>
                            <p class="text-white text-sm font-medium mt-1" id="student-total">—</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Recent</p>
                        <div id="student-recent" class="mt-2 space-y-1.5 min-h-[120px] max-h-[35vh] overflow-y-auto no-scrollbar">
                            <p class="text-slate-400 text-sm">No student selected.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const clsState = {
        classes: [],
        filtered: [],
        selectedClass: null,
        classPage: 1,
        classPageSize: 20,
        roster: [],
        rosterFiltered: [],
        selectedStudent: null,
        studentAttendance: null,
    };

    const fmtPct = (v) => {
        if (v === null || typeof v === 'undefined') return '—';
        const n = Number(v);
        if (Number.isNaN(n)) return '—';
        return `${n}%`;
    };

    const badge = (status) => {
        const norm = (status || '').toString().toLowerCase();
        const map = {
            active: { text: 'Active', cls: 'text-neon' },
            inactive: { text: 'Inactive', cls: 'text-slate-300' },
            archived: { text: 'Archived', cls: 'text-slate-400' },
        };
        const b = map[norm] || { text: status || '—', cls: 'text-slate-300' };
        return `<span class="text-sm ${b.cls}">${b.text}</span>`;
    };

    const renderStats = () => {
        document.getElementById('stat-total').textContent = clsState.classes.length;
        document.getElementById('stat-active').textContent = clsState.classes.filter(c => (c.status || '').toLowerCase() === 'active').length;

        const teacherSet = new Set(clsState.classes.map(c => (c.teacher_name || '').trim()).filter(t => t && t.toLowerCase() !== 'unassigned'));
        document.getElementById('stat-teachers').textContent = teacherSet.size;

        const rates = clsState.classes.map(c => Number(c.attendance_rate)).filter(n => !Number.isNaN(n));
        const avg = rates.length ? (rates.reduce((s, v) => s + v, 0) / rates.length) : null;
        document.getElementById('stat-attendance').textContent = avg === null ? '—' : `${Math.round(avg)}%`;
    };

    const renderClassList = () => {
        const list = document.getElementById('class-list');
        const total = clsState.filtered.length;
        const pageSize = Math.max(1, Number(clsState.classPageSize) || 20);
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        clsState.classPage = Math.min(Math.max(1, clsState.classPage), totalPages);
        const startIdx = (clsState.classPage - 1) * pageSize;
        const pageRows = clsState.filtered.slice(startIdx, startIdx + pageSize);

        const summary = document.getElementById('class-page-summary');
        const indicator = document.getElementById('class-page-indicator');
        const prev = document.getElementById('class-page-prev');
        const next = document.getElementById('class-page-next');
        if (summary) {
            if (!total) {
                summary.textContent = '0 classes';
            } else {
                const to = Math.min(startIdx + pageRows.length, total);
                summary.textContent = `Showing ${startIdx + 1}-${to} of ${total}`;
            }
        }
        if (indicator) indicator.textContent = `${clsState.classPage} / ${totalPages}`;
        if (prev) prev.disabled = clsState.classPage <= 1;
        if (next) next.disabled = clsState.classPage >= totalPages;

        if (!total) {
            list.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No classes found.</p>';
            return;
        }

        list.innerHTML = pageRows.map(c => `
            <button class="w-full text-left grid grid-cols-1 lg:grid-cols-12 px-3 py-2 gap-2 lg:gap-0 hover:bg-white/5 transition ${clsState.selectedClass && String(clsState.selectedClass.id) === String(c.id) ? 'bg-white/10' : ''}" data-class="${c.id}">
                <div class="lg:col-span-7 flex items-start gap-2.5 min-w-0">
                    <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-primary via-neon to-mint flex items-center justify-center text-midnight text-xs">
                        ${(c.grade ? `G${c.grade}` : 'C').slice(0,2)}
                    </div>
                    <div>
                        <p class="text-white">${c.name}</p>
                        <p class="text-xs text-slate-400">${c.teacher_name || 'Unassigned'}</p>
                    </div>
                </div>
                <div class="lg:col-span-3 text-slate-200 flex items-center text-sm">${c.students_count ?? 0}</div>
                <div class="lg:col-span-2 flex lg:justify-end items-center text-slate-200 text-sm">${fmtPct(c.attendance_rate)}</div>
            </button>
        `).join('');

        document.querySelectorAll('[data-class]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-class');
                const found = clsState.classes.find(x => String(x.id) === String(id));
                if (found) selectClass(found);
            });
        });
    };

    const applyClassFilter = () => {
        const q = (document.getElementById('class-search').value || '').toLowerCase().trim();
        const sort = document.getElementById('class-sort').value;

        const base = clsState.classes.filter(c => {
            if (!q) return true;
            return (c.name || '').toLowerCase().includes(q) || (c.teacher_name || '').toLowerCase().includes(q);
        });

        if (sort === 'students') base.sort((a,b) => (Number(b.students_count)||0) - (Number(a.students_count)||0));
        if (sort === 'attendance') base.sort((a,b) => (Number(b.attendance_rate)||0) - (Number(a.attendance_rate)||0));
        if (sort === 'grade') base.sort((a,b) => (Number(a.grade)||999) - (Number(b.grade)||999) || (String(a.section||'').localeCompare(String(b.section||''))));

        clsState.filtered = base;
        clsState.classPage = 1;
        renderClassList();
    };

    const renderSelectedClass = () => {
        const c = clsState.selectedClass;
        document.getElementById('selected-class-name').textContent = c ? c.name : 'Select a class';
        document.getElementById('selected-class-meta').textContent = c ? `Grade ${c.grade ?? '—'} • Section ${c.section ?? '—'}` : 'Roster and attendance details appear here.';
        document.getElementById('selected-class-teacher').textContent = c ? (c.teacher_name || 'Unassigned') : '—';
        document.getElementById('selected-class-students').textContent = c ? (c.students_count ?? 0) : '—';
        document.getElementById('selected-class-status').innerHTML = c ? badge(c.status) : '—';
        document.getElementById('selected-class-attendance').textContent = c ? fmtPct(c.attendance_rate) : '—';

        const jump = document.getElementById('jump-attendance');
        if (c) jump.href = `/admin/attendance?class_id=${encodeURIComponent(c.id)}`;
        else jump.href = `/admin/attendance`;
    };

    const renderRoster = () => {
        const wrap = document.getElementById('roster-list');
        const count = document.getElementById('roster-count');
        const students = clsState.rosterFiltered;

        count.textContent = clsState.selectedClass ? `${students.length} students` : '—';

        if (!clsState.selectedClass) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm">Select a class to load students.</p>';
            return;
        }

        if (!students.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm">No students found.</p>';
            return;
        }

        wrap.innerHTML = students.map(s => `
            <button class="w-full text-left glass rounded-lg px-3 py-2 border border-white/5 hover:bg-white/10 transition flex items-center justify-between gap-3 ${clsState.selectedStudent && String(clsState.selectedStudent.id) === String(s.id) ? 'bg-white/10' : ''}" data-student="${s.id}">
                <div>
                    <p class="text-white">${s.full_name || 'Student'}</p>
                    <p class="text-xs text-slate-400">${s.gender ? s.gender : ''}${s.current_grade ? ` • Grade ${s.current_grade}` : ''}</p>
                </div>
                <i class="fas fa-angle-right text-slate-400"></i>
            </button>
        `).join('');

        document.querySelectorAll('[data-student]').forEach(btn => {
            btn.addEventListener('click', () => {
                const sid = btn.getAttribute('data-student');
                const found = clsState.roster.find(x => String(x.id) === String(sid));
                if (found) selectStudent(found);
            });
        });
    };

    const applyStudentFilter = () => {
        const q = (document.getElementById('student-search').value || '').toLowerCase().trim();
        clsState.rosterFiltered = clsState.roster.filter(s => (s.full_name || '').toLowerCase().includes(q));
        renderRoster();
    };

    const renderStudentDetail = () => {
        const s = clsState.selectedStudent;
        if (!s) {
            document.getElementById('student-name').textContent = 'Select a student';
            document.getElementById('student-meta').textContent = 'Attendance summary and recent days.';
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

        const att = clsState.studentAttendance;
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

        const rows = att.recent || [];
        const recentWrap = document.getElementById('student-recent');
        if (!rows.length) {
            recentWrap.innerHTML = '<p class="text-slate-400 text-sm">No attendance records in the selected window.</p>';
            return;
        }

        const pill = (st) => {
            const map = { present: 'text-neon', absent: 'text-slate-300', permission: 'text-mint' };
            return `<span class="px-2.5 py-1 rounded-full bg-white/5 text-xs ${map[st] || 'text-slate-300'}">${(st || '').toString().toUpperCase()}</span>`;
        };

        recentWrap.innerHTML = rows.slice(0, 12).map(r => `
            <div class="glass rounded-lg px-3 py-2 border border-white/5 flex items-center justify-between gap-3">
                <div>
                    <p class="text-white text-sm">${(window.FinotDate && r.attendance_date) ? window.FinotDate.formatDate(r.attendance_date) : (r.attendance_date || '—')}</p>
                    <p class="text-xs text-slate-400">${r.workflow_status === 'submitted' ? 'Submitted' : 'Draft'}${r.note ? ` • ${r.note}` : ''}</p>
                </div>
                ${pill(r.status)}
            </div>
        `).join('');
    };

    const loadClasses = async () => {
        const list = document.getElementById('class-list');
        list.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Loading classes...</p>';
        try {
            const res = await fetch('/api/v1/classes');
            const json = await res.json();
            clsState.classes = (json.data || json || []).map(c => ({
                id: c.id,
                name: c.name || `Grade ${c.grade || ''}${c.section || ''}`.trim(),
                grade: c.grade ?? null,
                section: c.section ?? null,
                teacher_name: c.teacher_name || (c.teacher && (c.teacher.full_name || c.teacher.name)) || 'Unassigned',
                students_count: c.students_count ?? 0,
                attendance_rate: c.attendance_rate ?? null,
                status: c.status ?? 'inactive',
            }));
        } catch {
            clsState.classes = [];
        }

        renderStats();
        applyClassFilter();

        if (clsState.classes.length && !clsState.selectedClass) {
            selectClass(clsState.classes[0]);
        }
    };

    const selectClass = async (c) => {
        clsState.selectedClass = c;
        clsState.roster = [];
        clsState.rosterFiltered = [];
        clsState.selectedStudent = null;
        clsState.studentAttendance = null;

        renderSelectedClass();
        renderClassList();
        renderRoster();
        renderStudentDetail();

        document.getElementById('student-search').disabled = false;
        document.getElementById('roster-list').innerHTML = '<p class="text-slate-400 text-sm">Loading students...</p>';
        try {
            const res = await fetch(`/api/v1/classes/${c.id}/students`);
            const json = await res.json();
            clsState.roster = (json.data || json || []).map(s => ({
                id: s.id,
                full_name: s.full_name,
                gender: s.gender,
                current_grade: s.current_grade,
            }));
        } catch {
            clsState.roster = [];
        }
        clsState.rosterFiltered = clsState.roster;
        renderRoster();
    };

    const selectStudent = async (student) => {
        clsState.selectedStudent = student;
        clsState.studentAttendance = null;
        renderRoster();
        renderStudentDetail();

        const classId = clsState.selectedClass ? clsState.selectedClass.id : null;
        if (!classId) return;

        try {
            const res = await fetch(`/api/v1/students/${student.id}/attendance?class_id=${encodeURIComponent(classId)}&limit=30`);
            if (!res.ok) throw new Error('failed');
            clsState.studentAttendance = await res.json();
        } catch {
            clsState.studentAttendance = { summary: { present: 0, absent: 0, permission: 0, total: 0 }, recent: [] };
        }
        renderStudentDetail();
    };

    document.getElementById('class-search')?.addEventListener('input', applyClassFilter);
    document.getElementById('class-sort')?.addEventListener('change', applyClassFilter);
    document.getElementById('class-page-size')?.addEventListener('change', (e) => {
        clsState.classPageSize = Number(e.target.value) || 20;
        clsState.classPage = 1;
        renderClassList();
    });
    document.getElementById('class-page-prev')?.addEventListener('click', () => {
        clsState.classPage = Math.max(1, clsState.classPage - 1);
        renderClassList();
    });
    document.getElementById('class-page-next')?.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(clsState.filtered.length / Math.max(1, Number(clsState.classPageSize) || 20)));
        clsState.classPage = Math.min(totalPages, clsState.classPage + 1);
        renderClassList();
    });
    document.getElementById('classes-refresh')?.addEventListener('click', loadClasses);
    document.getElementById('student-search')?.addEventListener('input', applyStudentFilter);
    document.addEventListener('finot:dateprefs', () => {
        renderStudentDetail();
    });

    loadClasses();
</script>
@endpush

