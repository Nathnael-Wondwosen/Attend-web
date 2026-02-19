
    const rep = {
        rows: [],
        visibleRows: [],
        savedTerms: [],
        termDefaults: {},
        activeSavedTermId: null,
        classId: null,
        range: { from: null, to: null },
        sessions: 0,
    };

    const $ = (id) => document.getElementById(id);
    const openTermsDrawer = () => {
        $('rep-terms-drawer-backdrop')?.classList.remove('hidden');
        $('rep-terms-drawer')?.classList.remove('translate-x-full');
    };
    const closeTermsDrawer = () => {
        $('rep-terms-drawer-backdrop')?.classList.add('hidden');
        $('rep-terms-drawer')?.classList.add('translate-x-full');
    };
    const setTermHint = (msg) => {
        const el = $('rep-term-default-hint-drawer');
        if (el) el.textContent = msg || '';
    };
    const TERM_ROWS = [
        { key: 't1', label: '1 Term' },
        { key: 't2', label: '2 Term' },
        { key: 't3', label: '3 Term' },
        { key: 't4', label: '4 Term' },
        { key: 'summer', label: 'Summer Class' },
    ];

    const resultPill = (rate) => {
        if (rate === null || rate === undefined) {
            return '<span class="px-3 py-1 rounded-full bg-white/5 text-xs text-slate-300">--</span>';
        }
        return `<span class="px-3 py-1 rounded-full bg-white/5 text-xs text-slate-200">${Number(rate).toFixed(1)}%</span>`;
    };

    const toYmd = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };

    const endOfMonth = (year, month1to12) => {
        return new Date(year, month1to12, 0);
    };

    const computeRange = () => {
        const t = $('rep-period-type').value;
        const year = Number($('rep-year').value);

        if (t === 'semester') {
            const termPlan = String($('rep-term-plan').value || 't1');
            const preset = rep.termDefaults[termPlan] || null;
            const rawStart = preset?.from || '';
            const rawEnd = preset?.to || '';
            if (!rawStart || !rawEnd) return null;

            let start = new Date(rawStart + 'T00:00:00');
            let end = new Date(rawEnd + 'T00:00:00');
            if (start > end) [start, end] = [end, start];
            const termLabel = termPlan === 'summer' ? 'Summer Class' : `${termPlan.replace('t', '')} Term`;
            return { from: toYmd(start), to: toYmd(end), label: `${termLabel}` };
        }

        if (t === 'academic') {
            const ay = Number($('rep-academic-year').value);
            const term = $('rep-academic-term').value || 'full';
            if (!ay) return null;
            if (term === 'term1') {
                return { from: `${ay}-09-01`, to: `${ay + 1}-01-31`, label: `Academic ${ay}/${ay + 1} - Term 1 (Sep-Jan)` };
            }
            if (term === 'term2') {
                return { from: `${ay + 1}-02-01`, to: `${ay + 1}-06-30`, label: `Academic ${ay}/${ay + 1} - Term 2 (Feb-Jun)` };
            }
            return { from: `${ay}-09-01`, to: `${ay + 1}-06-30`, label: `Academic year ${ay}/${ay + 1}` };
        }

        if (t === 'year') {
            if (!year) return null;
            return { from: `${year}-01-01`, to: `${year}-12-31`, label: `Full year ${year}` };
        }

        if (t === 'month') {
            if (!year) return null;
            const month = Number($('rep-month').value || 1);
            const from = `${year}-${String(month).padStart(2, '0')}-01`;
            const to = toYmd(endOfMonth(year, month));
            const monthName = $('rep-month').selectedOptions[0]?.textContent || 'Month';
            return { from, to, label: `${monthName} ${year}` };
        }

        const from = $('rep-from').value;
        const to = $('rep-to').value;
        if (!from || !to) return null;
        if (from <= to) return { from, to, label: `Custom: ${from} to ${to}` };
        return { from: to, to: from, label: `Custom: ${to} to ${from}` };
    };

    const renderPeriodControls = () => {
        const t = $('rep-period-type').value;
        $('rep-year-wrap').classList.toggle('hidden', t === 'academic' || t === 'semester');
        $('rep-semester-wrap').classList.toggle('hidden', t !== 'semester');
        $('rep-academic-year-wrap').classList.toggle('hidden', t !== 'academic');
        $('rep-academic-term-wrap').classList.toggle('hidden', t !== 'academic');
        $('rep-month-wrap').classList.toggle('hidden', t !== 'month');
        $('rep-custom-wrap').classList.toggle('hidden', t !== 'custom');

        // In semester mode, keep selected-period and date filters in one row.
        const periodCard = $('rep-period-card');
        periodCard.classList.toggle('lg:col-span-12', true);
        periodCard.classList.remove('lg:col-span-4');

        if (t !== 'semester') setTermHint('');

        const range = computeRange();
        $('rep-period-summary').textContent = range ? `${range.label} | ${range.from} to ${range.to}` : 'Please select valid period values.';
    };

    const setStats = () => {
        const rows = rep.visibleRows;
        $('rep-students').textContent = rows.length;
        $('rep-sessions').textContent = rep.sessions;

        if (!rows.length) {
            $('rep-average').textContent = '--';
            $('rep-risk').textContent = '--';
            return;
        }

        const rates = rows
            .map(r => (r.present_rate === null || r.present_rate === undefined) ? null : Number(r.present_rate))
            .filter(v => v !== null && Number.isFinite(v));

        const avg = rates.length ? (rates.reduce((a, b) => a + b, 0) / rates.length) : null;
        const risk = rates.filter(v => v < 75).length;

        $('rep-average').textContent = avg === null ? '--' : `${avg.toFixed(1)}%`;
        $('rep-risk').textContent = String(risk);
    };

    const renderRows = () => {
        const wrap = $('rep-rows');
        if (!rep.rows.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No attendance records found for this class and period.</p>';
            rep.visibleRows = [];
            setStats();
            return;
        }

        const mode = $('rep-student-filter').value || 'all';
        rep.visibleRows = rep.rows.filter((r) => {
            const rate = Number(r.present_rate ?? -1);
            if (!Number.isFinite(rate) || rate < 0) return mode === 'all';
            if (mode === 'risk') return rate < 75;
            if (mode === 'ok') return rate >= 75;
            return true;
        });

        if (!rep.visibleRows.length) {
            wrap.innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">No students match the selected filter.</p>';
            setStats();
            return;
        }

        const sorted = [...rep.visibleRows].sort((a, b) => {
            const ar = Number(a.present_rate ?? -1);
            const br = Number(b.present_rate ?? -1);
            if (ar !== br) return ar - br;
            return String(a.full_name || '').localeCompare(String(b.full_name || ''));
        });

        wrap.innerHTML = sorted.map(r => {
            const rate = (r.present_rate === null || r.present_rate === undefined) ? '--' : `${Number(r.present_rate).toFixed(1)}%`;
            return `
                <div class="grid grid-cols-1 lg:grid-cols-12 px-4 py-3 gap-2 items-center">
                    <div class="lg:col-span-4">
                        <p class="text-white">${r.full_name}</p>
                        <p class="text-xs text-slate-400">ID: ${r.student_id}</p>
                    </div>
                    <div class="lg:col-span-1 text-slate-200">${r.present}</div>
                    <div class="lg:col-span-2 text-slate-200">${r.permission}</div>
                    <div class="lg:col-span-1 text-slate-200">${r.absent}</div>
                    <div class="lg:col-span-1 text-slate-200">${r.unmarked}</div>
                    <div class="lg:col-span-1 text-slate-200">${rate}</div>
                    <div class="lg:col-span-2 flex lg:justify-end">${resultPill(r.present_rate)}</div>
                </div>
            `;
        }).join('');

        setStats();
    };

    const loadClasses = async () => {
        const res = await fetch('/api/v1/classes');
        const json = await res.json().catch(() => null);
        const rows = (Array.isArray(json?.data) ? json.data : (Array.isArray(json) ? json : []));
        $('rep-class').innerHTML = '<option value="">Select class</option>' + rows.map(c => {
            const name = c.name || `Grade ${c.grade || ''}${c.section || ''}`.trim();
            return `<option value="${c.id}">${name}</option>`;
        }).join('');
    };

    const readTermEditorDates = (termKey) => {
        const from = $(`rep-term-${termKey}-from`)?.value || '';
        const to = $(`rep-term-${termKey}-to`)?.value || '';
        return { from: String(from).trim(), to: String(to).trim() };
    };

    const renderTermDefaultEditor = () => {
        const wrap = $('rep-term-default-editor');
        const classId = Number($('rep-class').value || 0);
        if (!classId) {
            wrap.innerHTML = '<p class="text-[11px] text-slate-500">Select a class to configure term dates.</p>';
            return;
        }

        wrap.innerHTML = TERM_ROWS.map((t) => {
            const d = rep.termDefaults[t.key] || {};
            const from = d.from || '';
            const to = d.to || '';
            return `
                <div class="rounded-md border border-white/10 bg-white/5 p-1.5">
                    <p class="text-[11px] text-white mb-1">${t.label}</p>
                    <div class="grid grid-cols-2 gap-1.5">
                        <input id="rep-term-${t.key}-from" type="date" value="${from}" class="w-full rounded-md bg-white/5 border border-white/10 px-2 py-1.5 text-[11px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40" />
                        <input id="rep-term-${t.key}-to" type="date" value="${to}" class="w-full rounded-md bg-white/5 border border-white/10 px-2 py-1.5 text-[11px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40" />
                    </div>
                    <div class="mt-1.5 flex justify-end">
                        <button type="button" data-save-term-default="${t.key}" class="h-6 px-1.5 rounded-md glass text-[10px] text-slate-200 hover:text-white">Save</button>
                    </div>
                </div>
            `;
        }).join('');

        wrap.querySelectorAll('[data-save-term-default]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const termKey = String(btn.getAttribute('data-save-term-default') || '');
                if (!termKey) return;
                await saveTermDefault(termKey);
            });
        });
    };

    const loadTermDefaults = async (classId) => {
        rep.termDefaults = {};
        if (!classId) {
            renderTermDefaultEditor();
            setTermHint('');
            renderTermDefaultsDrawer();
            return;
        }
        try {
            const res = await fetch(`/api/v1/reports/class/${classId}/semester-defaults`);
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed to load term defaults');
            rep.termDefaults = json?.defaults || {};
        } catch {
            rep.termDefaults = {};
        }
        renderTermDefaultEditor();
        renderTermDefaultsDrawer();
        renderPeriodControls();
    };

    const saveTermDefault = async (termKeyArg = null) => {
        $('rep-error').textContent = '';
        const classId = Number($('rep-class').value || 0);
        if (!classId) {
            $('rep-error').textContent = 'Select a class first.';
            return;
        }
        const termKey = String(termKeyArg || $('rep-term-plan').value || 't1');
        const dates = readTermEditorDates(termKey);
        const from = dates.from;
        const to = dates.to;
        if (!from || !to) {
            $('rep-error').textContent = 'Set start and end dates first.';
            return;
        }
        const label = termKey === 'summer' ? 'Summer Class' : `${termKey.replace('t', '')} Term`;
        try {
            const res = await fetch(`/api/v1/reports/class/${classId}/semester-defaults/${encodeURIComponent(termKey)}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ from, to, label }),
            });
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed to save term dates');
            await loadTermDefaults(classId);
            setTermHint(`Saved default dates for ${label}.`);
        } catch (e) {
            $('rep-error').textContent = e?.message || 'Failed to save term dates';
        }
    };

    const saveAllTermDefaults = async () => {
        const classId = Number($('rep-class').value || 0);
        if (!classId) {
            $('rep-error').textContent = 'Select a class first.';
            return;
        }

        for (const t of TERM_ROWS) {
            const { from, to } = readTermEditorDates(t.key);
            if (!from || !to) continue;
            // eslint-disable-next-line no-await-in-loop
            await saveTermDefault(t.key);
        }
        setTermHint('Saved all provided term date values.');
    };

    const groupByYear = (rows) => {
        const out = {};
        rows.forEach((r) => {
            const y = (r.from || '').slice(0, 4) || 'Unknown';
            if (!out[y]) out[y] = [];
            out[y].push(r);
        });
        return out;
    };

    const renderTermDefaultsDrawer = () => {
        const wrap = $('rep-term-defaults-groups');
        const classId = $('rep-class').value;
        if (!classId) {
            wrap.innerHTML = '<p class="text-[11px] text-slate-500">Select a class to load defaults.</p>';
            return;
        }
        const rows = Object.values(rep.termDefaults || {});
        if (!rows.length) {
            wrap.innerHTML = '<p class="text-[11px] text-slate-500">No semester defaults saved yet.</p>';
            return;
        }
        const grouped = groupByYear(rows);
        const years = Object.keys(grouped).sort((a, b) => String(b).localeCompare(String(a)));
        wrap.innerHTML = years.map((year) => `
            <div class="space-y-1.5">
                <p class="text-[11px] text-slate-400">${year}</p>
                <div class="space-y-1.5">
                    ${grouped[year].map((d) => `
                        <div class="rounded-md border border-white/10 bg-white/5 p-1.5">
                            <p class="text-[11px] text-white">${d.label || d.term_key}</p>
                            <p class="text-[11px] text-slate-400 mt-1">${d.from} to ${d.to}</p>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
    };

    const renderSavedTerms = () => {
        const wrap = $('rep-saved-terms-groups');
        const countEl = $('rep-saved-terms-count');
        const classId = $('rep-class').value;
        if (!classId) {
            wrap.innerHTML = '<p class="text-[11px] text-slate-500">Select a class to load saved terms.</p>';
            if (countEl) countEl.textContent = 'Select a class to manage terms.';
            return;
        }

        if (!rep.savedTerms.length) {
            wrap.innerHTML = '<p class="text-[11px] text-slate-500">No saved terms yet for this class.</p>';
            if (countEl) countEl.textContent = 'No saved terms yet.';
            return;
        }

        const grouped = groupByYear(rep.savedTerms);
        const years = Object.keys(grouped).sort((a, b) => String(b).localeCompare(String(a)));
        wrap.innerHTML = years.map((year) => `
            <div class="space-y-1.5">
                <p class="text-[11px] text-slate-400">${year}</p>
                <div class="space-y-1.5">
                    ${grouped[year].map((t) => {
                        const active = rep.activeSavedTermId === t.id;
                        const base = active ? 'border-neon/60 bg-white/10' : 'border-white/10 bg-white/5';
                        return `
                            <div class="rounded-md border ${base} p-1.5">
                                <p class="text-[11px] text-white">${t.label}</p>
                                <p class="text-[11px] text-slate-400 mt-1">${t.from} to ${t.to}</p>
                                <div class="mt-1.5 flex items-center gap-1.5">
                                    <button type="button" data-saved-id="${t.id}" class="h-6 px-1.5 rounded-md glass text-[10px] text-slate-200 hover:text-white">Load</button>
                                    <button type="button" data-edit-saved-id="${t.id}" class="h-6 px-1.5 rounded-md glass text-[10px] text-slate-200 hover:text-cyan-300">Edit</button>
                                    <button type="button" data-delete-saved-id="${t.id}" class="h-6 px-1.5 rounded-md glass text-[10px] text-slate-200 hover:text-red-300">Delete</button>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `).join('');

        if (countEl) countEl.textContent = `${rep.savedTerms.length} saved term(s).`;

        wrap.querySelectorAll('[data-saved-id]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-saved-id'));
                const found = rep.savedTerms.find((x) => x.id === id);
                if (!found) return;
                rep.activeSavedTermId = found.id;
                renderSavedTerms();
                $('rep-period-type').value = 'custom';
                $('rep-from').value = found.from;
                $('rep-to').value = found.to;
                renderPeriodControls();
                loadReportByRange(Number($('rep-class').value), { from: found.from, to: found.to }, found.label);
                closeTermsDrawer();
            });
        });

        wrap.querySelectorAll('[data-delete-saved-id]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = Number(btn.getAttribute('data-delete-saved-id'));
                if (!id) return;
                if (!confirm('Delete this saved term?')) return;
                try {
                    const res = await fetch(`/api/v1/reports/saved-terms/${id}`, { method: 'DELETE' });
                    const json = await res.json().catch(() => null);
                    if (!res.ok) throw new Error(json?.message || 'Delete failed');
                    rep.savedTerms = rep.savedTerms.filter((x) => x.id !== id);
                    if (rep.activeSavedTermId === id) rep.activeSavedTermId = null;
                    renderSavedTerms();
                } catch (e) {
                    $('rep-error').textContent = e?.message || 'Delete failed';
                }
            });
        });

        wrap.querySelectorAll('[data-edit-saved-id]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = Number(btn.getAttribute('data-edit-saved-id'));
                const found = rep.savedTerms.find((x) => x.id === id);
                if (!found) return;

                const label = prompt('Edit saved term name:', found.label || '');
                if (label === null) return;
                const cleanLabel = String(label).trim();
                if (!cleanLabel) return;

                const from = prompt('Edit start date (YYYY-MM-DD):', found.from || '');
                if (from === null) return;
                const to = prompt('Edit end date (YYYY-MM-DD):', found.to || '');
                if (to === null) return;

                const cleanFrom = String(from).trim();
                const cleanTo = String(to).trim();
                if (!cleanFrom || !cleanTo) return;

                try {
                    const res = await fetch(`/api/v1/reports/saved-terms/${id}`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ label: cleanLabel, from: cleanFrom, to: cleanTo }),
                    });
                    const json = await res.json().catch(() => null);
                    if (!res.ok) throw new Error(json?.message || 'Update failed');
                    await loadSavedTerms(Number($('rep-class').value || 0));
                } catch (e) {
                    $('rep-error').textContent = e?.message || 'Update failed';
                }
            });
        });
    };

    const loadSavedTerms = async (classId) => {
        rep.savedTerms = [];
        rep.activeSavedTermId = null;
        if (!classId) {
            renderSavedTerms();
            return;
        }

        try {
            const res = await fetch(`/api/v1/reports/class/${classId}/saved-terms`);
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed to load saved terms');
            rep.savedTerms = Array.isArray(json?.data) ? json.data : [];
            renderSavedTerms();
        } catch {
            rep.savedTerms = [];
            renderSavedTerms();
        }
    };

    const loadReportByRange = async (classId, range, label = null) => {
        rep.classId = Number(classId);
        rep.range = { from: range.from, to: range.to };
        $('rep-period-summary').textContent = label ? `${label} | ${range.from} to ${range.to}` : `${range.from} to ${range.to}`;
        $('rep-rows').innerHTML = '<p class="text-slate-400 text-sm px-4 py-3">Loading...</p>';

        try {
            const url = `/api/v1/reports/class/${classId}/range?from=${encodeURIComponent(range.from)}&to=${encodeURIComponent(range.to)}`;
            const res = await fetch(url);
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed to load report');
            rep.rows = Array.isArray(json?.rows) ? json.rows : [];
            rep.sessions = Number(json?.sessions?.count || 0);
            renderRows();
        } catch (e) {
            rep.rows = [];
            rep.sessions = 0;
            renderRows();
            $('rep-error').textContent = e?.message || 'Failed to load report';
        }
    };

    const saveCurrentTerm = async () => {
        $('rep-error').textContent = '';
        const classId = $('rep-class').value;
        if (!classId) {
            $('rep-error').textContent = 'Select a class first.';
            return;
        }

        const range = computeRange();
        if (!range) {
            $('rep-error').textContent = 'Select a valid period first.';
            return;
        }

        const suggested = range.label || `${range.from} to ${range.to}`;
        const label = prompt('Saved term name:', suggested);
        if (label === null) return;
        const clean = String(label).trim();
        if (!clean) return;

        try {
            const res = await fetch(`/api/v1/reports/class/${classId}/saved-terms`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    label: clean,
                    from: range.from,
                    to: range.to,
                    period_type: $('rep-period-type').value,
                    term_key: $('rep-term-plan')?.value || null,
                    meta: { period_summary: $('rep-period-summary').textContent || null },
                }),
            });
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed to save term');
            await loadSavedTerms(Number(classId));
        } catch (e) {
            $('rep-error').textContent = e?.message || 'Failed to save term';
        }
    };

    const loadReport = async () => {
        $('rep-error').textContent = '';

        const classId = $('rep-class').value;
        if (!classId) {
            $('rep-error').textContent = 'Please select a class.';
            return;
        }

        const range = computeRange();
        if (!range) {
            $('rep-error').textContent = 'Please complete period selection.';
            return;
        }

        rep.classId = Number(classId);
        rep.range = { from: range.from, to: range.to };
        rep.activeSavedTermId = null;
        renderSavedTerms();
        await loadReportByRange(classId, range, range.label);
    };

    const exportCsv = async () => {
        $('rep-error').textContent = '';

        const classId = $('rep-class').value;
        if (!classId) return;

        const range = computeRange();
        if (!range) return;

        try {
            const endpoint = `/api/v1/reports/class/${classId}/range?from=${encodeURIComponent(range.from)}&to=${encodeURIComponent(range.to)}&format=csv`;
            const res = await fetch(endpoint, { headers: { 'Accept': 'text/csv' } });
            if (!res.ok) throw new Error('CSV export failed');

            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `attendance_report_class_${classId}_${range.from}_to_${range.to}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (e) {
            $('rep-error').textContent = e?.message || 'CSV export failed';
        }
    };

    const printReport = () => {
        const classLabel = $('rep-class').selectedOptions[0]?.textContent || 'Class';
        const period = $('rep-period-summary').textContent || '';
        const filter = $('rep-student-filter').selectedOptions[0]?.textContent || 'All students';
        const title = `${classLabel} Attendance Report`;

        const rowsHtml = (rep.visibleRows.length ? rep.visibleRows : rep.rows).map((r) => {
            const rate = (r.present_rate === null || r.present_rate === undefined) ? '--' : `${Number(r.present_rate).toFixed(1)}%`;
            return `
                <tr>
                    <td>${r.full_name} (#${r.student_id})</td>
                    <td>${r.present}</td>
                    <td>${r.permission}</td>
                    <td>${r.absent}</td>
                    <td>${r.unmarked}</td>
                    <td>${rate}</td>
                    <td>${rate}</td>
                </tr>
            `;
        }).join('');

        const printWindow = window.open('', '_blank');
        if (!printWindow) return;

        printWindow.document.write(`
            <html>
            <head>
                <title>${title}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; color: #111; }
                    h1 { margin: 0 0 8px 0; font-size: 22px; }
                    p { margin: 3px 0; font-size: 13px; color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
                    th, td { border: 1px solid #ccc; padding: 8px; font-size: 12px; text-align: left; }
                    th { background: #f3f4f6; }
                </style>
            </head>
            <body>
                <h1>${title}</h1>
                <p><strong>Period:</strong> ${period}</p>
                <p><strong>Filter:</strong> ${filter}</p>
                <p><strong>Students:</strong> ${$('rep-students').textContent} | <strong>Sessions:</strong> ${$('rep-sessions').textContent} | <strong>Class average:</strong> ${$('rep-average').textContent}</p>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Present</th>
                            <th>Permission</th>
                            <th>Absent</th>
                            <th>Unmarked</th>
                            <th>Rate</th>
                            <th>Result (%)</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    };

    const now = new Date();
    const currentYear = now.getFullYear();
    $('rep-year').innerHTML = [currentYear - 2, currentYear - 1, currentYear, currentYear + 1]
        .map(y => `<option value="${y}" ${y === currentYear ? 'selected' : ''}>${y}</option>`)
        .join('');
    $('rep-academic-year').innerHTML = [currentYear - 2, currentYear - 1, currentYear, currentYear + 1]
        .map(y => `<option value="${y}" ${y === currentYear ? 'selected' : ''}>${y}/${y + 1}</option>`)
        .join('');
    $('rep-month').value = String(now.getMonth() + 1);
    $('rep-term-plan').value = 't1';

    // Default custom range: last 30 days.
    const from = new Date(now);
    from.setDate(now.getDate() - 29);
    $('rep-from').value = toYmd(from);
    $('rep-to').value = toYmd(now);

    $('rep-period-type').addEventListener('change', renderPeriodControls);
    $('rep-year').addEventListener('change', renderPeriodControls);
    $('rep-term-plan').addEventListener('change', renderPeriodControls);
    $('rep-academic-year').addEventListener('change', renderPeriodControls);
    $('rep-academic-term').addEventListener('change', renderPeriodControls);
    $('rep-month').addEventListener('change', renderPeriodControls);
    $('rep-from').addEventListener('change', renderPeriodControls);
    $('rep-to').addEventListener('change', renderPeriodControls);
    $('rep-student-filter').addEventListener('change', renderRows);
    $('rep-class').addEventListener('change', () => {
        rep.activeSavedTermId = null;
        rep.rows = [];
        rep.visibleRows = [];
        rep.sessions = 0;
        renderRows();
        loadSavedTerms(Number($('rep-class').value || 0));
        loadTermDefaults(Number($('rep-class').value || 0));
    });
    $('rep-load').addEventListener('click', loadReport);
    $('rep-save-term-drawer').addEventListener('click', saveCurrentTerm);
    $('rep-save-term-default-drawer').addEventListener('click', () => saveTermDefault());
    $('rep-save-all-term-defaults').addEventListener('click', saveAllTermDefaults);
    $('rep-open-terms-drawer').addEventListener('click', openTermsDrawer);
    $('rep-open-terms-drawer-2').addEventListener('click', openTermsDrawer);
    $('rep-close-terms-drawer').addEventListener('click', closeTermsDrawer);
    $('rep-terms-drawer-backdrop').addEventListener('click', closeTermsDrawer);
    $('rep-csv').addEventListener('click', exportCsv);
    $('rep-print').addEventListener('click', printReport);

    renderPeriodControls();
    renderSavedTerms();
    renderTermDefaultsDrawer();
    loadTermDefaults(Number($('rep-class').value || 0));
    loadClasses().catch(() => {
        $('rep-class').innerHTML = '<option value="">Failed to load classes</option>';
    });

