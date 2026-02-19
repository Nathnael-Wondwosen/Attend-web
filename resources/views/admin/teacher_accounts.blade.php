@extends('layouts.admin')

@section('title', 'Finot | Teacher Accounts')
@section('page-label', 'Access control')
@section('page-title', 'Teacher Accounts')
@section('page-subtitle', 'Create and manage teacher login accounts for the attendance mobile app')

@section('content')
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6">
        <div class="xl:col-span-2 glass rounded-2xl p-4 md:p-6 shadow-glow flex flex-col gap-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Directory</p>
                    <h3 class="text-lg text-white font-medium">Accounts</h3>
                </div>
                <div class="flex items-center gap-3">
                    <input id="acct-search" type="text" placeholder="Search teacher/username" class="rounded-xl bg-white/5 border border-white/10 px-4 py-2.5 text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-neon/60 w-full md:w-72" />
                    <button id="acct-new" type="button" class="h-11 px-4 rounded-xl neon-pill text-sm font-medium flex items-center gap-2 whitespace-nowrap">
                        <i class="fas fa-plus"></i><span>New</span>
                    </button>
                </div>
            </div>

            <div class="glass rounded-2xl border border-white/5 overflow-hidden">
                <div class="hidden lg:grid grid-cols-12 px-4 py-3 bg-white/5 text-xs text-slate-300">
                    <span class="col-span-4">Teacher</span>
                    <span class="col-span-2">Username</span>
                    <span class="col-span-2">Classes</span>
                    <span class="col-span-2">Last Login</span>
                    <span class="col-span-1">Status</span>
                    <span class="col-span-1 text-right">Actions</span>
                </div>
                <div id="acct-list" class="divide-y divide-white/5 min-h-[160px]">
                    <p class="text-slate-400 text-sm px-4 py-3">Loading...</p>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-4 md:p-6 shadow-glow flex flex-col gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Create</p>
                <h3 class="text-lg text-white font-medium">New teacher account</h3>
            </div>

            <div class="space-y-3 text-sm text-slate-200">
                <div>
                    <label class="text-xs text-slate-400">Teacher</label>
                    <select id="new-teacher" class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                        <option value="">Select teacher</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-400">Assign classes (optional)</label>
                    <select id="new-classes" multiple class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60 min-h-[120px]">
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Hold Ctrl (Windows) to select multiple.</p>
                </div>
                <div>
                    <label class="text-xs text-slate-400">Username</label>
                    <input id="new-username" type="text" class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" placeholder="e.g. teacher10a" />
                </div>
                <div>
                    <label class="text-xs text-slate-400">Password (optional)</label>
                    <input id="new-password" type="text" class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" placeholder="Leave empty to auto-generate" />
                </div>
                <button id="new-create" type="button" class="w-full neon-pill rounded-xl px-4 py-3 text-left flex items-center gap-3">
                    <i class="fas fa-user-plus"></i><span>Create account</span>
                </button>
            </div>

            <div id="pw-box" class="glass rounded-xl p-4 border border-white/5 hidden">
                <p class="text-xs text-slate-400 mb-2">Generated password (show once)</p>
                <div class="flex items-center gap-2">
                    <code id="pw-value" class="flex-1 px-3 py-2 rounded-lg bg-white/5 text-slate-200 text-xs overflow-auto"></code>
                    <button id="pw-copy" type="button" class="h-10 px-3 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring">Copy</button>
                </div>
                <p class="text-xs text-slate-400 mt-2">Share this securely with the teacher.</p>
            </div>

            <div id="acct-status" class="text-xs text-slate-400"></div>
            <div id="acct-error" class="text-sm text-red-300"></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const state = {
        accounts: [],
        teachers: [],
        classes: [],
        filter: '',
    };

    const el = (id) => document.getElementById(id);
    const setError = (msg) => { el('acct-error').textContent = msg || ''; };
    const setStatus = (msg) => { el('acct-status').textContent = msg || ''; };

    // Surface JS errors in-page (users won't see DevTools on mobile).
    window.addEventListener('error', (e) => {
        try {
            setError(`JS error: ${e.message || 'unknown'}`);
        } catch {}
    });
    window.addEventListener('unhandledrejection', (e) => {
        try {
            setError(`JS error: ${(e.reason && e.reason.message) ? e.reason.message : 'promise rejected'}`);
        } catch {}
    });

    const pill = (status) => {
        const map = { active: 'text-neon', disabled: 'text-slate-400' };
        const copy = (status || '—').toString();
        return `<span class="px-2.5 py-1 rounded-full bg-white/5 text-xs ${map[copy] || 'text-slate-300'}">${copy.toUpperCase()}</span>`;
    };

    const initials = (name) => {
        const s = String(name || '').trim();
        if (!s) return 'T';
        const parts = s.split(/\s+/).filter(Boolean);
        const a = (parts[0] || 'T')[0];
        const b = (parts[1] || '')[0] || '';
        return (a + b).toUpperCase();
    };

    const fmtDT = (iso) => {
        if (!iso) return '—';
        try {
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return String(iso);
            return window.FinotDate ? window.FinotDate.formatDateTime(iso) : d.toLocaleString();
        } catch {
            return String(iso);
        }
    };

    const iconBtn = (action, id, icon, label) => `
        <button type="button" data-action="${action}" data-id="${id}"
            class="h-9 w-9 rounded-lg bg-white/5 border border-white/10 text-slate-200 hover:text-white hover:bg-white/10 transition inline-flex items-center justify-center"
            title="${label}" aria-label="${label}">
            <i class="${icon}"></i>
        </button>
    `;

    const render = () => {
        const q = state.filter.trim().toLowerCase();
        const data = state.accounts.filter(a => {
            if (!q) return true;
            return String(a.teacher_name || '').toLowerCase().includes(q) ||
                String(a.username || '').toLowerCase().includes(q);
        });

        const wrap = el('acct-list');
        if (!data.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No accounts.</p>';
            return;
        }

        wrap.innerHTML = data.map(a => {
            const classesCount = Number(a.assigned_classes_count || 0);
            const teacherName = a.teacher_name || 'Teacher';
            const teacherActive = (typeof a.teacher_active === 'number' ? a.teacher_active === 1 : !!a.teacher_active);
            const lastLogin = a.last_login ? fmtDT(a.last_login) : '—';
            const created = a.created_at ? fmtDT(a.created_at) : '—';
            const status = (a.status || '').toString();

            // Mobile: card layout. Desktop: table-like grid.
            return `
                <div class="px-4 py-3 hover:bg-white/5 transition">
                    <div class="flex items-start justify-between gap-3 lg:hidden">
                        <div class="flex items-start gap-3">
                            <div class="h-11 w-11 rounded-xl bg-gradient-to-br from-primary via-neon to-mint flex items-center justify-center text-midnight font-semibold">
                                ${initials(teacherName)}
                            </div>
                            <div>
                                <p class="text-white">${teacherName} ${teacherActive ? '' : '<span class="text-xs text-amber-300">(Inactive)</span>'}</p>
                                <p class="text-xs text-slate-400">username: <span class="text-slate-200">${a.username}</span> • ${classesCount} classes</p>
                                <p class="text-xs text-slate-400 mt-1">last login: ${lastLogin}</p>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            ${pill(status)}
                            <div class="flex items-center gap-2">
                                ${iconBtn('classes', a.id, 'fas fa-layer-group', 'Edit classes')}
                                ${iconBtn('take', a.id, 'fas fa-link', 'Generate take link')}
                                ${iconBtn('reset', a.id, 'fas fa-key', 'Reset password')}
                                ${iconBtn('toggle', a.id, status === 'active' ? 'fas fa-ban' : 'fas fa-check', status === 'active' ? 'Disable' : 'Enable')}
                            </div>
                        </div>
                    </div>

                    <div class="hidden lg:grid grid-cols-12 gap-2 items-center">
                        <div class="col-span-4 flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-primary via-neon to-mint flex items-center justify-center text-midnight font-semibold">
                                ${initials(teacherName)}
                            </div>
                            <div>
                                <p class="text-white">${teacherName} ${teacherActive ? '' : '<span class="text-xs text-amber-300">(Inactive)</span>'}</p>
                                <p class="text-xs text-slate-400">teacher_id: ${a.teacher_id} • created: ${created}</p>
                            </div>
                        </div>
                        <div class="col-span-2 text-slate-200">
                            <p class="text-sm">${a.username}</p>
                        </div>
                        <div class="col-span-2 text-slate-200">
                            <span class="px-3 py-1 rounded-full bg-white/5 text-xs">${classesCount}</span>
                        </div>
                        <div class="col-span-2 text-slate-200">
                            <span class="text-xs">${lastLogin}</span>
                        </div>
                        <div class="col-span-1">${pill(status)}</div>
                        <div class="col-span-1 flex justify-end gap-2">
                            ${iconBtn('classes', a.id, 'fas fa-layer-group', 'Edit classes')}
                            ${iconBtn('take', a.id, 'fas fa-link', 'Generate take link')}
                            ${iconBtn('reset', a.id, 'fas fa-key', 'Reset password')}
                            ${iconBtn('toggle', a.id, status === 'active' ? 'fas fa-ban' : 'fas fa-check', status === 'active' ? 'Disable' : 'Enable')}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        wrap.querySelectorAll('button[data-action][data-id]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                const action = btn.getAttribute('data-action');
                const acct = state.accounts.find(x => String(x.id) === String(id));
                if (!acct) return;

                if (action === 'toggle') {
                    const next = acct.status === 'active' ? 'disabled' : 'active';
                    await updateAccount(id, { status: next });
                }

                if (action === 'reset') {
                    if (!confirm('Reset password for this teacher account?')) return;
                    const res = await updateAccount(id, { reset_password: true });
                    if (res && res.password) showPassword(res.password);
                }

                if (action === 'classes') {
                    await openClassesModal(acct);
                }

                if (action === 'take') {
                    const ttl = 24;
                    if (!confirm(`Generate a ${ttl}h take-attendance link for ${acct.teacher_name}?`)) return;
                    const out = await createTakeLink(acct.teacher_id, acct.teacher_name, ttl);
                    if (out?.take_url) showTakeLink(out.take_url, out.expires_at);
                }
            });
        });
    };

    const showPassword = (pw) => {
        el('pw-box').classList.remove('hidden');
        el('pw-value').textContent = pw;
    };

    const loadTeachers = async () => {
        const res = await fetch('/api/v1/teachers');
        if (!res.ok) throw new Error('Failed to load teachers');
        const json = await res.json();
        state.teachers = (json.data || json || []);
        el('new-teacher').innerHTML = '<option value="">Select teacher</option>' +
            state.teachers.map(t => `<option value="${t.id}">${t.full_name}</option>`).join('');
    };

    const loadClasses = async () => {
        const res = await fetch('/api/v1/classes');
        if (!res.ok) throw new Error('Failed to load classes');
        const json = await res.json();
        const rows = (json.data || json || []);
        state.classes = rows.map(c => ({
            id: c.id,
            name: c.name || `Grade ${c.grade || ''}${c.section || ''}`.trim(),
        }));
        el('new-classes').innerHTML = state.classes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    };

    const loadAccounts = async () => {
        const res = await fetch('/api/v1/teacher-accounts');
        if (!res.ok) throw new Error('Failed to load accounts');
        state.accounts = await res.json();
        render();
    };

    const createAccount = async () => {
        setError('');
        setStatus('');
        el('pw-box').classList.add('hidden');

        const btn = el('new-create');
        const originalText = btn ? btn.innerHTML : '';

        try {
            const teacherId = el('new-teacher')?.value;
            const username = (el('new-username')?.value || '').trim();
            const password = (el('new-password')?.value || '').trim();
            const clsSelect = el('new-classes');
            const classIds = Array.from(clsSelect?.selectedOptions || []).map(o => Number(o.value)).filter(v => Number.isFinite(v));

            if (!teacherId) throw new Error('Select a teacher');
            if (!username) throw new Error('Enter a username');
            if (username.length < 3) throw new Error('Username must be at least 3 characters');

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i><span>Creating...</span>';
            }
            setStatus('Creating account...');

            const res = await fetch('/api/v1/teacher-accounts', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    teacher_id: Number(teacherId),
                    username,
                    password: password || null,
                    class_ids: classIds.length ? classIds : null
                })
            });

            const json = await res.json().catch(() => null);
            if (!res.ok) {
                throw new Error(json?.message || `Failed to create account (HTTP ${res.status})`);
            }

            if (json?.password) showPassword(json.password);
            setStatus('Account created. Refreshing list...');

            // Reset some fields after success.
            if (el('new-password')) el('new-password').value = '';
            if (clsSelect) Array.from(clsSelect.options).forEach(o => o.selected = false);

            // Refresh list; failure here should not look like create failed.
            try {
                await loadAccounts();
            } catch (e) {
                console.error('loadAccounts failed after create', e);
            }

            setStatus('Done.');
        } catch (e) {
            console.error('createAccount failed', e);
            setError(e?.message || 'Failed to create account');
            setStatus('');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    };

    const updateAccount = async (id, patch) => {
        setError('');
        const res = await fetch(`/api/v1/teacher-accounts/${id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(patch)
        });
        const json = await res.json().catch(() => null);
        if (!res.ok) {
            setError(json?.message || 'Failed to update account');
            return null;
        }
        await loadAccounts();
        return json;
    };

    const showTakeLink = (url, expiresAt) => {
        el('pw-box').classList.remove('hidden');
        el('pw-value').textContent = url;
        // Reuse the hint line to show expiry.
        const hint = el('pw-box').querySelector('p.text-xs.text-slate-400.mt-2');
        if (hint) {
            hint.textContent = expiresAt ? `Expires: ${expiresAt}` : 'Copy this link and share it securely.';
        }
    };

    const createTakeLink = async (teacherId, teacherName, ttlHours) => {
        setError('');
        try {
            const res = await fetch('/api/v1/take-tokens', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ teacher_id: Number(teacherId), ttl_hours: Number(ttlHours), label: `takeattendance:${teacherName || teacherId}` })
            });
            const json = await res.json().catch(() => null);
            if (!res.ok) {
                setError(json?.message || 'Failed to generate take link');
                return null;
            }
            return json;
        } catch {
            setError('Failed to generate take link');
            return null;
        }
    };

    // Assign classes modal
    const modal = (() => {
        const root = document.createElement('div');
        root.id = 'classes-modal';
        root.className = 'fixed inset-0 bg-black/60 hidden z-50';
        root.innerHTML = `
            <div class="absolute inset-x-0 bottom-0 md:inset-y-0 md:right-0 md:left-auto md:w-[28rem] glass border border-white/10 shadow-glow rounded-t-2xl md:rounded-none md:rounded-l-2xl p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Assignments</p>
                        <p class="text-lg text-white font-medium" id="modal-title">Teacher</p>
                        <p class="text-xs text-slate-400 mt-1" id="modal-sub">Pick classes for this teacher.</p>
                    </div>
                    <button id="modal-close" class="h-10 w-10 rounded-xl glass flex items-center justify-center text-slate-200 hover:text-white transition shadow-ring">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <label class="text-xs text-slate-400">Assigned classes</label>
                    <select id="modal-classes" multiple class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60 min-h-[240px]"></select>
                    <p class="text-xs text-slate-400">Hold Ctrl (Windows) to select multiple.</p>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button id="modal-save" class="h-11 px-4 rounded-xl neon-pill text-sm font-medium flex items-center justify-center gap-2">
                        <i class="fas fa-floppy-disk"></i><span>Save</span>
                    </button>
                    <button id="modal-clear" class="h-11 px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center justify-center gap-2">
                        <i class="fas fa-ban"></i><span>Clear</span>
                    </button>
                </div>

                <div class="mt-4 text-sm text-red-300" id="modal-error"></div>
            </div>
        `;
        document.body.appendChild(root);

        const close = () => root.classList.add('hidden');
        root.addEventListener('click', (e) => { if (e.target === root) close(); });
        root.querySelector('#modal-close')?.addEventListener('click', close);

        return {
            root,
            close,
            title: root.querySelector('#modal-title'),
            sub: root.querySelector('#modal-sub'),
            select: root.querySelector('#modal-classes'),
            save: root.querySelector('#modal-save'),
            clear: root.querySelector('#modal-clear'),
            error: root.querySelector('#modal-error'),
        };
    })();

    let modalAccount = null;

    const openClassesModal = async (acct) => {
        modalAccount = acct;
        modal.error.textContent = '';
        modal.title.textContent = acct.teacher_name || 'Teacher';
        modal.sub.textContent = `username: ${acct.username} | teacher_id: ${acct.teacher_id}`;
        modal.root.classList.remove('hidden');

        // Populate options
        modal.select.innerHTML = state.classes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

        // Load current assignments
        try {
            const res = await fetch(`/api/v1/teacher-accounts/${acct.id}/classes`);
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed');

            const ids = new Set((json.class_ids || []).map(Number));
            Array.from(modal.select.options).forEach(o => { o.selected = ids.has(Number(o.value)); });
        } catch {
            modal.error.textContent = 'Failed to load current class assignments.';
        }
    };

    modal.clear?.addEventListener('click', () => {
        Array.from(modal.select.options).forEach(o => { o.selected = false; });
    });

    modal.save?.addEventListener('click', async () => {
        if (!modalAccount) return;
        modal.error.textContent = '';
        const classIds = Array.from(modal.select.selectedOptions || []).map(o => Number(o.value)).filter(v => Number.isFinite(v));
        const res = await updateAccount(modalAccount.id, { class_ids: classIds });
        if (!res) {
            modal.error.textContent = el('acct-error').textContent || 'Failed to save assignments.';
            return;
        }
        modal.close();
    });

    el('acct-search')?.addEventListener('input', (e) => {
        state.filter = e.target.value;
        render();
    });
    // Attach both direct and delegated handlers (guards against rare DOM timing issues).
    el('new-create')?.addEventListener('click', createAccount);
    document.addEventListener('click', (e) => {
        const hit = e.target?.closest?.('#new-create');
        if (!hit) return;
        e.preventDefault();
        createAccount();
    });

    // Allow Enter in username/password to trigger creation.
    ['new-username', 'new-password'].forEach((id) => {
        el(id)?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                createAccount();
            }
        });
    });
    el('pw-copy')?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(el('pw-value').textContent || '');
        } catch {}
    });

    // init
    Promise.all([loadTeachers(), loadClasses(), loadAccounts()]).catch(() => setError('Failed to load data'));
    document.addEventListener('finot:dateprefs', () => render());
</script>
@endpush

