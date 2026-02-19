@extends('layouts.admin')

@section('title', 'Finot | Reports')
@section('page-label', 'Reports')
@section('page-title', 'Class Attendance Reports')
@section('page-subtitle', 'Choose class and period, then see each student\'s attendance percentage and mark.')

@section('content')
    <div class="glass rounded-2xl p-4 md:p-6 shadow-glow space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            <div class="lg:col-span-2">
                <label class="text-xs text-slate-400">Class</label>
                <select id="rep-class" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option value="">Loading...</option>
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="text-xs text-slate-400">Period type</label>
                <select id="rep-period-type" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option value="semester">Semester</option>
                    <option value="academic">Academic Year</option>
                    <option value="year" selected>Year</option>
                    <option value="month">Month</option>
                    <option value="custom">Custom dates</option>
                </select>
            </div>

            <div id="rep-year-wrap" class="lg:col-span-2">
                <label class="text-xs text-slate-400">Year</label>
                <select id="rep-year" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60"></select>
            </div>

            <div id="rep-semester-wrap" class="lg:col-span-2">
                <label class="text-xs text-slate-400">Term</label>
                <select id="rep-term-plan" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option value="t1">1 Term</option>
                    <option value="t2">2 Term</option>
                    <option value="t3">3 Term</option>
                    <option value="t4">4 Term</option>
                    <option value="summer">Summer Class</option>
                </select>
            </div>

            <div id="rep-academic-year-wrap" class="lg:col-span-2 hidden">
                <label class="text-xs text-slate-400">Academic year</label>
                <select id="rep-academic-year" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60"></select>
            </div>

            <div id="rep-academic-term-wrap" class="lg:col-span-2 hidden">
                <label class="text-xs text-slate-400">Term</label>
                <select id="rep-academic-term" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option value="full">Full academic year</option>
                    <option value="term1">Term 1 (Sep-Jan)</option>
                    <option value="term2">Term 2 (Feb-Jun)</option>
                </select>
            </div>

            <div id="rep-month-wrap" class="lg:col-span-2 hidden">
                <label class="text-xs text-slate-400">Month</label>
                <select id="rep-month" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60"></select>
            </div>

            <div id="rep-month-et-year-wrap" class="lg:col-span-2 hidden">
                <label class="text-xs text-slate-400">ET year</label>
                <select id="rep-month-et-year" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60"></select>
            </div>

            <div class="lg:col-span-2">
                <label class="text-xs text-slate-400">Student filter</label>
                <select id="rep-student-filter" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-neon/60">
                    <option value="all">All students</option>
                    <option value="risk">At risk (&lt; 75%)</option>
                    <option value="ok">On track (&gt;= 75%)</option>
                </select>
            </div>

            <div class="lg:col-span-2">
                <div class="grid grid-cols-4 gap-1.5">
                    <button id="rep-load" class="h-8 px-2 rounded-lg neon-pill text-[10px] font-normal whitespace-nowrap">Load</button>
                    <button id="rep-open-terms-drawer" class="h-8 px-2 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring text-[10px] font-normal whitespace-nowrap">Terms</button>
                    <button id="rep-csv" class="h-8 px-2 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring text-[10px] font-normal whitespace-nowrap">CSV</button>
                    <button id="rep-print" class="h-8 px-2 rounded-lg glass text-slate-200 hover:text-white transition shadow-ring text-[10px] font-normal whitespace-nowrap">Print</button>
                </div>
            </div>
        </div>

        <div id="rep-period-row" class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">
            <div id="rep-custom-inline-wrap" class="hidden lg:col-span-5 xl:col-span-6 glass rounded-xl p-2 border border-white/5">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[11px] text-slate-400">From</label>
                        <input id="rep-from" type="date" class="mt-1 w-full rounded-lg bg-white/5 border border-white/10 px-2 py-1.5 text-[11px] text-white focus:outline-none focus:ring-2 focus:ring-neon/60" />
                        <div id="rep-et-from-wrap" class="hidden mt-1 grid grid-cols-3 gap-1">
                            <select id="rep-et-from-year" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                            <select id="rep-et-from-month" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                            <select id="rep-et-from-day" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                        </div>
                        <p id="rep-from-display" class="mt-1 text-[10px] text-slate-500"></p>
                    </div>
                    <div>
                        <label class="text-[11px] text-slate-400">To</label>
                        <input id="rep-to" type="date" class="mt-1 w-full rounded-lg bg-white/5 border border-white/10 px-2 py-1.5 text-[11px] text-white focus:outline-none focus:ring-2 focus:ring-neon/60" />
                        <div id="rep-et-to-wrap" class="hidden mt-1 grid grid-cols-3 gap-1">
                            <select id="rep-et-to-year" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                            <select id="rep-et-to-month" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                            <select id="rep-et-to-day" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                        </div>
                        <p id="rep-to-display" class="mt-1 text-[10px] text-slate-500"></p>
                    </div>
                </div>
            </div>
            <div id="rep-period-card" class="glass rounded-xl p-3 border border-white/5 lg:col-span-12">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-400">Selected period</p>
                    <div class="flex items-center gap-2">
                        <label for="rep-date-input-mode" class="text-[11px] text-slate-400">Input</label>
                        <select id="rep-date-input-mode" class="h-7 rounded-md bg-white/5 border border-white/10 px-2 text-[11px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/60">
                            <option value="gr">GR</option>
                            <option value="et">ET</option>
                        </select>
                        <label for="rep-et-month-lang" class="text-[11px] text-slate-400">ET labels</label>
                        <select id="rep-et-month-lang" class="h-7 rounded-md bg-white/5 border border-white/10 px-2 text-[11px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/60">
                            <option value="en">EN</option>
                            <option value="am">AM</option>
                        </select>
                        <label for="rep-calendar-mode" class="text-[11px] text-slate-400">Calendar</label>
                        <select id="rep-calendar-mode" class="h-7 rounded-md bg-white/5 border border-white/10 px-2 text-[11px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/60">
                            <option value="et_gr">ET + GR</option>
                            <option value="et">ET only</option>
                            <option value="gr">GR only</option>
                        </select>
                    </div>
                </div>
                <p id="rep-period-summary" class="text-sm text-slate-200 mt-1">--</p>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="glass rounded-xl p-3 border border-white/5">
                <p class="text-xs text-slate-400">Students</p>
                <p class="text-2xl text-white font-medium" id="rep-students">--</p>
            </div>
            <div class="glass rounded-xl p-3 border border-white/5">
                <p class="text-xs text-slate-400">Sessions in period</p>
                <p class="text-2xl text-white font-medium" id="rep-sessions">--</p>
            </div>
            <div class="glass rounded-xl p-3 border border-white/5">
                <p class="text-xs text-slate-400">Class average</p>
                <p class="text-2xl text-white font-medium" id="rep-average">--</p>
            </div>
            <div class="glass rounded-xl p-3 border border-white/5">
                <p class="text-xs text-slate-400">At risk (&lt; 75%)</p>
                <p class="text-2xl text-white font-medium" id="rep-risk">--</p>
            </div>
        </div>

        <div class="glass rounded-2xl border border-white/5 overflow-hidden">
            <div class="hidden lg:grid grid-cols-12 px-4 py-3 bg-white/5 text-xs text-slate-300">
                <span class="col-span-4">Student</span>
                <span class="col-span-1">Present</span>
                <span class="col-span-2">Permission</span>
                <span class="col-span-1">Absent</span>
                <span class="col-span-1">Unmarked</span>
                <span class="col-span-1">%</span>
                <span class="col-span-2 text-right">Attendance Mark (%)</span>
            </div>
            <div id="rep-rows" class="divide-y divide-white/5 min-h-[200px]">
                <p class="text-slate-400 text-sm px-4 py-3">Select class and period, then load the report.</p>
            </div>
        </div>

        <p id="rep-error" class="text-sm text-red-300"></p>
    </div>

    <div id="rep-terms-drawer-backdrop" class="fixed inset-0 bg-black/50 z-[80] hidden"></div>
    <aside id="rep-terms-drawer" class="fixed top-0 right-0 h-full w-full sm:w-[360px] max-w-full glass border-l border-white/10 z-[90] translate-x-full transition-transform duration-200 ease-out">
        <div class="h-full flex flex-col">
            <div class="px-3 py-2.5 border-b border-white/10 flex items-center justify-between gap-2">
                <div>
                    <p class="text-[11px] text-slate-400">Terms Manager</p>
                    <p class="text-xs text-white">Grouped by year</p>
                </div>
                <div class="flex items-center gap-1.5">
                    <button id="rep-save-term-drawer" class="h-7 px-2 rounded-md glass text-[10px] text-slate-200 hover:text-white whitespace-nowrap">Save Report Term</button>
                    <button id="rep-close-terms-drawer" class="h-7 w-7 rounded-md glass text-slate-300 hover:text-white text-xs">x</button>
                </div>
            </div>

            <div class="p-3 overflow-y-auto space-y-3">
                <p id="rep-term-default-hint-drawer" class="text-[11px] text-slate-500"></p>

                <section class="space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] text-slate-400">Term dates setup</p>
                        <button id="rep-save-all-term-defaults" class="h-7 px-2 rounded-md glass text-[10px] text-slate-200 hover:text-white">Save All</button>
                    </div>
                    <div id="rep-term-default-editor" class="space-y-1.5">
                        <p class="text-[11px] text-slate-500">Select a class to configure term dates.</p>
                    </div>
                </section>

                <section class="space-y-1.5">
                    <p class="text-[11px] text-slate-400">Semester defaults</p>
                    <div id="rep-term-defaults-groups" class="space-y-2">
                        <p class="text-[11px] text-slate-500">Select a class to load defaults.</p>
                    </div>
                </section>

                <section class="space-y-1.5">
                    <p class="text-[11px] text-slate-400">Saved report terms</p>
                    <div id="rep-saved-terms-groups" class="space-y-2">
                        <p class="text-[11px] text-slate-500">Select a class to load saved terms.</p>
                    </div>
                </section>
            </div>
        </div>
    </aside>
