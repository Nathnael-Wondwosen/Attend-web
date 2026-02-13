<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finot | Take Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { grotesk: ['Space Grotesk', 'Inter', 'system-ui'] },
                    colors: {
                        primary: '#7c3aed',
                        neon: '#22d3ee',
                        mint: '#34d399',
                        midnight: '#0b1021',
                        panel: 'rgba(255,255,255,0.06)',
                    },
                    boxShadow: {
                        glow: '0 10px 60px rgba(124,58,237,0.25)',
                        ring: '0 0 0 1px rgba(255,255,255,0.08)'
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --grid: radial-gradient(circle at 20% 20%, rgba(34,211,238,0.15), transparent 25%),
                    radial-gradient(circle at 80% 0%, rgba(124,58,237,0.2), transparent 28%),
                    radial-gradient(circle at 40% 80%, rgba(52,211,153,0.18), transparent 30%);
        }
        body { font-family: 'Space Grotesk', 'Inter', system-ui, -apple-system, sans-serif; background: #070912; color: #e2e8f0; }
        .holo-bg {
            position: fixed; inset: 0;
            background: linear-gradient(120deg, rgba(124,58,237,0.12), rgba(34,211,238,0.12)),
                        linear-gradient(200deg, rgba(15,23,42,0.9), rgba(10,10,20,0.95)),
                        var(--grid);
            background-blend-mode: screen, normal, normal;
            pointer-events: none;
            filter: drop-shadow(0 0 40px rgba(124,58,237,0.18));
        }
        .glass { background: linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02)); border: 1px solid rgba(255,255,255,0.06); backdrop-filter: blur(14px); }
        .neon-pill { background: linear-gradient(90deg, rgba(124,58,237,0.9), rgba(34,211,238,0.9)); color: #0b1021; box-shadow: 0 8px 30px rgba(34,211,238,0.28); }
        /* Native select dropdown styling for dark theme (option list). */
        select { color-scheme: dark; }
        select option, select optgroup { background-color: #0b1224; color: #e2e8f0; }
        input[type="date"] { color-scheme: dark; }
    </style>
</head>
<body class="min-h-screen">
    <div class="holo-bg"></div>
    <main class="relative z-10 max-w-3xl mx-auto px-4 py-6 space-y-4">
        <div class="glass rounded-2xl p-5 shadow-glow">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Teacher</p>
                    <h1 class="text-2xl text-white font-medium">Take Attendance</h1>
                    <p class="text-slate-400 text-sm mt-1">Select class, set date, mark statuses, then save and submit.</p>
                </div>
                <button id="take-logout" type="button" class="h-10 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center gap-2">
                    <i class="fas fa-right-from-bracket"></i><span class="text-sm">Logout</span>
                </button>
            </div>

            <p class="text-sm text-red-300 mt-3" id="take-error"></p>

            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end mt-4">
                <div class="sm:col-span-7">
                    <label class="text-xs text-slate-400">Class</label>
                    <select id="take-class" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" disabled>
                        <option value="">Select class</option>
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <label class="text-xs text-slate-400">Date</label>
                    <input id="take-date" type="date" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" disabled />
                </div>
                <div class="sm:col-span-2">
                    <button id="take-open" type="button" class="w-full h-11 rounded-xl neon-pill text-sm font-medium" disabled>Open</button>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
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

            <div class="mt-4 flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <input id="take-search" type="text" placeholder="Search students" class="w-full rounded-xl bg-white/5 border border-white/10 px-4 py-2.5 text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-neon/60" disabled />
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                </div>
                <button id="take-all-present" class="h-11 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center justify-center gap-2" disabled>
                    <i class="fas fa-user-check text-neon"></i><span class="text-sm">All present</span>
                </button>
            </div>

            <div class="glass rounded-2xl border border-white/5 overflow-hidden mt-4">
                <div class="hidden sm:grid grid-cols-12 px-4 py-3 bg-white/5 text-xs text-slate-300">
                    <span class="col-span-6">Student</span>
                    <span class="col-span-2">Status</span>
                    <span class="col-span-4 text-right">Actions</span>
                </div>
                <div id="take-roster" class="divide-y divide-white/5 min-h-[220px]">
                    <p class="text-slate-400 text-sm px-4 py-3">Enter a token, then open a class and date.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
                <button id="take-save" class="h-11 px-4 rounded-xl neon-pill text-sm font-medium flex items-center justify-center gap-2" disabled>
                    <i class="fas fa-floppy-disk"></i><span>Save</span>
                </button>
                <button id="take-submit" class="h-11 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center justify-center gap-2" disabled>
                    <i class="fas fa-paper-plane text-mint"></i><span>Submit</span>
                </button>
                <button id="take-refresh" class="h-11 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center justify-center gap-2" disabled>
                    <i class="fas fa-rotate text-primary"></i><span>Refresh</span>
                </button>
            </div>

            <div id="take-status" class="hidden glass rounded-xl p-4 border border-white/5 mt-4">
                <p class="text-white text-sm font-medium" id="take-status-title">Status</p>
                <p class="text-slate-400 text-sm mt-1" id="take-status-copy">—</p>
            </div>

            <div id="lock-banner" class="hidden glass rounded-xl p-4 border border-white/5 mt-4">
                <p class="text-white text-sm font-medium">This attendance is locked</p>
                <p class="text-slate-400 text-sm mt-1" id="lock-banner-copy">Submitted more than 7 days ago.</p>
            </div>
        </div>
    </main>

    <script>
        const take = {
            teacherToken: '',
            classes: [],
            currentClassId: null,
            currentSessionId: null,
            roster: [],
            filtered: [],
            dirty: {},
            locked: false,
            editableUntil: null,
        };

        const el = (id) => document.getElementById(id);
        const setError = (m) => { el('take-error').textContent = m || ''; };
        const setStatusBox = (title, copy) => {
            const box = el('take-status');
            if (!title && !copy) {
                box.classList.add('hidden');
                return;
            }
            el('take-status-title').textContent = title || 'Status';
            el('take-status-copy').textContent = copy || '';
            box.classList.remove('hidden');
        };
        const setBusy = (busy, which) => {
            const setBtn = (id, on, label, icon) => {
                const b = el(id);
                if (!b) return;
                b.disabled = on ? true : b.disabled;
                if (!b.dataset.orig) b.dataset.orig = b.innerHTML;
                b.innerHTML = on
                    ? `<i class="fas fa-circle-notch fa-spin"></i><span>${label}</span>`
                    : b.dataset.orig;
            };
            if (which === 'save') setBtn('take-save', busy, 'Saving...', 'fa-floppy-disk');
            if (which === 'submit') setBtn('take-submit', busy, 'Submitting...', 'fa-paper-plane');
            if (which === 'refresh') setBtn('take-refresh', busy, 'Refreshing...', 'fa-rotate');
            if (busy) {
                el('take-open').disabled = true;
                el('take-all-present').disabled = true;
            } else {
                el('take-open').disabled = !take.mode || !el('take-class').value || !el('take-date').value;
                el('take-all-present').disabled = !take.currentSessionId || take.locked;
            }
        };

        const setModeBadge = () => {
            const map = { none: 'Mode: --', public: 'Mode: Token (no login)', teacher: 'Mode: Teacher login' };
            el('mode-badge').textContent = map[take.mode] || 'Mode: --';
            el('take-logout').classList.toggle('hidden', take.mode !== 'teacher');
        };

        const api = async (path, options = {}) => {
            const headers = { ...(options.headers || {}) };
            headers['Authorization'] = `Bearer ${take.teacherToken}`;
            return fetch(`/api/v1${path}`, { ...options, headers });
        };

        const statusPill = (status) => {
            const palette = { present: 'text-neon', permission: 'text-mint', absent: 'text-slate-300' };
            const s = (status || 'absent').toString();
            const copy = s.charAt(0).toUpperCase() + s.slice(1);
            return `<span class="px-3 py-1 rounded-full bg-white/5 ${palette[s] || ''} text-xs">${copy}</span>`;
        };

        const showLock = (locked, editableUntil) => {
            const banner = el('lock-banner');
            const copy = el('lock-banner-copy');
            if (!locked) { banner.classList.add('hidden'); return; }
            banner.classList.remove('hidden');
            copy.textContent = editableUntil ? `Locked after ${editableUntil}.` : 'Submitted more than 7 days ago.';
        };

        const computeStats = () => {
            const total = take.filtered.length;
            const present = take.filtered.filter(s => s.status === 'present').length;
            const permission = take.filtered.filter(s => s.status === 'permission').length;
            const absent = take.filtered.filter(s => s.status === 'absent').length;
            el('stat-total').textContent = total;
            el('stat-present').textContent = present;
            el('stat-permission').textContent = permission;
            el('stat-absent').textContent = absent;
        };

        const renderRoster = () => {
            const wrap = el('take-roster');
            if (!take.currentSessionId) {
                wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Open a class and date.</p>';
                take.filtered = [];
                computeStats();
                return;
            }
            if (!take.filtered.length) {
                wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No students found.</p>';
                computeStats();
                return;
            }
            const disabled = take.locked ? 'disabled' : '';
            wrap.innerHTML = take.filtered.map(s => `
                <div class="grid grid-cols-1 sm:grid-cols-12 px-4 py-3 gap-3 items-center">
                    <div class="sm:col-span-6">
                        <p class="text-white">${s.name}</p>
                        <p class="text-xs text-slate-400">ID: ${s.id}</p>
                    </div>
                    <div class="sm:col-span-2">${statusPill(s.status)}</div>
                    <div class="sm:col-span-4 flex flex-wrap gap-2 justify-start sm:justify-end">
                        ${['present','permission','absent'].map(st => `
                            <button ${disabled} data-student="${s.id}" data-status="${st}" class="px-3 py-1 rounded-lg bg-white/5 text-xs ${s.status===st?'text-neon':'text-slate-200'} hover:text-white transition disabled:opacity-40 disabled:cursor-not-allowed">
                                ${st[0].toUpperCase() + st.slice(1)}
                            </button>
                        `).join('')}
                    </div>
                </div>
            `).join('');

            wrap.querySelectorAll('button[data-student][data-status]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (take.locked) return;
                    const sid = btn.getAttribute('data-student');
                    const status = btn.getAttribute('data-status');
                    const target = take.roster.find(x => String(x.id) === String(sid));
                    if (!target) return;
                    target.status = status;
                    applyFilter();
                    take.dirty[String(sid)] = status;
                    el('take-save').disabled = Object.keys(take.dirty).length === 0;
                });
            });

            computeStats();
        };

        const applyFilter = () => {
            const q = (el('take-search').value || '').toLowerCase().trim();
            take.filtered = take.roster.filter(s => (s.name || '').toLowerCase().includes(q));
            renderRoster();
        };

        const setEnabled = (on) => {
            el('take-class').disabled = !on;
            el('take-date').disabled = !on;
            el('take-open').disabled = !on;
        };

        const setRosterEnabled = (on) => {
            el('take-search').disabled = !on;
            el('take-all-present').disabled = !on;
            el('take-save').disabled = !on;
            el('take-submit').disabled = !on;
            el('take-refresh').disabled = !on;
        };

        const loadClasses = async () => {
            const res = await api('/teacher/classes');
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed to load classes');
            take.classes = json?.data || json || [];
            el('take-class').innerHTML = '<option value="">Select class</option>' + take.classes.map(c => {
                const name = c.name || `Grade ${c.grade || ''}${c.section || ''}`.trim();
                return `<option value="${c.id}">${name}</option>`;
            }).join('');
        };

        const validateTeacherToken = async () => {
            if (!take.teacherToken) return false;
            try {
                const res = await fetch('/api/v1/me', { headers: { 'Authorization': `Bearer ${take.teacherToken}` } });
                const json = await res.json().catch(() => null);
                if (!res.ok || json?.type !== 'teacher') return false;
                return true;
            } catch {
                return false;
            }
        };

        const openSession = async () => {
            setError('');
            setStatusBox(null, null);
            const classId = el('take-class').value;
            const date = el('take-date').value;
            if (!classId) return setError('Select a class');
            if (!date) return setError('Select a date');

            // Public and teacher modes both use the same request shape; paths differ by base.
            setBusy(true, 'refresh');
            const res = await api(`/classes/${classId}/sessions`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attendance_date: date })
            });
            const json = await res.json().catch(() => null);
            setBusy(false, 'refresh');
            if (!res.ok) throw new Error(json?.message || 'Failed to open session');
            const session = json.session || json;
            take.currentClassId = Number(classId);
            take.currentSessionId = session.id;

            // If this date was already taken, tell the teacher immediately (no duplicates exist; we load the existing record).
            const wf = (session.workflow_status || json?.session?.workflow_status || '').toString().toLowerCase();
            if (wf === 'submitted') {
                setStatusBox('Already submitted', 'Attendance for this class/date is already submitted. You are viewing the existing record.');
            } else {
                setStatusBox('Opened', 'Attendance session opened. Mark students, then Save and Submit.');
            }

            await loadRoster();
        };

        const loadRoster = async () => {
            el('take-roster').innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Loading roster...</p>';
            setStatusBox('Loading', 'Loading roster...');
            const res = await api(`/sessions/${take.currentSessionId}/students`);
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed to load roster');
            take.locked = !!json.session?.locked;
            take.editableUntil = json.session?.editable_until || null;
            showLock(take.locked, take.editableUntil);

            take.roster = (json.students || []).map(s => ({
                id: s.student_id,
                name: s.full_name,
                status: s.attendance_status || 'absent',
                attendance_id: s.attendance_id || null,
            }));
            take.dirty = {};
            el('take-save').disabled = true;
            setRosterEnabled(true);
            el('take-submit').disabled = take.locked;
            el('take-all-present').disabled = take.locked;
            applyFilter();
            setStatusBox('Ready', take.locked ? 'Attendance is locked.' : 'Mark students, then Save and Submit.');
        };

        const save = async () => {
            if (!take.currentSessionId) return;
            if (take.locked) return setError('Attendance is locked.');
            const entries = Object.entries(take.dirty);
            if (!entries.length) return setStatusBox('No changes', 'Nothing to save.');
            setError('');
            setBusy(true, 'save');
            setStatusBox('Saving', 'Saving changes...');
            const updates = entries.map(([studentId, status]) => ({ student_id: Number(studentId), status }));
            try {
                const res = await api(`/sessions/${take.currentSessionId}/students`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ updates })
                });
                const json = await res.json().catch(() => null);
                if (!res.ok) throw new Error(json?.message || 'Failed to save');
                take.dirty = {};
                el('take-save').disabled = true;
                setStatusBox('Saved', `Saved at ${new Date().toLocaleTimeString()}.`);
            } finally {
                setBusy(false, 'save');
            }
        };

        const submit = async () => {
            if (!take.currentSessionId) return;
            if (take.locked) return setError('Attendance is locked.');
            if (!confirm('Submit attendance for this date?')) return;
            setError('');
            setBusy(true, 'submit');
            setStatusBox('Submitting', 'Submitting attendance...');
            try {
                const res = await api(`/sessions/${take.currentSessionId}/close`, { method: 'POST' });
                const json = await res.json().catch(() => null);
                if (!res.ok) throw new Error(json?.message || 'Failed to submit');

                // Backend may return 200 "Already submitted". Treat as a user-visible error and do not clear the page.
                const msg = (json?.message || '').toString().toLowerCase();
                if (msg.includes('already submitted')) {
                    setError('Already submitted for this class/date.');
                    setStatusBox('Already submitted', 'This attendance was already submitted earlier. No duplicate submission was made.');
                    // Refresh roster so the teacher sees the current state.
                    await loadRoster();
                    return;
                }

                // Clear session state so the UI is "fresh" for a new attendance.
                take.currentSessionId = null;
                take.roster = [];
                take.filtered = [];
                take.dirty = {};
                take.locked = false;
                take.editableUntil = null;
                el('take-search').value = '';
                el('take-save').disabled = true;
                el('take-submit').disabled = true;
                setRosterEnabled(false);
                showLock(false, null);
                renderRoster();
                setStatusBox('Submitted', 'Attendance submitted. Change date (or class) and press Open to start a new attendance.');
            } finally {
                setBusy(false, 'submit');
            }
        };

        const allPresent = () => {
            if (take.locked) return;
            take.roster.forEach(s => s.status = 'present');
            take.dirty = Object.fromEntries(take.roster.map(s => [String(s.id), 'present']));
            el('take-save').disabled = false;
            applyFilter();
            setStatusBox('Updated', 'All students set to Present. Press Save.');
        };

        const useTeacherLogin = async (token) => {
            take.teacherToken = (token || '').trim();
            if (!take.teacherToken) return;
            const ok = await validateTeacherToken();
            if (!ok) throw new Error('Teacher login expired. Please login again.');
            await loadClasses();
            setEnabled(true);
            setStatusBox('Ready', 'Select class and date, then press Open.');
        };

        const teacherTokenKey = 'finot_teacher_token';
        const teacherExisting = localStorage.getItem(teacherTokenKey) || '';

        el('take-logout').addEventListener('click', () => {
            localStorage.removeItem(teacherTokenKey);
            take.teacherToken = '';
            window.location.href = '/login?role=teacher';
        });

        el('take-date').value = new Date().toISOString().slice(0, 10);
        el('take-open').addEventListener('click', () => openSession().catch(e => { setError(e?.message || 'Failed'); setBusy(false, 'refresh'); }));
        el('take-refresh').addEventListener('click', () => take.currentSessionId && loadRoster().catch(e => setError(e?.message || 'Failed')));
        el('take-save').addEventListener('click', () => save().catch(e => { setError(e?.message || 'Failed'); setBusy(false, 'save'); }));
        el('take-submit').addEventListener('click', () => submit().catch(e => { setError(e?.message || 'Failed'); setBusy(false, 'submit'); }));
        el('take-all-present').addEventListener('click', allPresent);
        el('take-search').addEventListener('input', applyFilter);

        // Require teacher login.
        (async () => {
            if (teacherExisting) {
                try {
                    await useTeacherLogin(teacherExisting);
                    return;
                } catch {
                    localStorage.removeItem(teacherTokenKey);
                }
            }
            window.location.href = '/login?role=teacher';
        })();
    </script>
</body>
</html>
