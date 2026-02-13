@extends('layouts.admin')

@section('title', 'Finot | Classes')
@section('page-label', 'Manage cohorts')
@section('page-title', 'Classes')
@section('page-subtitle', 'Live roster, schedules, and attendance health')

@section('content')
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-6">
        <div class="glass card-accent rounded-2xl p-4 shadow-glow">
            <p class="text-slate-300 text-sm mb-1">Total Classes</p>
            <p class="text-3xl text-white font-medium" id="stat-total">24</p>
            <p class="text-xs text-slate-400 mt-1">Including archived</p>
        </div>
        <div class="glass card-accent rounded-2xl p-4 shadow-glow">
            <p class="text-slate-300 text-sm mb-1">Active Today</p>
            <p class="text-3xl text-white font-medium" id="stat-active">18</p>
            <p class="text-xs text-slate-400 mt-1">Running sessions</p>
        </div>
        <div class="glass card-accent rounded-2xl p-4 shadow-glow">
            <p class="text-slate-300 text-sm mb-1">Teachers</p>
            <p class="text-3xl text-white font-medium" id="stat-teachers">14</p>
            <p class="text-xs text-slate-400 mt-1">Assigned owners</p>
        </div>
        <div class="glass card-accent rounded-2xl p-4 shadow-glow">
            <p class="text-slate-300 text-sm mb-1">Avg Attendance</p>
            <p class="text-3xl text-white font-medium" id="stat-attendance">91%</p>
            <p class="text-xs text-slate-400 mt-1">Week to date</p>
        </div>
    </div>

    <div class="glass rounded-2xl p-4 md:p-6 shadow-glow mt-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 md:gap-4 mb-4">
            <div class="flex items-center gap-3 w-full md:w-auto">
                <div class="relative flex-1 md:w-80">
                    <input id="class-search" type="text" placeholder="Search classes" class="w-full rounded-xl bg-white/5 border border-white/10 px-4 py-2.5 text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-neon/60" />
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                </div>
                <select id="class-filter" class="rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option value="all">All statuses</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div class="flex items-center gap-3">
                <button class="h-11 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center gap-2">
                    <i class="fas fa-cloud-arrow-down"></i><span class="text-sm">Export</span>
                </button>
                <button class="h-11 px-4 rounded-xl neon-pill text-sm font-medium flex items-center gap-2">
                    <i class="fas fa-plus"></i><span>New Class</span>
                </button>
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/5 overflow-hidden">
            <div class="hidden lg:grid grid-cols-12 px-4 py-3 bg-white/5 text-xs text-slate-300">
                <span class="col-span-4">Class</span>
                <span class="col-span-2">Teacher</span>
                <span class="col-span-2">Students</span>
                <span class="col-span-2">Schedule</span>
                <span class="col-span-2 text-right">Status</span>
            </div>
            <div id="class-list" class="divide-y divide-white/5">
                <!-- Rows injected by JS -->
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mt-6">
        <div class="lg:col-span-2 glass rounded-2xl p-4 md:p-6 shadow-glow">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Snapshots</p>
            <h3 class="text-lg text-white font-medium mb-4">Highlighted classes</h3>
            <div id="class-cards" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Cards injected by JS -->
            </div>
        </div>
        <div class="glass rounded-2xl p-4 md:p-6 shadow-glow">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Actions</p>
            <h3 class="text-lg text-white font-medium mb-4">Quick ops</h3>
            <div class="space-y-3 text-sm text-slate-200">
                <button class="w-full glass rounded-xl px-4 py-3 text-left hover:bg-white/10 transition flex items-center gap-3">
                    <i class="fas fa-robot text-neon"></i><span>Auto-assign teachers</span>
                </button>
                <button class="w-full glass rounded-xl px-4 py-3 text-left hover:bg-white/10 transition flex items-center gap-3">
                    <i class="fas fa-bolt text-primary"></i><span>Trigger attendance sync</span>
                </button>
                <button class="w-full glass rounded-xl px-4 py-3 text-left hover:bg-white/10 transition flex items-center gap-3">
                    <i class="fas fa-envelope-open-text text-amber-300"></i><span>Notify guardians</span>
                </button>
                <button class="w-full glass rounded-xl px-4 py-3 text-left hover:bg-white/10 transition flex items-center gap-3">
                    <i class="fas fa-database text-mint"></i><span>Export roster CSV</span>
                </button>
            </div>
        </div>
    </div>

    <div id="student-panel" class="fixed inset-0 bg-black/60 hidden z-50">
        <div class="absolute bottom-0 md:bottom-auto md:top-0 md:right-0 md:h-full w-full md:w-96 glass border border-white/10 shadow-glow rounded-t-2xl md:rounded-none md:rounded-l-2xl p-5 flex flex-col gap-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Roster</p>
                    <p id="student-panel-title" class="text-lg text-white font-medium">Class</p>
                    <p id="student-panel-sub" class="text-xs text-slate-400 mt-1"></p>
                </div>
                <button id="student-panel-close" class="h-10 w-10 rounded-xl glass flex items-center justify-center text-slate-200 hover:text-white transition shadow-ring">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <div id="student-panel-body" class="flex-1 overflow-auto space-y-3 text-sm text-slate-200">
                <p class="text-slate-400 text-sm">Select a class to load students.</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const placeholderClasses = [
        { id: 1, name: 'Grade 10A - Mathematics', teacher: 'M. Alvarez', students: 32, schedule: 'Mon • 9:00', status: 'active', attendance: 94 },
        { id: 2, name: 'Grade 9B - Science', teacher: 'K. Patel', students: 28, schedule: 'Tue • 11:00', status: 'active', attendance: 88 },
        { id: 3, name: 'Grade 11C - Literature', teacher: 'S. Kim', students: 30, schedule: 'Wed • 10:00', status: 'pending', attendance: 0 },
        { id: 4, name: 'Grade 8A - Geography', teacher: 'T. Johnson', students: 26, schedule: 'Thu • 13:00', status: 'active', attendance: 90 },
        { id: 5, name: 'Grade 12B - Physics', teacher: 'L. Ibrahim', students: 22, schedule: 'Fri • 8:30', status: 'archived', attendance: 82 }
    ];

    const statusBadge = (status) => {
        const map = { active: 'text-neon', pending: 'text-amber-300', archived: 'text-slate-400' };
        const copy = status.charAt(0).toUpperCase() + status.slice(1);
        return `<span class="text-sm ${map[status] || 'text-slate-300'}">${copy}</span>`;
    };

    const renderTable = (data) => {
        const target = document.getElementById('class-list');
        if (!target) return;
        target.innerHTML = data.map(item => `
            <div class="grid grid-cols-1 lg:grid-cols-12 px-4 py-3 gap-2 lg:gap-0 hover:bg-white/5 transition cursor-pointer" data-class-id="${item.id}">
                <div class="lg:col-span-4 flex items-start lg:items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-primary via-neon to-mint flex items-center justify-center text-midnight text-sm">${item.name.slice(0,2)}</div>
                    <div>
                        <p class="text-white">${item.name}</p>
                        <p class="text-xs text-slate-400">${item.schedule}</p>
                    </div>
                </div>
                <div class="lg:col-span-2 text-slate-200">${item.teacher}</div>
                <div class="lg:col-span-2 text-slate-200">${item.students} students</div>
                <div class="lg:col-span-2 text-slate-200">${item.schedule}</div>
                <div class="lg:col-span-2 flex lg:justify-end">${statusBadge(item.status)}</div>
            </div>
        `).join('');
        document.querySelectorAll('[data-class-id]').forEach(row => {
            row.addEventListener('click', () => {
                const id = row.getAttribute('data-class-id');
                const found = window.finotClasses.find(c => String(c.id) === String(id));
                if (found) openStudentPanel(found);
            });
        });
    };

    const renderCards = (data) => {
        const wrap = document.getElementById('class-cards');
        if (!wrap) return;
        wrap.innerHTML = data.slice(0,4).map(item => `
            <div class="glass rounded-2xl p-4 border border-white/5 flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-white">${item.name}</p>
                        <p class="text-xs text-slate-400">${item.teacher}</p>
                    </div>
                    ${statusBadge(item.status)}
                </div>
                <div class="flex items-center gap-3 text-sm text-slate-200">
                    <span class="px-3 py-1 rounded-full bg-white/5">${item.students} students</span>
                    <span class="px-3 py-1 rounded-full bg-white/5">${item.schedule}</span>
                </div>
                <div class="w-full h-2 rounded-full bg-white/10 overflow-hidden">
                    <div class="h-2 rounded-full bg-gradient-to-r from-neon to-mint" style="width:${item.attendance || 0}%"></div>
                </div>
                <p class="text-xs text-slate-400">Attendance: ${item.attendance || '—'}%</p>
            </div>
        `).join('');
    };

    const applyStats = (data) => {
        document.getElementById('stat-total').textContent = data.length;
        document.getElementById('stat-active').textContent = data.filter(i => i.status === 'active').length;
        const teachers = new Set(data.map(i => i.teacher)).size;
        document.getElementById('stat-teachers').textContent = teachers;
        const avg = Math.round(data.reduce((s,i)=> s + (i.attendance || 0),0) / Math.max(1, data.length));
        document.getElementById('stat-attendance').textContent = avg + '%';
    };

    const hydrate = (source) => {
        renderTable(source);
        renderCards(source);
        applyStats(source);
    };

    const filterData = () => {
        const query = document.getElementById('class-search').value.toLowerCase();
        const status = document.getElementById('class-filter').value;
        const filtered = window.finotClasses.filter(item => {
            const matchesText = item.name.toLowerCase().includes(query) || item.teacher.toLowerCase().includes(query);
            const matchesStatus = status === 'all' ? true : item.status === status;
            return matchesText && matchesStatus;
        });
        hydrate(filtered);
    };

    const loadClasses = async () => {
        try {
            const res = await fetch('/api/v1/classes');
            if (!res.ok) throw new Error('Network');
            const json = await res.json();
            const mapped = (json.data || json || []).map(item => ({
                id: item.id,
                name: item.name || `Grade ${item.grade || ''}${item.section || ''}`.trim(),
                teacher: item.teacher?.full_name || item.teacher?.name || 'Unassigned',
                students: item.students_count || item.students?.length || 0,
                schedule: item.schedule || `G${item.grade || '?'} • Sec ${item.section || '?'}`,
                status: item.status || 'active',
                attendance: item.attendance_rate || 0,
            }));
            window.finotClasses = mapped.length ? mapped : placeholderClasses;
        } catch (e) {
            window.finotClasses = placeholderClasses;
        }
        hydrate(window.finotClasses);
    };

    // Student drawer
    const studentPanel = document.getElementById('student-panel');
    const studentBody = document.getElementById('student-panel-body');
    const studentTitle = document.getElementById('student-panel-title');
    const studentSub = document.getElementById('student-panel-sub');
    const closePanel = () => studentPanel.classList.add('hidden');
    document.getElementById('student-panel-close')?.addEventListener('click', closePanel);
    studentPanel?.addEventListener('click', (e) => {
        if (e.target === studentPanel) closePanel();
    });

    const renderStudents = (students = []) => {
        if (!students.length) {
            studentBody.innerHTML = '<p class="text-slate-400 text-sm">No students found.</p>';
            return;
        }
        studentBody.innerHTML = students.map(s => `
            <div class="glass rounded-xl px-3 py-2 border border-white/5 flex items-center justify-between">
                <div>
                    <p class="text-white">${s.name || 'Student'}</p>
                    <p class="text-xs text-slate-400">ID: ${s.id || '—'}</p>
                </div>
                <span class="text-xs text-slate-300">${s.status || 'Active'}</span>
            </div>
        `).join('');
    };

    const openStudentPanel = async (cls) => {
        studentTitle.textContent = cls.name;
        studentSub.textContent = `${cls.students || 0} students • ${cls.teacher}`;
        studentBody.innerHTML = '<p class="text-slate-400 text-sm">Loading roster...</p>';
        studentPanel.classList.remove('hidden');
        try {
            const res = await fetch(`/api/v1/classes/${cls.id}/students`);
            if (!res.ok) throw new Error('Network');
            const json = await res.json();
            const students = (json.data || json || []).map(s => ({
                id: s.id,
                name: s.full_name || s.name || `${s.first_name || ''} ${s.last_name || ''}`.trim(),
                status: s.status || s.pivot?.status || 'Active'
            }));
            renderStudents(students);
        } catch (e) {
            renderStudents([
                { id: 'N/A', name: 'Demo Student 1', status: 'Active' },
                { id: 'N/A', name: 'Demo Student 2', status: 'Active' },
            ]);
        }
    };

    document.getElementById('class-search')?.addEventListener('input', filterData);
    document.getElementById('class-filter')?.addEventListener('change', filterData);

    loadClasses();
</script>
@endpush