@endsection

@push('scripts')
<script>
    const rep = {
        rows: [],
        visibleRows: [],
        savedTerms: [],
        termDefinitions: {},
        termEditorOpenKey: null,
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
    const ETH_TZ = 'Africa/Addis_Ababa';
    const etFmt = new Intl.DateTimeFormat('en-ET-u-ca-ethiopic', {
        timeZone: ETH_TZ,
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
    const parseYmdUtc = (ymd) => {
        if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(String(ymd))) return null;
        const [y, m, d] = String(ymd).split('-').map(Number);
        // Use midday Addis Ababa time to avoid date shifts across timezones.
        return new Date(Date.UTC(y, m - 1, d, 9, 0, 0));
    };
    const toEtDate = (ymd) => {
        const dt = parseYmdUtc(ymd);
        if (!dt) return '--';
        return etFmt.format(dt);
    };
    const calendarMode = () => $('rep-calendar-mode')?.value || 'et_gr';
    const formatPeriodRange = (label, from, to) => {
        const gr = `${from} to ${to}`;
        const et = `${toEtDate(from)} to ${toEtDate(to)}`;
        const mode = calendarMode();
        let rangeText = gr;
        if (mode === 'et') rangeText = et;
        else if (mode === 'et_gr') rangeText = `${gr} | ET: ${et}`;
        return label ? `${label} | ${rangeText}` : rangeText;
    };
    const formatSingleDateForMode = (ymd) => {
        if (!ymd) return '--';
        const et = toEtDate(ymd);
        const gr = String(ymd);
        const mode = calendarMode();
        if (mode === 'et') return et;
        if (mode === 'gr') return gr;
        return `ET: ${et} | GR: ${gr}`;
    };
    const formatDrawerRangeHtml = (from, to) => {
        const gr = `GR: ${from} to ${to}`;
        const et = `ET: ${toEtDate(from)} to ${toEtDate(to)}`;
        const mode = calendarMode();
        if (mode === 'et') return `<p class="text-[11px] text-slate-400 mt-1">${et}</p>`;
        if (mode === 'gr') return `<p class="text-[11px] text-slate-400 mt-1">${gr}</p>`;
        return `<p class="text-[11px] text-slate-400 mt-1">${et}</p><p class="text-[10px] text-slate-500 mt-1">${gr}</p>`;
    };
    const formatCompactRange = (from, to) => {
        if (!from || !to) return 'No dates set';
        const mode = calendarMode();
        if (mode === 'et') return `${toEtDate(from)} to ${toEtDate(to)}`;
        if (mode === 'gr') return `${from} to ${to}`;
        return `ET: ${toEtDate(from)} to ${toEtDate(to)}`;
    };
    const dateInputMode = () => $('rep-date-input-mode')?.value || 'gr';
    const etNumericFmt = new Intl.DateTimeFormat('en-ET-u-ca-ethiopic-nu-latn', {
        timeZone: ETH_TZ,
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
    });
    const etMonthLabelsEn = [
        '1 Meskerem', '2 Tikimt', '3 Hidar', '4 Tahsas', '5 Tir', '6 Yekatit', '7 Megabit',
        '8 Miazia', '9 Ginbot', '10 Sene', '11 Hamle', '12 Nehase', '13 Pagume',
    ];
    const etMonthLabelsAm = [
        '1 መስከረም', '2 ጥቅምት', '3 ህዳር', '4 ታህሳስ', '5 ጥር', '6 የካቲት', '7 መጋቢት',
        '8 ሚያዝያ', '9 ግንቦት', '10 ሰኔ', '11 ሐምሌ', '12 ነሐሴ', '13 ጳጉሜ',
    ];
    const etMonthLang = () => $('rep-et-month-lang')?.value || 'en';
    const etMonthLabels = () => etMonthLang() === 'am' ? etMonthLabelsAm : etMonthLabelsEn;
    const partsToInt = (arr, type) => {
        const v = arr.find((p) => p.type === type)?.value || '';
        return Number(String(v).replace(/[^\d]/g, ''));
    };
    const gregToEtParts = (ymd) => {
        const dt = parseYmdUtc(ymd);
        if (!dt) return null;
        const parts = etNumericFmt.formatToParts(dt);
        const year = partsToInt(parts, 'year');
        const month = partsToInt(parts, 'month');
        const day = partsToInt(parts, 'day');
        if (!year || !month || !day) return null;
        return { year, month, day };
    };
    const toYmdUtcDate = (d) => {
        const y = d.getUTCFullYear();
        const m = String(d.getUTCMonth() + 1).padStart(2, '0');
        const day = String(d.getUTCDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };
    const addDaysToYmd = (ymd, deltaDays) => {
        const dt = parseYmdUtc(ymd);
        if (!dt) return '';
        return toYmdUtcDate(new Date(dt.getTime() + Number(deltaDays || 0) * 86400000));
    };
    const etToGregYmd = (etYear, etMonth, etDay) => {
        if (!etYear || !etMonth || !etDay) return '';
        const base = new Date(Date.UTC(Number(etYear) + 7, 8, 1, 9, 0, 0));
        for (let i = -45; i <= 430; i += 1) {
            const cand = new Date(base.getTime() + i * 86400000);
            const parts = etNumericFmt.formatToParts(cand);
            const y = partsToInt(parts, 'year');
            const m = partsToInt(parts, 'month');
            const d = partsToInt(parts, 'day');
            if (y === Number(etYear) && m === Number(etMonth) && d === Number(etDay)) {
                return toYmdUtcDate(cand);
            }
        }
        return '';
    };
    const etDaysInMonth = (year, month) => {
        if (month !== 13) return 30;
        return (Number(year) % 4 === 3) ? 6 : 5;
    };
    const fillEtDaySelect = (yearEl, monthEl, dayEl, selectedDay = null) => {
        const y = Number(yearEl?.value || 0);
        const m = Number(monthEl?.value || 0);
        if (!yearEl || !monthEl || !dayEl || !y || !m) return;
        const max = etDaysInMonth(y, m);
        const prev = Number(selectedDay || dayEl.value || 1);
        const safe = Math.min(Math.max(prev, 1), max);
        dayEl.innerHTML = Array.from({ length: max }, (_, i) => i + 1)
            .map((d) => `<option value="${d}" ${d === safe ? 'selected' : ''}>${d}</option>`)
            .join('');
    };
    const applyEtSelectsFromGregorian = (yearEl, monthEl, dayEl, ymd) => {
        const parts = gregToEtParts(ymd);
        if (!parts || !yearEl || !monthEl || !dayEl) return;
        if (!Array.from(yearEl.options).some((o) => Number(o.value) === parts.year)) {
            yearEl.innerHTML += `<option value="${parts.year}">${parts.year}</option>`;
        }
        yearEl.value = String(parts.year);
        monthEl.value = String(parts.month);
        fillEtDaySelect(yearEl, monthEl, dayEl, parts.day);
    };
    const syncGregorianInputFromEtSelects = (yearEl, monthEl, dayEl, targetInputId) => {
        const y = Number(yearEl?.value || 0);
        const m = Number(monthEl?.value || 0);
        const d = Number(dayEl?.value || 0);
        const ymd = etToGregYmd(y, m, d);
        const target = $(targetInputId);
        if (target && ymd) target.value = ymd;
    };
    const fillEtDayOptions = (prefix, selectedDay = null) => {
        const y = Number($(`rep-et-${prefix}-year`)?.value || 0);
        const m = Number($(`rep-et-${prefix}-month`)?.value || 0);
        const daySel = $(`rep-et-${prefix}-day`);
        fillEtDaySelect($(`rep-et-${prefix}-year`), $(`rep-et-${prefix}-month`), daySel, selectedDay);
    };
    const applyEtControlsFromGregorian = (prefix, ymd) => {
        const ySel = $(`rep-et-${prefix}-year`);
        const mSel = $(`rep-et-${prefix}-month`);
        const dSel = $(`rep-et-${prefix}-day`);
        applyEtSelectsFromGregorian(ySel, mSel, dSel, ymd);
    };
    const syncGregorianFromEt = (prefix) => {
        const y = Number($(`rep-et-${prefix}-year`)?.value || 0);
        const m = Number($(`rep-et-${prefix}-month`)?.value || 0);
        const d = Number($(`rep-et-${prefix}-day`)?.value || 0);
        const ymd = etToGregYmd(y, m, d);
        const target = prefix === 'from' ? $('rep-from') : $('rep-to');
        if (target && ymd) target.value = ymd;
    };
    const initEtCustomControls = () => {
        const currentEt = gregToEtParts(toYmd(new Date())) || { year: 2018, month: 1, day: 1 };
        const years = [currentEt.year - 2, currentEt.year - 1, currentEt.year, currentEt.year + 1, currentEt.year + 2];
        ['from', 'to'].forEach((p) => {
            const ySel = $(`rep-et-${p}-year`);
            const mSel = $(`rep-et-${p}-month`);
            if (!ySel || !mSel) return;
            ySel.innerHTML = years.map((y) => `<option value="${y}">${y}</option>`).join('');
            mSel.innerHTML = etMonthLabels().map((label, idx) => `<option value="${idx + 1}">${label}</option>`).join('');
            const currentGreg = p === 'from' ? $('rep-from')?.value : $('rep-to')?.value;
            applyEtControlsFromGregorian(p, currentGreg || toYmd(new Date()));
        });
    };
    const renderEtMonthYearOptions = () => {
        const sel = $('rep-month-et-year');
        if (!sel) return;
        const currentEt = gregToEtParts(toYmd(new Date())) || { year: 2018 };
        const years = [currentEt.year - 2, currentEt.year - 1, currentEt.year, currentEt.year + 1, currentEt.year + 2];
        const existing = Number(sel.value || currentEt.year);
        const selected = years.includes(existing) ? existing : currentEt.year;
        sel.innerHTML = years.map((y) => `<option value="${y}" ${y === selected ? 'selected' : ''}>${y}</option>`).join('');
    };
    const renderMonthOptions = () => {
        const monthSel = $('rep-month');
        if (!monthSel) return;
        const mode = dateInputMode();
        const currentGrMonth = (new Date()).getMonth() + 1;
        const selectedRaw = Number(monthSel.value || currentGrMonth);
        const labels = mode === 'et'
            ? etMonthLabels()
            : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const selected = Math.min(Math.max(selectedRaw, 1), labels.length);
        monthSel.innerHTML = labels
            .map((label, idx) => `<option value="${idx + 1}" ${idx + 1 === selected ? 'selected' : ''}>${label}</option>`)
            .join('');
    };
    const initEtTermRowControls = (termKey, fromYmd, toYmd) => {
        const currentEt = gregToEtParts(toYmd(new Date())) || { year: 2018 };
        const years = [currentEt.year - 2, currentEt.year - 1, currentEt.year, currentEt.year + 1, currentEt.year + 2];
        ['from', 'to'].forEach((side) => {
            const ySel = $(`rep-term-et-${termKey}-${side}-year`);
            const mSel = $(`rep-term-et-${termKey}-${side}-month`);
            const dSel = $(`rep-term-et-${termKey}-${side}-day`);
            if (!ySel || !mSel || !dSel) return;
            ySel.innerHTML = years.map((y) => `<option value="${y}">${y}</option>`).join('');
            mSel.innerHTML = etMonthLabels().map((label, idx) => `<option value="${idx + 1}">${label}</option>`).join('');
            applyEtSelectsFromGregorian(ySel, mSel, dSel, side === 'from' ? fromYmd : toYmd);

            const inputId = `rep-term-${termKey}-${side}`;
            ySel.addEventListener('change', () => {
                fillEtDaySelect(ySel, mSel, dSel);
                syncGregorianInputFromEtSelects(ySel, mSel, dSel, inputId);
            });
            mSel.addEventListener('change', () => {
                fillEtDaySelect(ySel, mSel, dSel);
                syncGregorianInputFromEtSelects(ySel, mSel, dSel, inputId);
            });
            dSel.addEventListener('change', () => {
                syncGregorianInputFromEtSelects(ySel, mSel, dSel, inputId);
            });
        });
    };
    const firstDefinedTermKey = () => {
        for (const t of TERM_ROWS) {
            const d = rep.termDefinitions[t.key];
            if (d?.from && d?.to) return t.key;
        }
        return null;
    };

    const resultPill = (rate) => {
        if (rate === null || rate === undefined) {
            return '<span class="px-3 py-1 rounded-full bg-white/5 text-xs text-slate-300">--</span>';
        }
        return `<span class="px-3 py-1 rounded-full bg-white/5 text-xs text-slate-200">${Number(rate).toFixed(1)}%</span>`;
    };
    const markPercent = (row) => {
        if (row?.attendance_mark_percent !== null && row?.attendance_mark_percent !== undefined) {
            return Number(row.attendance_mark_percent);
        }
        if (row?.present_rate !== null && row?.present_rate !== undefined) {
            return Number(row.present_rate);
        }
        return null;
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
            const preset = rep.termDefinitions[termPlan] || null;
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
            const month = Number($('rep-month').value || 1);
            const monthName = $('rep-month').selectedOptions[0]?.textContent || 'Month';
            if (dateInputMode() === 'et') {
                const etYear = Number($('rep-month-et-year')?.value || 0);
                if (!etYear) return null;
                const from = etToGregYmd(etYear, month, 1);
                const nextStart = month === 13
                    ? etToGregYmd(etYear + 1, 1, 1)
                    : etToGregYmd(etYear, month + 1, 1);
                if (!from || !nextStart) return null;
                const to = addDaysToYmd(nextStart, -1);
                if (!to) return null;
                return { from, to, label: `${monthName} ${etYear} (ET)` };
            }
            if (!year) return null;
            const from = `${year}-${String(month).padStart(2, '0')}-01`;
            const to = toYmd(endOfMonth(year, month));
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
        if (t === 'semester') {
            const selectedKey = String($('rep-term-plan').value || 't1');
            const selected = rep.termDefinitions[selectedKey];
            const firstKey = firstDefinedTermKey();
            if ((!selected?.from || !selected?.to) && firstKey && firstKey !== selectedKey) {
                $('rep-term-plan').value = firstKey;
            }
        }

        const etMonthMode = (t === 'month' && dateInputMode() === 'et');
        $('rep-year-wrap').classList.toggle('hidden', t === 'academic' || etMonthMode);
        $('rep-semester-wrap').classList.toggle('hidden', t !== 'semester');
        $('rep-academic-year-wrap').classList.toggle('hidden', t !== 'academic');
        $('rep-academic-term-wrap').classList.toggle('hidden', t !== 'academic');
        $('rep-month-wrap').classList.toggle('hidden', t !== 'month');
        $('rep-month-et-year-wrap').classList.toggle('hidden', !etMonthMode);
        $('rep-custom-inline-wrap').classList.toggle('hidden', t !== 'custom');

        // Keep custom range ordered for cleaner UX.
        if (t === 'custom') {
            const fromEl = $('rep-from');
            const toEl = $('rep-to');
            const mode = dateInputMode();
            const etFromWrap = $('rep-et-from-wrap');
            const etToWrap = $('rep-et-to-wrap');
            if (fromEl) fromEl.classList.toggle('hidden', mode === 'et');
            if (toEl) toEl.classList.toggle('hidden', mode === 'et');
            if (etFromWrap) etFromWrap.classList.toggle('hidden', mode !== 'et');
            if (etToWrap) etToWrap.classList.toggle('hidden', mode !== 'et');

            if (mode === 'et') {
                syncGregorianFromEt('from');
                syncGregorianFromEt('to');
            } else {
                applyEtControlsFromGregorian('from', fromEl?.value || '');
                applyEtControlsFromGregorian('to', toEl?.value || '');
            }

            const fromVal = fromEl?.value || '';
            const toVal = toEl?.value || '';
            if (fromVal && toVal && fromVal > toVal) {
                fromEl.value = toVal;
                toEl.value = fromVal;
                if (mode === 'et') {
                    applyEtControlsFromGregorian('from', fromEl.value);
                    applyEtControlsFromGregorian('to', toEl.value);
                }
            }
            const showFrom = $('rep-from-display');
            const showTo = $('rep-to-display');
            if (showFrom) showFrom.textContent = fromEl?.value ? formatSingleDateForMode(fromEl.value) : '';
            if (showTo) showTo.textContent = toEl?.value ? formatSingleDateForMode(toEl.value) : '';
        } else {
            const showFrom = $('rep-from-display');
            const showTo = $('rep-to-display');
            if (showFrom) showFrom.textContent = '';
            if (showTo) showTo.textContent = '';
        }

        // In semester mode, keep selected-period and date filters in one row.
        const periodCard = $('rep-period-card');
        if (t === 'custom') {
            periodCard.classList.remove('lg:col-span-12');
            periodCard.classList.add('lg:col-span-7');
            periodCard.classList.remove('xl:col-span-12');
            periodCard.classList.add('xl:col-span-6');
        } else {
            periodCard.classList.add('lg:col-span-12');
            periodCard.classList.remove('lg:col-span-7');
            periodCard.classList.add('xl:col-span-12');
            periodCard.classList.remove('xl:col-span-6');
        }

        if (t !== 'semester') setTermHint('');

        const range = computeRange();
        $('rep-period-summary').textContent = range ? formatPeriodRange(range.label, range.from, range.to) : 'Please select valid period values.';
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
            .map(r => markPercent(r))
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
            const rate = Number(markPercent(r) ?? -1);
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
            const ar = Number(markPercent(a) ?? -1);
            const br = Number(markPercent(b) ?? -1);
            if (ar !== br) return ar - br;
            return String(a.full_name || '').localeCompare(String(b.full_name || ''));
        });

        wrap.innerHTML = sorted.map(r => {
            const rateNum = markPercent(r);
            const rate = (rateNum === null || rateNum === undefined || !Number.isFinite(rateNum)) ? '--' : `${Number(rateNum).toFixed(1)}%`;
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
                    <div class="lg:col-span-2 flex lg:justify-end">${resultPill(rateNum)}</div>
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
        const year = Number($('rep-year').value || 0);
        if (!year) {
            wrap.innerHTML = '<p class="text-[11px] text-slate-500">Select a year to configure term dates.</p>';
            return;
        }

        wrap.innerHTML = TERM_ROWS.map((t) => {
            const d = rep.termDefinitions[t.key] || {};
            const from = d.from || '';
            const to = d.to || '';
            const isOpen = rep.termEditorOpenKey === t.key;
            const statusText = formatCompactRange(from, to);
            const useEtInput = dateInputMode() === 'et';
            return `
                <div class="rounded-md border border-white/10 bg-white/5 p-1.5">
                    <button type="button" data-toggle-term-editor="${t.key}" class="w-full flex items-center justify-between gap-2 text-left">
                        <span class="text-[11px] text-white">${t.label}</span>
                        <span class="text-[10px] text-slate-400">${statusText}</span>
                    </button>
                    <div class="${isOpen ? 'mt-1.5' : 'hidden'}" id="rep-term-editor-${t.key}">
                        <div class="grid grid-cols-2 gap-1.5">
                            <div>
                                <input id="rep-term-${t.key}-from" type="date" value="${from}" class="${useEtInput ? 'hidden' : ''} w-full rounded-md bg-white/5 border border-white/10 px-2 py-1.5 text-[11px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40" />
                                <div id="rep-term-et-${t.key}-from-wrap" class="${useEtInput ? 'grid' : 'hidden'} grid-cols-3 gap-1">
                                    <select id="rep-term-et-${t.key}-from-year" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                                    <select id="rep-term-et-${t.key}-from-month" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                                    <select id="rep-term-et-${t.key}-from-day" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                                </div>
                            </div>
                            <div>
                                <input id="rep-term-${t.key}-to" type="date" value="${to}" class="${useEtInput ? 'hidden' : ''} w-full rounded-md bg-white/5 border border-white/10 px-2 py-1.5 text-[11px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40" />
                                <div id="rep-term-et-${t.key}-to-wrap" class="${useEtInput ? 'grid' : 'hidden'} grid-cols-3 gap-1">
                                    <select id="rep-term-et-${t.key}-to-year" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                                    <select id="rep-term-et-${t.key}-to-month" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                                    <select id="rep-term-et-${t.key}-to-day" class="rounded-md bg-white/5 border border-white/10 px-1 py-1 text-[10px] text-slate-200 focus:outline-none focus:ring-2 focus:ring-neon/40"></select>
                                </div>
                            </div>
                        </div>
                        <div class="mt-1.5 flex justify-end">
                            <button type="button" data-save-term-default="${t.key}" class="h-6 px-1.5 rounded-md glass text-[10px] text-slate-200 hover:text-white">Save</button>
                        </div>
                        ${from && to ? formatDrawerRangeHtml(from, to) : ''}
                    </div>
                </div>
            `;
        }).join('');

        wrap.querySelectorAll('[data-toggle-term-editor]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const termKey = String(btn.getAttribute('data-toggle-term-editor') || '');
                if (!termKey) return;
                rep.termEditorOpenKey = rep.termEditorOpenKey === termKey ? null : termKey;
                renderTermDefaultEditor();
            });
        });

        wrap.querySelectorAll('[data-save-term-default]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const termKey = String(btn.getAttribute('data-save-term-default') || '');
                if (!termKey) return;
                await saveTermDefault(termKey);
            });
        });

        if (dateInputMode() === 'et') {
            TERM_ROWS.forEach((t) => {
                const d = rep.termDefinitions[t.key] || {};
                initEtTermRowControls(t.key, d.from || '', d.to || '');
            });
        }
    };

    const loadTermDefaults = async () => {
        const year = Number($('rep-year').value || 0);
        rep.termDefinitions = {};
        if (!year) {
            renderTermDefaultEditor();
            setTermHint('');
            renderTermDefaultsDrawer();
            return;
        }
        try {
            const res = await fetch(`/api/v1/reports/term-definitions?year=${encodeURIComponent(String(year))}`);
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed to load term definitions');
            const rows = Array.isArray(json?.data) ? json.data : [];
            rows.forEach((r) => {
                rep.termDefinitions[String(r.term_key)] = r;
            });
        } catch {
            rep.termDefinitions = {};
        }
        renderTermDefaultEditor();
        renderTermDefaultsDrawer();
        renderPeriodControls();
    };

    const saveTermDefault = async (termKeyArg = null) => {
        $('rep-error').textContent = '';
        const year = Number($('rep-year').value || 0);
        if (!year) {
            $('rep-error').textContent = 'Select a year first.';
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
            const res = await fetch(`/api/v1/reports/term-definitions/${encodeURIComponent(String(year))}/${encodeURIComponent(termKey)}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ from, to, term_label: label }),
            });
            const json = await res.json().catch(() => null);
            if (!res.ok) throw new Error(json?.message || 'Failed to save term dates');
            rep.termEditorOpenKey = null;
            await loadTermDefaults();
            setTermHint(`Saved default dates for ${label}.`);
        } catch (e) {
            $('rep-error').textContent = e?.message || 'Failed to save term dates';
        }
    };

    const saveAllTermDefaults = async () => {
        const year = Number($('rep-year').value || 0);
        if (!year) {
            $('rep-error').textContent = 'Select a year first.';
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
        const year = Number($('rep-year').value || 0);
        if (!year) {
            wrap.innerHTML = '<p class="text-[11px] text-slate-500">Select a year to load term definitions.</p>';
            return;
        }
        const rows = Object.values(rep.termDefinitions || {});
        if (!rows.length) {
            wrap.innerHTML = '<p class="text-[11px] text-slate-500">No term definitions saved for this year.</p>';
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
                            <p class="text-[11px] text-white">${d.term_label || d.term_key}</p>
                            ${formatDrawerRangeHtml(d.from, d.to)}
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
                                ${formatDrawerRangeHtml(t.from, t.to)}
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
        $('rep-period-summary').textContent = formatPeriodRange(label, range.from, range.to);
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
            const rows = rep.visibleRows.length ? rep.visibleRows : rep.rows;
            const classLabel = $('rep-class').selectedOptions[0]?.textContent || `Class ${classId}`;
            const csvEscape = (v) => {
                const s = String(v ?? '');
                if (s.includes('"') || s.includes(',') || s.includes('\n')) return `"${s.replace(/"/g, '""')}"`;
                return s;
            };
            const mode = calendarMode();
            const header = [
                'class_id',
                'class_name',
                'period_label',
                ...(mode !== 'et' ? ['from_gregorian', 'to_gregorian'] : []),
                ...(mode !== 'gr' ? ['from_ethiopian', 'to_ethiopian'] : []),
                'student_id',
                'full_name',
                'present',
                'permission',
                'absent',
                'unmarked',
                'total_days',
                'attendance_percent',
                'attendance_mark_percent',
            ];
            const lines = [header.join(',')];
            rows.forEach((r) => {
                const mark = markPercent(r);
                lines.push([
                    classId,
                    classLabel,
                    range.label || '',
                    ...(mode !== 'et' ? [range.from, range.to] : []),
                    ...(mode !== 'gr' ? [toEtDate(range.from), toEtDate(range.to)] : []),
                    r.student_id,
                    r.full_name,
                    r.present,
                    r.permission,
                    r.absent,
                    r.unmarked,
                    r.total_days,
                    mark === null || !Number.isFinite(mark) ? '' : Number(mark).toFixed(1),
                    mark === null || !Number.isFinite(mark) ? '' : Number(mark).toFixed(1),
                ].map(csvEscape).join(','));
            });

            const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
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
            const rateNum = markPercent(r);
            const rate = (rateNum === null || rateNum === undefined || !Number.isFinite(rateNum)) ? '--' : `${Number(rateNum).toFixed(1)}%`;
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
                            <th>Attendance Mark (%)</th>
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
    $('rep-period-type').value = 'year';
    $('rep-date-input-mode').value = 'et';
    $('rep-et-month-lang').value = 'en';
    $('rep-calendar-mode').value = 'et';

    // Default custom range: last 30 days.
    const from = new Date(now);
    from.setDate(now.getDate() - 29);
    $('rep-from').value = toYmd(from);
    $('rep-to').value = toYmd(now);
    initEtCustomControls();
    renderMonthOptions();
    $('rep-month').value = String(now.getMonth() + 1);
    renderEtMonthYearOptions();

    const on = (id, event, handler) => {
        const el = $(id);
        if (el) el.addEventListener(event, handler);
    };

    on('rep-period-type', 'change', renderPeriodControls);
    on('rep-year', 'change', () => {
        renderPeriodControls();
        loadTermDefaults();
    });
    on('rep-term-plan', 'change', renderPeriodControls);
    on('rep-academic-year', 'change', renderPeriodControls);
    on('rep-academic-term', 'change', renderPeriodControls);
    on('rep-month', 'change', renderPeriodControls);
    on('rep-month-et-year', 'change', renderPeriodControls);
    on('rep-date-input-mode', 'change', () => {
        renderMonthOptions();
        renderEtMonthYearOptions();
        renderPeriodControls();
        renderTermDefaultEditor();
    });
    on('rep-et-month-lang', 'change', () => {
        initEtCustomControls();
        renderMonthOptions();
        renderEtMonthYearOptions();
        renderTermDefaultEditor();
        renderPeriodControls();
    });
    on('rep-calendar-mode', 'change', () => {
        renderPeriodControls();
        renderTermDefaultEditor();
        renderTermDefaultsDrawer();
        renderSavedTerms();
    });
    on('rep-from', 'change', renderPeriodControls);
    on('rep-to', 'change', renderPeriodControls);
    ['from', 'to'].forEach((p) => {
        on(`rep-et-${p}-year`, 'change', () => {
            fillEtDayOptions(p);
            syncGregorianFromEt(p);
            renderPeriodControls();
        });
        on(`rep-et-${p}-month`, 'change', () => {
            fillEtDayOptions(p);
            syncGregorianFromEt(p);
            renderPeriodControls();
        });
        on(`rep-et-${p}-day`, 'change', () => {
            syncGregorianFromEt(p);
            renderPeriodControls();
        });
    });
    on('rep-student-filter', 'change', renderRows);
    on('rep-class', 'change', () => {
        rep.activeSavedTermId = null;
        rep.rows = [];
        rep.visibleRows = [];
        rep.sessions = 0;
        renderRows();
        loadSavedTerms(Number($('rep-class').value || 0));
        loadTermDefaults();
    });
    on('rep-load', 'click', loadReport);
    on('rep-save-term-drawer', 'click', saveCurrentTerm);
    on('rep-save-all-term-defaults', 'click', saveAllTermDefaults);
    on('rep-open-terms-drawer', 'click', openTermsDrawer);
    on('rep-close-terms-drawer', 'click', closeTermsDrawer);
    on('rep-terms-drawer-backdrop', 'click', closeTermsDrawer);
    on('rep-csv', 'click', exportCsv);
    on('rep-print', 'click', printReport);

    renderPeriodControls();
    renderSavedTerms();
    renderTermDefaultsDrawer();
    loadTermDefaults();
    loadClasses().catch(() => {
        $('rep-class').innerHTML = '<option value="">Failed to load classes</option>';
    });
</script>
@endpush
