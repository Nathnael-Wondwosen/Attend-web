<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finot Attendance - Take Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(180deg, #070912, #0b1021); }
        /* Native select dropdown styling for dark theme (option list). */
        select { color-scheme: dark; }
        select option, select optgroup { background-color: #0b1224; color: #e2e8f0; }
        input[type="date"] { color-scheme: dark; }
    </style>
</head>
<body class="min-h-screen text-slate-100 p-4 md:p-6">
    <div class="max-w-3xl mx-auto space-y-4">
        <header class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Teacher</p>
                <h1 class="text-2xl md:text-3xl font-semibold text-white mt-1">Attendance</h1>
                <p class="text-sm text-slate-300 mt-2">Select class, set date, mark statuses, then submit.</p>
            </div>
            <button id="logout" class="h-11 px-4 rounded-xl bg-white/5 border border-white/10 text-slate-200 hover:text-white">
                Logout
            </button>
        </header>

        <section class="bg-white/5 border border-white/10 backdrop-blur rounded-2xl p-4 md:p-5 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="text-xs text-slate-400">Class</label>
                    <select id="cls" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400/50">
                        <option value="">Loading...</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-400">Date</label>
                    <input id="date" type="date" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-3 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400/50" />
                </div>
                <div class="flex items-end gap-2">
                    <button id="open" class="flex-1 h-12 rounded-xl bg-gradient-to-r from-cyan-400 to-emerald-400 text-slate-900 font-semibold">
                        Open
                    </button>
                    <button id="submit" class="h-12 px-4 rounded-xl bg-white/5 border border-white/10 text-slate-200 hover:text-white">
                        Submit
                    </button>
                </div>
            </div>

            <div class="flex gap-3 text-sm">
                <div class="flex-1 rounded-xl bg-white/5 border border-white/10 p-3">
                    <p class="text-xs text-slate-400">Present</p>
                    <p class="text-xl font-semibold text-white" id="c-present">0</p>
                </div>
                <div class="flex-1 rounded-xl bg-white/5 border border-white/10 p-3">
                    <p class="text-xs text-slate-400">Permission</p>
                    <p class="text-xl font-semibold text-white" id="c-perm">0</p>
                </div>
                <div class="flex-1 rounded-xl bg-white/5 border border-white/10 p-3">
                    <p class="text-xs text-slate-400">Absent</p>
                    <p class="text-xl font-semibold text-white" id="c-absent">0</p>
                </div>
            </div>

            <p id="hint" class="text-sm text-slate-300"></p>
            <p id="err" class="text-sm text-red-300"></p>
        </section>

        <section class="bg-white/5 border border-white/10 backdrop-blur rounded-2xl overflow-hidden">
            <div class="px-4 py-3 bg-white/5 border-b border-white/10 text-xs text-slate-300 grid grid-cols-12">
                <span class="col-span-7">Student</span>
                <span class="col-span-5 text-right">Status</span>
            </div>
            <div id="list" class="divide-y divide-white/10">
                <p class="px-4 py-4 text-slate-400 text-sm">Open a class/date to load roster.</p>
            </div>
        </section>

        <div class="flex gap-2">
            <button id="save" class="flex-1 h-12 rounded-xl bg-white/5 border border-white/10 text-slate-200 hover:text-white">
                Save
            </button>
            <button id="all-present" class="h-12 px-4 rounded-xl bg-white/5 border border-white/10 text-slate-200 hover:text-white">
                All Present
            </button>
        </div>
    </div>

    <script>
        const tokenKey = 'finot_teacher_token';
        const token = localStorage.getItem(tokenKey);
        if (!token) window.location.href = '/teacher/login';

        const api = (url, opts = {}) => {
            opts.headers = { ...(opts.headers || {}), Authorization: `Bearer ${token}` };
            return fetch(url, opts);
        };

        const state = {
            classes: [],
            sessionId: null,
            locked: false,
            roster: [],
            dirty: {},
        };

        const el = (id) => document.getElementById(id);
        const setErr = (m) => el('err').textContent = m || '';
        const setHint = (m) => el('hint').textContent = m || '';

        const compute = () => {
            const present = state.roster.filter(s => s.status === 'present').length;
            const perm = state.roster.filter(s => s.status === 'permission').length;
            const absent = state.roster.filter(s => s.status === 'absent').length;
            el('c-present').textContent = present;
            el('c-perm').textContent = perm;
            el('c-absent').textContent = absent;
        };

        const render = () => {
            const wrap = el('list');
            if (!state.roster.length) {
                wrap.innerHTML = '<p class="px-4 py-4 text-slate-400 text-sm">No students.</p>';
                compute();
                return;
            }

            const btn = (sid, status, label) =>
                `<button ${state.locked ? 'disabled' : ''} data-sid="${sid}" data-status="${status}" class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs ${status === 'present' ? 'text-emerald-300' : status === 'permission' ? 'text-cyan-300' : 'text-slate-200'} disabled:opacity-40">${label}</button>`;

            wrap.innerHTML = state.roster.map(s => `
                <div class="px-4 py-3 grid grid-cols-12 items-center gap-2">
                    <div class="col-span-7">
                        <p class="text-white">${s.name}</p>
                        <p class="text-xs text-slate-400">ID: ${s.id}</p>
                    </div>
                    <div class="col-span-5 flex gap-2 justify-end">
                        ${btn(s.id, 'present', 'Present')}
                        ${btn(s.id, 'permission', 'Permission')}
                        ${btn(s.id, 'absent', 'Absent')}
                    </div>
                </div>
            `).join('');

            wrap.querySelectorAll('button[data-sid][data-status]').forEach(b => {
                b.addEventListener('click', () => {
                    const sid = b.getAttribute('data-sid');
                    const status = b.getAttribute('data-status');
                    const row = state.roster.find(x => String(x.id) === String(sid));
                    if (!row) return;
                    row.status = status;
                    state.dirty[sid] = status;
                    render();
                });
            });

            compute();
        };

        const loadClasses = async () => {
            const res = await api('/api/v1/teacher/classes');
            if (!res.ok) throw new Error('Failed to load classes');
            state.classes = await res.json();
            el('cls').innerHTML = '<option value="">Select class</option>' +
                state.classes.map(c => `<option value="${c.id}">${c.name || ('Grade ' + c.grade + (c.section || ''))}</option>`).join('');
        };

        const openSession = async () => {
            setErr('');
            const classId = el('cls').value;
            const date = el('date').value;
            if (!classId) return setErr('Select a class');
            if (!date) return setErr('Select a date');

            const res = await api(`/api/v1/classes/${classId}/sessions`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attendance_date: date })
            });
            const json = await res.json().catch(() => null);
            if (!res.ok) return setErr(json?.message || 'Failed to open attendance');

            state.sessionId = json.session?.id || json.session_id || json.id || json.session?.session_id;
            state.locked = !!json.locked;
            state.dirty = {};
            setHint(state.locked ? 'Attendance is locked (submitted more than 7 days ago).' : 'Draft attendance opened.');

            await loadRoster();
        };

        const loadRoster = async () => {
            if (!state.sessionId) return;
            const res = await api(`/api/v1/sessions/${state.sessionId}/students`);
            const json = await res.json().catch(() => null);
            if (!res.ok) return setErr(json?.message || 'Failed to load roster');

            state.locked = !!json.session?.locked;
            state.roster = (json.students || []).map(s => ({
                id: s.student_id,
                name: s.full_name,
                status: s.attendance_status || 'absent',
            }));
            render();
        };

        const save = async () => {
            setErr('');
            if (!state.sessionId) return;
            if (state.locked) return setErr('Attendance is locked.');
            const entries = Object.entries(state.dirty);
            if (!entries.length) return;
            const updates = entries.map(([studentId, status]) => ({ student_id: Number(studentId), status }));
            const res = await api(`/api/v1/sessions/${state.sessionId}/students`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ updates })
            });
            const json = await res.json().catch(() => null);
            if (!res.ok) return setErr(json?.message || 'Save failed');
            state.dirty = {};
            setHint('Saved.');
            await loadRoster();
        };

        const submit = async () => {
            setErr('');
            if (!state.sessionId) return;
            if (!confirm('Submit attendance?')) return;
            const res = await api(`/api/v1/sessions/${state.sessionId}/close`, { method: 'POST' });
            const json = await res.json().catch(() => null);
            if (!res.ok) return setErr(json?.message || 'Submit failed');
            setHint('Submitted.');
            await loadRoster();
        };

        el('save').addEventListener('click', save);
        el('open').addEventListener('click', openSession);
        el('submit').addEventListener('click', submit);
        el('all-present').addEventListener('click', () => {
            if (state.locked) return;
            state.roster.forEach(s => { s.status = 'present'; state.dirty[s.id] = 'present'; });
            render();
        });
        el('logout').addEventListener('click', () => {
            localStorage.removeItem(tokenKey);
            window.location.href = '/teacher/login';
        });

        el('date').value = new Date().toISOString().slice(0, 10);

        loadClasses().catch(() => {
            localStorage.removeItem(tokenKey);
            window.location.href = '/teacher/login';
        });
    </script>
</body>
</html>
