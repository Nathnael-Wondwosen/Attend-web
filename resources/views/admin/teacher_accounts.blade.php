@extends('layouts.admin')

@section('title', 'Finot | Teacher Accounts')
@section('page-label', 'Access control')
@section('page-title', 'Teacher Accounts')
@section('page-subtitle', 'Create and manage teacher login accounts for the attendance mobile app')

@push('head')
<style>
    .no-scrollbar { scrollbar-width: none; -ms-overflow-style: none; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
</style>
@endpush

@section('content')
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-3">
        <div class="xl:col-span-2 glass rounded-xl p-3 shadow-glow flex flex-col gap-2.5">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2.5">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Directory</p>
                    <h3 class="text-base text-white font-medium">Accounts</h3>
                </div>
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <input id="acct-search" type="text" placeholder="Search teacher/username" class="rounded-lg bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-neon/60 w-full md:w-72" />
                    <button id="acct-new" type="button" class="h-8 px-3 rounded-lg neon-pill text-xs font-medium flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-plus"></i><span>New</span>
                    </button>
                </div>
            </div>

            <div class="glass rounded-xl border border-white/5 overflow-hidden">
                <div class="hidden lg:grid grid-cols-12 px-3 py-2 bg-white/5 text-[11px] text-slate-300 sticky top-0 z-10">
                    <span class="col-span-6">Username</span>
                    <span class="col-span-2">Classes</span>
                    <span class="col-span-2">Status</span>
                    <span class="col-span-2 text-right">Actions</span>
                </div>
                <div id="acct-list" class="divide-y divide-white/5 min-h-[180px] max-h-[60vh] overflow-y-auto no-scrollbar">
                    <p class="text-slate-400 text-sm px-4 py-3">Loading...</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400 pt-1">
                <span id="acct-page-summary">0 accounts</span>
                <div class="flex items-center gap-2">
                    <label for="acct-page-size" class="text-slate-400">Rows</label>
                    <select id="acct-page-size" class="h-8 rounded-md bg-white/5 border border-white/10 px-2 text-xs text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="40">40</option>
                        <option value="80">80</option>
                    </select>
                    <button id="acct-page-prev" class="h-8 px-2.5 rounded-md glass text-slate-200 hover:text-white transition disabled:opacity-40 disabled:cursor-not-allowed" type="button">Prev</button>
                    <span id="acct-page-indicator" class="min-w-[64px] text-center">1 / 1</span>
                    <button id="acct-page-next" class="h-8 px-2.5 rounded-md glass text-slate-200 hover:text-white transition disabled:opacity-40 disabled:cursor-not-allowed" type="button">Next</button>
                </div>
            </div>
        </div>

        <div class="glass rounded-xl p-3 shadow-glow flex flex-col gap-2.5">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Create</p>
                <h3 class="text-base text-white font-medium">New teacher account</h3>
            </div>

            <div class="space-y-2.5 text-sm text-slate-200">
                <div>
                    <label class="text-xs text-slate-400 mb-1 block">Teacher</label>
                    <select id="new-teacher" class="w-full rounded-lg bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                        <option value="">Select teacher</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-400 mb-1 block">Assign classes (optional)</label>
                    <select id="new-classes" multiple class="w-full rounded-lg bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60 min-h-[96px]">
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Hold Ctrl (Windows) to select multiple.</p>
                </div>
                <div>
                    <label class="text-xs text-slate-400 mb-1 block">Username</label>
                    <input id="new-username" type="text" class="w-full rounded-lg bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" placeholder="e.g. teacher10a" />
                </div>
                <div>
                    <label class="text-xs text-slate-400 mb-1 block">Password (optional)</label>
                    <input id="new-password" type="text" class="w-full rounded-lg bg-white/5 border border-white/10 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60" placeholder="Leave empty to auto-generate" />
                </div>
                <button id="new-create" type="button" class="w-full h-9 neon-pill rounded-lg px-3 text-left text-sm flex items-center gap-2">
                    <i class="fas fa-user-plus"></i><span>Create account</span>
                </button>
            </div>

            <div id="pw-box" class="glass rounded-lg p-3 border border-white/5 hidden">
                <p class="text-xs text-slate-400 mb-2">Generated password (show once)</p>
                <div class="flex items-center gap-2">
                    <code id="pw-value" class="flex-1 px-3 py-2 rounded-lg bg-white/5 text-slate-200 text-xs overflow-auto"></code>
                    <button id="pw-copy" type="button" class="h-8 px-3 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring">Copy</button>
                </div>
                <p class="text-xs text-slate-400 mt-2">Share this securely with the teacher.</p>
            </div>

            <div id="acct-status" class="text-xs text-slate-400"></div>
            <div id="acct-error" class="text-sm text-red-300"></div>
        </div>
    </div>

    <div id="acct-detail-backdrop" class="fixed inset-0 bg-black/60 hidden z-[90]"></div>
    <aside id="acct-detail-drawer" class="fixed inset-y-0 right-0 h-screen w-full max-w-md glass border-l border-white/10 shadow-glow z-[91] transform translate-x-full transition-transform duration-200 ease-out">
        <div class="h-full flex flex-col p-4 overflow-hidden">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Account Detail</p>
                    <h3 id="acct-detail-username" class="text-base text-white font-medium">Username</h3>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                        <p id="acct-detail-name" class="text-slate-400">Teacher</p>
                        <span id="acct-detail-status-chip" class="px-2 py-0.5 rounded-full bg-white/5 text-slate-200">--</span>
                        <span id="acct-detail-teacher-chip" class="px-2 py-0.5 rounded-full bg-white/5 text-slate-300">ID --</span>
                    </div>
                </div>
                <button id="acct-detail-close" class="h-9 w-9 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>

            <div class="mt-3 flex-1 min-h-0 overflow-y-auto no-scrollbar space-y-3 pr-1">
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="glass rounded-lg p-2 border border-white/5">
                        <p class="text-slate-400">Classes</p>
                        <p id="acct-detail-classes-count" class="text-slate-200 mt-1">--</p>
                    </div>
                    <div class="glass rounded-lg p-2 border border-white/5">
                        <p class="text-slate-400">Last Login</p>
                        <p id="acct-detail-last-login" class="text-slate-200 mt-1">--</p>
                    </div>
                </div>

                <div class="glass rounded-lg p-2 border border-white/5">
                    <p class="text-xs text-slate-400">Created</p>
                    <p id="acct-detail-created" class="text-xs text-slate-200 mt-1">--</p>
                </div>

                <div class="glass rounded-lg p-2 border border-white/5">
                    <p class="text-xs text-slate-400 mb-2">Assigned classes</p>
                    <div id="acct-detail-classes" class="grid grid-cols-2 gap-1.5 text-xs text-slate-200">
                        <p class="text-slate-400 col-span-2">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="pt-3 pb-2 border-t border-white/10 grid grid-cols-2 gap-2 sticky bottom-2 bg-midnight/80 backdrop-blur-sm">
                <button id="acct-detail-edit-classes" class="h-9 px-3 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring text-xs">Edit classes</button>
                <button id="acct-detail-toggle" class="h-9 px-3 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring text-xs">Enable/Disable</button>
                <button id="acct-detail-reset" class="h-9 px-3 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring text-xs">Change password</button>
                <button id="acct-detail-take" class="h-9 px-3 rounded-lg neon-pill text-xs">Generate link</button>
            </div>
        </div>
    </aside>
@endsection

@push('scripts')
<script>
    const state = {
        accounts: [],
        teachers: [],
        classes: [],
        filter: '',
        page: 1,
        pageSize: 20,
        detailAccountId: null,
        detailClassIds: [],
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
        const copy = (status || '--').toString();
        return `<span class="px-2.5 py-1 rounded-full bg-white/5 text-xs ${map[copy] || 'text-slate-300'}">${copy.toUpperCase()}</span>`;
    };

    const fmtDT = (iso) => {
        if (!iso) return '--';
        try {
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return String(iso);
            return window.FinotDate ? window.FinotDate.formatDateTime(iso) : d.toLocaleString();
        } catch {
            return String(iso);
        }
    };

    const detailEls = {
        backdrop: el('acct-detail-backdrop'),
        drawer: el('acct-detail-drawer'),
        username: el('acct-detail-username'),
        name: el('acct-detail-name'),
        statusChip: el('acct-detail-status-chip'),
        teacherChip: el('acct-detail-teacher-chip'),
        classesCount: el('acct-detail-classes-count'),
        lastLogin: el('acct-detail-last-login'),
        created: el('acct-detail-created'),
        classes: el('acct-detail-classes'),
        toggle: el('acct-detail-toggle'),
    };

    const getAssignedClassNames = (ids) => {
        const classMap = new Map(state.classes.map(c => [Number(c.id), c.name]));
        return (ids || []).map(id => classMap.get(Number(id)) || `Class #${id}`);
    };

    const setDrawerOpen = (open) => {
        detailEls.backdrop?.classList.toggle('hidden', !open);
        if (open) detailEls.drawer?.classList.remove('translate-x-full');
        else detailEls.drawer?.classList.add('translate-x-full');
    };

    const renderDetailDrawer = () => {
        const acct = state.accounts.find(x => String(x.id) === String(state.detailAccountId));
        if (!acct) return;
        const status = (acct.status || '').toString() || 'disabled';
        const classNames = getAssignedClassNames(state.detailClassIds);

        detailEls.username.textContent = acct.username || '--';
        detailEls.name.textContent = acct.teacher_name || 'Teacher';
        detailEls.statusChip.textContent = status.toUpperCase();
        detailEls.statusChip.className = `px-2 py-0.5 rounded-full bg-white/5 ${status === 'active' ? 'text-neon' : 'text-slate-300'}`;
        detailEls.teacherChip.textContent = `ID ${acct.teacher_id ?? '--'}`;
        detailEls.classesCount.textContent = String(classNames.length);
        detailEls.lastLogin.textContent = fmtDT(acct.last_login);
        detailEls.created.textContent = fmtDT(acct.created_at);
        detailEls.toggle.textContent = status === 'active' ? 'Disable account' : 'Enable account';

        if (!classNames.length) {
            detailEls.classes.innerHTML = '<p class="text-slate-400 col-span-2">No classes assigned.</p>';
        } else {
            detailEls.classes.innerHTML = classNames.map(name => `<div class="glass rounded-md px-2 py-1 border border-white/5">${name}</div>`).join('');
        }
    };

    const openDetailDrawer = async (acct) => {
        state.detailAccountId = acct.id;
        state.detailClassIds = [];
        detailEls.classes.innerHTML = '<p class="text-slate-400">Loading...</p>';
        renderDetailDrawer();
        setDrawerOpen(true);
        try {
            const res = await fetch(`/api/v1/teacher-accounts/${acct.id}/classes`);
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed');
            state.detailClassIds = (json.class_ids || []).map(Number);
        } catch {
            state.detailClassIds = [];
            detailEls.classes.innerHTML = '<p class="text-red-300 col-span-2">Failed to load assigned classes.</p>';
            return;
        }
        renderDetailDrawer();
    };

    const render = () => {
        const q = state.filter.trim().toLowerCase();
        const filtered = state.accounts.filter(a => {
            if (!q) return true;
            return String(a.teacher_name || '').toLowerCase().includes(q) ||
                String(a.username || '').toLowerCase().includes(q);
        });
        const total = filtered.length;
        const pageSize = Math.max(1, Number(state.pageSize) || 20);
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        state.page = Math.min(Math.max(1, state.page), totalPages);
        const startIdx = (state.page - 1) * pageSize;
        const data = filtered.slice(startIdx, startIdx + pageSize);

        const summary = el('acct-page-summary');
        const indicator = el('acct-page-indicator');
        const prev = el('acct-page-prev');
        const next = el('acct-page-next');
        if (summary) {
            if (!total) summary.textContent = '0 accounts';
            else summary.textContent = `Showing ${startIdx + 1}-${Math.min(startIdx + data.length, total)} of ${total}`;
        }
        if (indicator) indicator.textContent = `${state.page} / ${totalPages}`;
        if (prev) prev.disabled = state.page <= 1;
        if (next) next.disabled = state.page >= totalPages;

        const wrap = el('acct-list');
        if (!data.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No accounts.</p>';
            return;
        }

        wrap.innerHTML = data.map(a => {
            const classesCount = Number(a.assigned_classes_count || 0);
            const status = (a.status || '').toString();

            return `
                <div class="px-3 py-2 hover:bg-white/5 transition">
                    <div class="flex items-center justify-between gap-3 lg:hidden">
                        <div class="min-w-0">
                            <button type="button" data-open-detail="${a.id}" class="text-sm text-white hover:text-neon transition truncate text-left">${a.username || '--'}</button>
                            <p class="text-xs text-slate-400 mt-0.5">${classesCount} classes</p>
                        </div>
                        <div class="flex items-center gap-2">
                            ${pill(status)}
                            <button type="button" data-open-detail="${a.id}" class="h-8 px-2.5 rounded-md glass text-slate-200 hover:text-white transition text-xs">Detail</button>
                        </div>
                    </div>

                    <div class="hidden lg:grid grid-cols-12 gap-2 items-center">
                        <div class="col-span-6 text-slate-200 min-w-0">
                            <button type="button" data-open-detail="${a.id}" class="text-sm text-white hover:text-neon transition truncate text-left">${a.username || '--'}</button>
                        </div>
                        <div class="col-span-2 text-slate-200">
                            <span class="px-2.5 py-1 rounded-full bg-white/5 text-xs">${classesCount}</span>
                        </div>
                        <div class="col-span-2">${pill(status)}</div>
                        <div class="col-span-2 flex justify-end">
                            <button type="button" data-open-detail="${a.id}" class="h-8 px-2.5 rounded-md glass text-slate-200 hover:text-white transition text-xs">Detail</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        wrap.querySelectorAll('button[data-open-detail]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-open-detail');
                const acct = state.accounts.find(x => String(x.id) === String(id));
                if (acct) await openDetailDrawer(acct);
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
        if (state.detailAccountId) {
            const acct = state.accounts.find(x => String(x.id) === String(state.detailAccountId));
            if (acct) renderDetailDrawer();
            else setDrawerOpen(false);
        }
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
        try {
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
            if (state.detailAccountId && String(state.detailAccountId) === String(id)) {
                renderDetailDrawer();
            }
            return json;
        } catch {
            setError('Network error while updating account.');
            return null;
        }
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
        root.className = 'fixed inset-0 bg-black/60 hidden z-[70]';
        root.innerHTML = `
            <div class="absolute inset-x-0 bottom-0 md:inset-y-0 md:right-0 md:left-auto md:w-[24rem] glass border border-white/10 shadow-glow rounded-t-xl md:rounded-none md:rounded-l-xl p-3.5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Assignments</p>
                        <p class="text-lg text-white font-medium" id="modal-title">Teacher</p>
                        <p class="text-xs text-slate-400 mt-1" id="modal-sub">Pick classes for this teacher.</p>
                    </div>
                    <button id="modal-close" class="h-9 w-9 rounded-lg glass flex items-center justify-center text-slate-200 hover:text-white transition shadow-ring">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                <div class="mt-2.5 space-y-2">
                    <label class="text-xs text-slate-400">Assigned classes</label>
                    <div id="modal-classes-list" class="max-h-56 overflow-y-auto no-scrollbar space-y-1.5 rounded-lg border border-white/10 p-2 bg-white/5"></div>
                </div>

                <div class="mt-2.5 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button id="modal-save" class="h-9 px-3 rounded-lg neon-pill text-sm font-medium flex items-center justify-center gap-2">
                        <i class="fas fa-floppy-disk"></i><span>Save</span>
                    </button>
                    <button id="modal-clear" class="h-9 px-3 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring flex items-center justify-center gap-2">
                        <i class="fas fa-ban"></i><span>Clear</span>
                    </button>
                </div>

                <div class="mt-3 text-xs text-slate-300" id="modal-status"></div>
                <div class="mt-1 text-sm text-red-300" id="modal-error"></div>
            </div>
        `;
        document.body.appendChild(root);

        const close = () => {
            root.classList.add('hidden');
        };
        root.addEventListener('click', (e) => { if (e.target === root) close(); });
        root.querySelector('#modal-close')?.addEventListener('click', close);

        return {
            root,
            close,
            title: root.querySelector('#modal-title'),
            sub: root.querySelector('#modal-sub'),
            list: root.querySelector('#modal-classes-list'),
            save: root.querySelector('#modal-save'),
            clear: root.querySelector('#modal-clear'),
            status: root.querySelector('#modal-status'),
            error: root.querySelector('#modal-error'),
        };
    })();

    let modalAccount = null;
    let modalSelectedClassIds = new Set();

    const renderModalClassPicker = () => {
        const rows = state.classes || [];
        modal.list.innerHTML = rows.map(c => {
            const selected = modalSelectedClassIds.has(Number(c.id));
            return `
                <button type="button" data-class-option="${c.id}" class="w-full text-left rounded-md px-2.5 py-2 border transition text-xs flex items-center justify-between gap-2 ${selected ? 'bg-white/10 border-neon/50 text-white' : 'bg-white/5 border-white/10 text-slate-200 hover:bg-white/10'}">
                    <span class="truncate">${c.name}</span>
                    <span class="px-2 py-0.5 rounded-full ${selected ? 'bg-neon/20 text-neon border border-neon/40' : 'bg-white/10 text-slate-300 border border-white/10'}">${selected ? 'ON' : 'OFF'}</span>
                </button>
            `;
        }).join('');

        modal.list.querySelectorAll('button[data-class-option]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-class-option'));
                if (!Number.isFinite(id)) return;
                if (modalSelectedClassIds.has(id)) modalSelectedClassIds.delete(id);
                else modalSelectedClassIds.add(id);
                renderModalClassPicker();
            });
        });
    };

    const openClassesModal = async (acct) => {
        modalAccount = acct;
        modal.error.textContent = '';
        modal.status.textContent = '';
        modal.title.textContent = acct.teacher_name || 'Teacher';
        modal.sub.textContent = `username: ${acct.username} | teacher_id: ${acct.teacher_id}`;
        modal.root.classList.remove('hidden');
        modalSelectedClassIds = new Set();
        renderModalClassPicker();

        // Load current assignments
        try {
            const res = await fetch(`/api/v1/teacher-accounts/${acct.id}/classes`);
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed');
            modalSelectedClassIds = new Set((json.class_ids || []).map(Number));
            renderModalClassPicker();
        } catch {
            modal.error.textContent = 'Failed to load current class assignments.';
        }
    };

    modal.clear?.addEventListener('click', () => {
        modalSelectedClassIds = new Set();
        renderModalClassPicker();
        modal.status.textContent = 'Selection cleared.';
        modal.error.textContent = '';
    });

    modal.save?.addEventListener('click', async () => {
        if (!modalAccount) return;
        modal.error.textContent = '';
        modal.status.textContent = 'Saving...';
        modal.save.disabled = true;
        modal.save.classList.add('opacity-60', 'cursor-not-allowed');
        const classIds = Array.from(modalSelectedClassIds).filter(v => Number.isFinite(v));
        const res = await updateAccount(modalAccount.id, { class_ids: classIds });
        if (!res) {
            modal.error.textContent = el('acct-error').textContent || 'Failed to save assignments.';
            modal.status.textContent = '';
            modal.save.disabled = false;
            modal.save.classList.remove('opacity-60', 'cursor-not-allowed');
            return;
        }
        modal.status.textContent = 'Saved successfully.';
        modal.error.textContent = '';
        modal.save.disabled = false;
        modal.save.classList.remove('opacity-60', 'cursor-not-allowed');
        state.detailClassIds = classIds;
        renderDetailDrawer();
        setTimeout(() => {
            modal.status.textContent = '';
            modal.close();
        }, 700);
    });

    el('acct-search')?.addEventListener('input', (e) => {
        state.filter = e.target.value;
        state.page = 1;
        render();
    });
    el('acct-page-size')?.addEventListener('change', (e) => {
        state.pageSize = Number(e.target.value) || 20;
        state.page = 1;
        render();
    });
    el('acct-page-prev')?.addEventListener('click', () => {
        state.page = Math.max(1, state.page - 1);
        render();
    });
    el('acct-page-next')?.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(state.accounts.filter(a => {
            const q = state.filter.trim().toLowerCase();
            if (!q) return true;
            return String(a.teacher_name || '').toLowerCase().includes(q) ||
                String(a.username || '').toLowerCase().includes(q);
        }).length / Math.max(1, Number(state.pageSize) || 20)));
        state.page = Math.min(totalPages, state.page + 1);
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

    el('acct-detail-close')?.addEventListener('click', () => setDrawerOpen(false));
    el('acct-detail-backdrop')?.addEventListener('click', () => setDrawerOpen(false));

    el('acct-detail-edit-classes')?.addEventListener('click', async () => {
        const acct = state.accounts.find(x => String(x.id) === String(state.detailAccountId));
        if (!acct) return;
        await openClassesModal(acct);
    });

    el('acct-detail-toggle')?.addEventListener('click', async () => {
        const acct = state.accounts.find(x => String(x.id) === String(state.detailAccountId));
        if (!acct) return;
        const next = acct.status === 'active' ? 'disabled' : 'active';
        await updateAccount(acct.id, { status: next });
        const updated = state.accounts.find(x => String(x.id) === String(acct.id));
        if (updated) renderDetailDrawer();
    });

    el('acct-detail-reset')?.addEventListener('click', async () => {
        const acct = state.accounts.find(x => String(x.id) === String(state.detailAccountId));
        if (!acct) return;
        const newPassword = prompt('Enter new password (minimum 8 characters):', '');
        if (newPassword === null) return;
        const trimmed = String(newPassword || '').trim();
        if (trimmed.length < 8) {
            setError('Password must be at least 8 characters.');
            return;
        }
        const confirmPassword = prompt('Confirm new password:', '');
        if (confirmPassword === null) return;
        if (trimmed !== String(confirmPassword || '')) {
            setError('Password confirmation does not match.');
            return;
        }
        const res = await updateAccount(acct.id, { reset_password: true, new_password: trimmed });
        if (res && res.password) showPassword(res.password);
    });

    el('acct-detail-take')?.addEventListener('click', async () => {
        const acct = state.accounts.find(x => String(x.id) === String(state.detailAccountId));
        if (!acct) return;
        const ttl = 24;
        if (!confirm(`Generate a ${ttl}h take-attendance link for ${acct.teacher_name || acct.username}?`)) return;
        const out = await createTakeLink(acct.teacher_id, acct.teacher_name || acct.username, ttl);
        if (out?.take_url) showTakeLink(out.take_url, out.expires_at);
    });

    // init
    Promise.all([loadTeachers(), loadClasses(), loadAccounts()]).catch(() => setError('Failed to load data'));
    document.addEventListener('finot:dateprefs', () => render());
</script>
@endpush


