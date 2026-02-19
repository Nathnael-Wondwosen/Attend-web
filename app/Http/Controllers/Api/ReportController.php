<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttTeacherAccount;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    protected const AUDIT_TERM_DEF_UPSERT = 'term_definition.upsert';
    protected const AUDIT_TERM_DEF_STATUS = 'term_definition.status';

    public function termDefinitions(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $query = DB::table('att_term_definitions')
            ->orderByDesc('academic_year')
            ->orderBy('term_order')
            ->orderBy('id');

        if (!empty($data['year'])) {
            $query->where('academic_year', (int) $data['year']);
        }

        $rows = $query->get([
            'id',
            'academic_year',
            'term_key',
            'term_label',
            'from_date',
            'to_date',
            'is_active',
            'status',
            'approved_by_admin_id',
            'approved_at',
            'locked_by_admin_id',
            'locked_at',
            'updated_at',
        ])->map(function ($r) {
            return [
                'id' => (int) $r->id,
                'academic_year' => (int) $r->academic_year,
                'term_key' => (string) $r->term_key,
                'term_label' => (string) ($r->term_label ?? strtoupper((string) $r->term_key)),
                'from' => (string) $r->from_date,
                'to' => (string) $r->to_date,
                'is_active' => (bool) $r->is_active,
                'status' => (string) ($r->status ?? 'draft'),
                'approved_by_admin_id' => $r->approved_by_admin_id ? (int) $r->approved_by_admin_id : null,
                'approved_at' => $r->approved_at ? Carbon::parse($r->approved_at)->toISOString() : null,
                'locked_by_admin_id' => $r->locked_by_admin_id ? (int) $r->locked_by_admin_id : null,
                'locked_at' => $r->locked_at ? Carbon::parse($r->locked_at)->toISOString() : null,
                'updated_at' => $r->updated_at ? Carbon::parse($r->updated_at)->toISOString() : null,
            ];
        })->values();

        return response()->json(['data' => $rows]);
    }

    public function upsertTermDefinition(Request $request, int $year, string $termKey)
    {
        $this->authorizeAdmin($request);
        $year = (int) $year;
        if ($year < 2000 || $year > 2100) {
            return response()->json(['message' => 'Invalid year'], 422);
        }

        $termKey = strtolower(trim($termKey));
        $allowed = ['t1', 't2', 't3', 't4', 'summer'];
        if (!in_array($termKey, $allowed, true)) {
            return response()->json(['message' => 'Invalid term key'], 422);
        }

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'term_label' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $from = Carbon::parse($data['from'])->toDateString();
        $to = Carbon::parse($data['to'])->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $termOrderMap = ['t1' => 1, 't2' => 2, 't3' => 3, 't4' => 4, 'summer' => 5];
        $label = trim((string) ($data['term_label'] ?? ($termKey === 'summer' ? 'Summer Class' : strtoupper($termKey))));
        $active = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;

        $existing = DB::table('att_term_definitions')
            ->where('academic_year', $year)
            ->where('term_key', $termKey)
            ->first(['id', 'status', 'from_date', 'to_date', 'term_label', 'is_active']);

        if ($existing && (string) ($existing->status ?? 'draft') === 'locked') {
            return response()->json(['message' => 'This term is locked and cannot be edited'], 422);
        }

        // Validation: prevent overlapping active ranges with other active terms in the same year.
        if ($active) {
            $overlap = DB::table('att_term_definitions')
                ->where('academic_year', $year)
                ->where('term_key', '!=', $termKey)
                ->where('is_active', 1)
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('from_date', [$from, $to])
                        ->orWhereBetween('to_date', [$from, $to])
                        ->orWhere(function ($x) use ($from, $to) {
                            $x->where('from_date', '<=', $from)->where('to_date', '>=', $to);
                        });
                })
                ->first(['term_key', 'from_date', 'to_date']);

            if ($overlap) {
                return response()->json([
                    'message' => "Date range overlaps with term {$overlap->term_key} ({$overlap->from_date} to {$overlap->to_date})",
                ], 422);
            }
        }

        $actorId = ($request->user() instanceof Admin) ? (int) $request->user()->id : null;
        $now = now();
        DB::table('att_term_definitions')->upsert(
            [[
                'academic_year' => $year,
                'term_key' => $termKey,
                'term_label' => $label,
                'term_order' => $termOrderMap[$termKey],
                'from_date' => $from,
                'to_date' => $to,
                'is_active' => $active ? 1 : 0,
                'status' => $existing ? (string) ($existing->status ?? 'draft') : 'draft',
                'updated_at' => $now,
                'created_at' => $now,
            ]],
            ['academic_year', 'term_key'],
            ['term_label', 'term_order', 'from_date', 'to_date', 'is_active', 'updated_at']
        );

        $this->audit($request, self::AUDIT_TERM_DEF_UPSERT, [
            'academic_year' => $year,
            'term_key' => $termKey,
            'from' => $from,
            'to' => $to,
            'is_active' => $active,
            'old' => $existing ? [
                'from' => (string) $existing->from_date,
                'to' => (string) $existing->to_date,
                'term_label' => (string) $existing->term_label,
                'is_active' => (bool) $existing->is_active,
                'status' => (string) ($existing->status ?? 'draft'),
            ] : null,
            'actor_admin_id' => $actorId,
        ]);

        return response()->json([
            'message' => 'Term definition saved',
            'data' => [
                'academic_year' => $year,
                'term_key' => $termKey,
                'term_label' => $label,
                'from' => $from,
                'to' => $to,
                'is_active' => $active,
            ],
        ]);
    }

    public function setTermDefinitionStatus(Request $request, int $year, string $termKey)
    {
        $this->authorizeAdmin($request);
        $year = (int) $year;
        $termKey = strtolower(trim($termKey));
        if ($year < 2000 || $year > 2100) {
            return response()->json(['message' => 'Invalid year'], 422);
        }
        if (!in_array($termKey, ['t1', 't2', 't3', 't4', 'summer'], true)) {
            return response()->json(['message' => 'Invalid term key'], 422);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,approved,locked'],
        ]);
        $newStatus = (string) $data['status'];

        $row = DB::table('att_term_definitions')
            ->where('academic_year', $year)
            ->where('term_key', $termKey)
            ->first(['id', 'status']);
        if (!$row) {
            return response()->json(['message' => 'Term definition not found'], 404);
        }

        $current = (string) ($row->status ?? 'draft');
        if ($current === 'locked' && $newStatus !== 'locked') {
            return response()->json(['message' => 'Locked term cannot transition back'], 422);
        }

        $user = $request->user();
        $adminId = $user instanceof Admin ? (int) $user->id : null;
        $updates = ['status' => $newStatus, 'updated_at' => now()];
        if ($newStatus === 'approved') {
            $updates['approved_by_admin_id'] = $adminId;
            $updates['approved_at'] = now();
        }
        if ($newStatus === 'locked') {
            $updates['locked_by_admin_id'] = $adminId;
            $updates['locked_at'] = now();
        }

        DB::table('att_term_definitions')->where('id', (int) $row->id)->update($updates);

        $this->audit($request, self::AUDIT_TERM_DEF_STATUS, [
            'academic_year' => $year,
            'term_key' => $termKey,
            'from_status' => $current,
            'to_status' => $newStatus,
        ]);

        return response()->json([
            'message' => 'Status updated',
            'data' => [
                'academic_year' => $year,
                'term_key' => $termKey,
                'status' => $newStatus,
            ],
        ]);
    }

    public function savedTerms(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);

        $rows = DB::table('att_saved_report_terms')
            ->where('class_id', $classId)
            ->where(function ($q) {
                $q->whereNull('period_type')
                    ->orWhere('period_type', '!=', 'semester_default');
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'class_id',
                'label',
                'period_type',
                'term_key',
                'from_date',
                'to_date',
                'meta',
                'created_by_admin_id',
                'created_at',
                'updated_at',
            ])
            ->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'class_id' => (int) $r->class_id,
                    'label' => (string) $r->label,
                    'period_type' => $r->period_type ? (string) $r->period_type : null,
                    'term_key' => $r->term_key ? (string) $r->term_key : null,
                    'from' => (string) $r->from_date,
                    'to' => (string) $r->to_date,
                    'meta' => $r->meta ? json_decode((string) $r->meta, true) : null,
                    'created_by_admin_id' => $r->created_by_admin_id ? (int) $r->created_by_admin_id : null,
                    'created_at' => $r->created_at ? Carbon::parse($r->created_at)->toISOString() : null,
                    'updated_at' => $r->updated_at ? Carbon::parse($r->updated_at)->toISOString() : null,
                ];
            })
            ->values();

        return response()->json([
            'class_id' => $classId,
            'data' => $rows,
        ]);
    }

    public function semesterDefaults(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);

        $rows = DB::table('att_saved_report_terms')
            ->where('class_id', $classId)
            ->where('period_type', 'semester_default')
            ->whereIn('term_key', ['t1', 't2', 't3', 't4', 'summer'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'label',
                'term_key',
                'from_date',
                'to_date',
                'updated_at',
            ]);

        $defaults = [];
        foreach ($rows as $r) {
            $key = (string) $r->term_key;
            if (isset($defaults[$key])) {
                continue;
            }
            $defaults[$key] = [
                'id' => (int) $r->id,
                'label' => (string) ($r->label ?? $key),
                'term_key' => $key,
                'from' => (string) $r->from_date,
                'to' => (string) $r->to_date,
                'updated_at' => $r->updated_at ? Carbon::parse($r->updated_at)->toISOString() : null,
            ];
        }

        return response()->json([
            'class_id' => $classId,
            'defaults' => $defaults,
        ]);
    }

    public function upsertSemesterDefault(Request $request, int $classId, string $termKey)
    {
        $this->authorizeUserForClass($request, $classId);
        $this->authorizeAdmin($request);

        $termKey = strtolower(trim($termKey));
        if (!in_array($termKey, ['t1', 't2', 't3', 't4', 'summer'], true)) {
            return response()->json(['message' => 'Invalid term key'], 422);
        }

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $from = Carbon::parse($data['from'])->toDateString();
        $to = Carbon::parse($data['to'])->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $label = trim((string) ($data['label'] ?? strtoupper($termKey)));
        if ($label === '') {
            $label = strtoupper($termKey);
        }

        $now = now();
        $user = $request->user();
        $adminId = $user instanceof Admin ? (int) $user->id : null;

        $existing = DB::table('att_saved_report_terms')
            ->where('class_id', $classId)
            ->where('period_type', 'semester_default')
            ->where('term_key', $termKey)
            ->orderByDesc('id')
            ->first(['id']);

        if ($existing) {
            DB::table('att_saved_report_terms')
                ->where('id', (int) $existing->id)
                ->update([
                    'label' => $label,
                    'from_date' => $from,
                    'to_date' => $to,
                    'updated_at' => $now,
                ]);
            $id = (int) $existing->id;
        } else {
            $id = (int) DB::table('att_saved_report_terms')->insertGetId([
                'class_id' => $classId,
                'label' => $label,
                'period_type' => 'semester_default',
                'term_key' => $termKey,
                'from_date' => $from,
                'to_date' => $to,
                'meta' => null,
                'created_by_admin_id' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return response()->json([
            'message' => 'Term default saved',
            'default' => [
                'id' => $id,
                'class_id' => $classId,
                'term_key' => $termKey,
                'label' => $label,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function storeSavedTerm(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'period_type' => ['nullable', 'string', 'max:32'],
            'term_key' => ['nullable', 'string', 'max:32'],
            'meta' => ['nullable', 'array'],
        ]);

        $from = Carbon::parse($data['from'])->toDateString();
        $to = Carbon::parse($data['to'])->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $user = $request->user();
        $adminId = $user instanceof Admin ? (int) $user->id : null;
        $now = now();

        $id = DB::table('att_saved_report_terms')->insertGetId([
            'class_id' => $classId,
            'label' => (string) $data['label'],
            'period_type' => $data['period_type'] ?? null,
            'term_key' => $data['term_key'] ?? null,
            'from_date' => $from,
            'to_date' => $to,
            'meta' => isset($data['meta']) ? json_encode($data['meta']) : null,
            'created_by_admin_id' => $adminId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'message' => 'Saved term created',
            'saved_term' => [
                'id' => (int) $id,
                'class_id' => (int) $classId,
                'label' => (string) $data['label'],
                'period_type' => $data['period_type'] ?? null,
                'term_key' => $data['term_key'] ?? null,
                'from' => $from,
                'to' => $to,
                'meta' => $data['meta'] ?? null,
            ],
        ], 201);
    }

    public function deleteSavedTerm(Request $request, int $savedTermId)
    {
        $this->authorizeAdmin($request);

        $exists = DB::table('att_saved_report_terms')->where('id', $savedTermId)->exists();
        if (!$exists) {
            abort(404, 'Saved term not found');
        }

        DB::table('att_saved_report_terms')->where('id', $savedTermId)->delete();

        return response()->json(['message' => 'Saved term deleted']);
    }

    public function updateSavedTerm(Request $request, int $savedTermId)
    {
        $this->authorizeAdmin($request);

        $row = DB::table('att_saved_report_terms')->where('id', $savedTermId)->first();
        if (!$row) {
            abort(404, 'Saved term not found');
        }

        $this->authorizeUserForClass($request, (int) $row->class_id);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'period_type' => ['nullable', 'string', 'max:32'],
            'term_key' => ['nullable', 'string', 'max:32'],
            'meta' => ['nullable', 'array'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->toDateString() : (string) $row->from_date;
        $to = isset($data['to']) ? Carbon::parse($data['to'])->toDateString() : (string) $row->to_date;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        DB::table('att_saved_report_terms')
            ->where('id', $savedTermId)
            ->update([
                'label' => array_key_exists('label', $data) ? (string) $data['label'] : (string) $row->label,
                'period_type' => array_key_exists('period_type', $data) ? $data['period_type'] : $row->period_type,
                'term_key' => array_key_exists('term_key', $data) ? $data['term_key'] : $row->term_key,
                'from_date' => $from,
                'to_date' => $to,
                'meta' => array_key_exists('meta', $data) ? json_encode($data['meta']) : $row->meta,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Saved term updated',
            'saved_term' => [
                'id' => (int) $savedTermId,
                'class_id' => (int) $row->class_id,
                'label' => array_key_exists('label', $data) ? (string) $data['label'] : (string) $row->label,
                'period_type' => array_key_exists('period_type', $data) ? $data['period_type'] : $row->period_type,
                'term_key' => array_key_exists('term_key', $data) ? $data['term_key'] : $row->term_key,
                'from' => $from,
                'to' => $to,
                'meta' => array_key_exists('meta', $data) ? $data['meta'] : ($row->meta ? json_decode((string) $row->meta, true) : null),
            ],
        ]);
    }

    public function classDay(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);

        $date = $request->query('date', now()->toDateString());
        $format = $request->query('format', 'json');

        $day = Carbon::parse($date)->toDateString();

        $session = DB::table('att_sessions')
            ->where('class_id', $classId)
            ->where('attendance_date', $day)
            ->orderByDesc('id')
            ->first(['id', 'workflow_status', 'submitted_at']);

        $sessionId = $session?->id;
        $workflow = (string) ($session?->workflow_status ?? 'none');

        // Base on roster to avoid missing students in draft sessions.
        // For submitted sessions, missing marks are treated as absent.
        // For drafts, missing marks are treated as unmarked.
        $rows = DB::table('class_enrollments as ce')
            ->join('students as s', 's.id', '=', 'ce.student_id')
            ->leftJoin('att_attendance as aa', function ($join) use ($sessionId) {
                $join->on('aa.student_id', '=', 'ce.student_id')
                    ->where('aa.session_id', '=', $sessionId ?: 0);
            })
            ->where('ce.class_id', $classId)
            ->where('ce.status', 'active')
            ->orderBy('s.full_name')
            ->get([
                's.id as student_id',
                's.full_name',
                'aa.status',
                'aa.method',
                'aa.marked_at',
                'aa.note',
            ])
            ->map(function ($r) use ($workflow) {
                $status = $r->status;
                if (!$status) {
                    $status = ($workflow === 'submitted') ? 'absent' : 'unmarked';
                }
                return [
                    'student_id' => (int) $r->student_id,
                    'full_name' => $r->full_name,
                    'status' => $status,
                    'method' => $r->method,
                    'marked_at' => $r->marked_at,
                    'note' => $r->note,
                ];
            })
            ->values();

        if ($format === 'csv') {
            $className = DB::table('classes')->where('id', $classId)->value('name');
            $fileBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($className ?: "class_{$classId}"));
            $filename = "report_daily_{$fileBase}_{$day}.csv";

            $response = new StreamedResponse(function () use ($rows, $classId, $day, $sessionId, $workflow, $className) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fputcsv($out, ['attendance_date', 'class_id', 'class_name', 'session_id', 'workflow_status', 'student_id', 'full_name', 'status', 'method', 'marked_at', 'note']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $day,
                        $classId,
                        $className,
                        $sessionId,
                        $workflow,
                        $row['student_id'],
                        $row['full_name'],
                        $row['status'],
                        $row['method'],
                        $row['marked_at'],
                        $row['note'],
                    ]);
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            return $response;
        }

        $counts = [
            'present' => (int) $rows->where('status', 'present')->count(),
            'permission' => (int) $rows->where('status', 'permission')->count(),
            'absent' => (int) $rows->where('status', 'absent')->count(),
            'unmarked' => (int) $rows->where('status', 'unmarked')->count(),
            'total' => (int) $rows->count(),
        ];

        return response()->json([
            'class_id' => $classId,
            'date' => $day,
            'session' => $session ? [
                'id' => (int) $session->id,
                'workflow_status' => (string) ($session->workflow_status ?? 'draft'),
                'submitted_at' => $session->submitted_at ? Carbon::parse($session->submitted_at)->toISOString() : null,
            ] : null,
            'counts' => $counts,
            'rows' => $rows,
        ]);
    }

    public function classRange(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'format' => ['nullable', 'string'],
        ]);

        $from = Carbon::parse($data['from'])->toDateString();
        $to = Carbon::parse($data['to'])->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $format = (string) ($data['format'] ?? $request->query('format', 'json'));

        $sessions = DB::table('att_sessions')
            ->where('class_id', $classId)
            ->whereBetween('attendance_date', [$from, $to])
            ->select(['id', 'attendance_date', 'workflow_status'])
            ->orderBy('attendance_date')
            ->get();

        $sessionIds = $sessions->pluck('id')->map(fn ($v) => (int) $v)->all();
        $hasSubmitted = $sessions->contains(fn ($s) => (string) ($s->workflow_status ?? '') === 'submitted');

        // Preload attendance marks in range.
        $marks = [];
        if (!empty($sessionIds)) {
            $marks = DB::table('att_attendance as aa')
                ->join('att_sessions as ses', 'ses.id', '=', 'aa.session_id')
                ->where('ses.class_id', $classId)
                ->whereBetween('ses.attendance_date', [$from, $to])
                ->get([
                    'aa.student_id',
                    'aa.status',
                    'ses.attendance_date',
                    'ses.workflow_status',
                ])
                ->groupBy('student_id');
        }

        $students = DB::table('class_enrollments as ce')
            ->join('students as s', 's.id', '=', 'ce.student_id')
            ->where('ce.class_id', $classId)
            ->where('ce.status', 'active')
            ->orderBy('s.full_name')
            ->get(['s.id as student_id', 's.full_name']);

        $sessionCount = (int) $sessions->count();

        $perStudent = $students->map(function ($s) use ($marks, $sessions, $sessionCount) {
            $sid = (int) $s->student_id;
            $present = 0;
            $absent = 0;
            $permission = 0;
            $unmarked = 0;

            // Build a quick lookup of attendance by date for this student.
            $byDate = [];
            if (isset($marks[$sid])) {
                foreach ($marks[$sid] as $m) {
                    $byDate[(string) $m->attendance_date] = [
                        'status' => (string) $m->status,
                        'workflow_status' => (string) ($m->workflow_status ?? 'draft'),
                    ];
                }
            }

            foreach ($sessions as $ses) {
                $d = (string) $ses->attendance_date;
                $wf = (string) ($ses->workflow_status ?? 'draft');
                $st = $byDate[$d]['status'] ?? null;
                if (!$st) {
                    $st = ($wf === 'submitted') ? 'absent' : 'unmarked';
                }
                if ($st === 'present') $present++;
                elseif ($st === 'permission') $permission++;
                elseif ($st === 'absent') $absent++;
                else $unmarked++;
            }

            $total = $sessionCount;
            $rate = $total > 0 ? round(($present * 100.0) / $total, 1) : null;
            $attendanceMark = $rate;

            return [
                'student_id' => $sid,
                'full_name' => $s->full_name,
                'present' => $present,
                'permission' => $permission,
                'absent' => $absent,
                'unmarked' => $unmarked,
                'total_days' => $total,
                'present_rate' => $rate,
                'attendance_mark_percent' => $attendanceMark,
            ];
        })->values();

        if ($format === 'csv') {
            $className = DB::table('classes')->where('id', $classId)->value('name');
            $fileBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($className ?: "class_{$classId}"));
            $filename = "report_range_{$fileBase}_{$from}_to_{$to}.csv";

            $response = new StreamedResponse(function () use ($perStudent, $classId, $className, $from, $to, $sessionCount) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fputcsv($out, ['from', 'to', 'class_id', 'class_name', 'sessions', 'student_id', 'full_name', 'present', 'permission', 'absent', 'unmarked', 'total_days', 'present_rate', 'attendance_mark_percent']);
                foreach ($perStudent as $row) {
                    fputcsv($out, [
                        $from,
                        $to,
                        $classId,
                        $className,
                        $sessionCount,
                        $row['student_id'],
                        $row['full_name'],
                        $row['present'],
                        $row['permission'],
                        $row['absent'],
                        $row['unmarked'],
                        $row['total_days'],
                        $row['present_rate'],
                        $row['attendance_mark_percent'],
                    ]);
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            return $response;
        }

        $totals = [
            'present' => (int) $perStudent->sum('present'),
            'permission' => (int) $perStudent->sum('permission'),
            'absent' => (int) $perStudent->sum('absent'),
            'unmarked' => (int) $perStudent->sum('unmarked'),
            'total' => (int) $perStudent->sum('total_days'),
        ];

        return response()->json([
            'class_id' => $classId,
            'from' => $from,
            'to' => $to,
            'sessions' => [
                'count' => $sessionCount,
                'submitted_any' => $hasSubmitted,
            ],
            'totals' => $totals,
            'rows' => $perStudent,
        ]);
    }

    public function classTrend(Request $request, int $classId)
    {
        $this->authorizeUserForClass($request, $classId);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'format' => ['nullable', 'string'],
        ]);

        $from = Carbon::parse($data['from'])->toDateString();
        $to = Carbon::parse($data['to'])->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $format = (string) ($data['format'] ?? $request->query('format', 'json'));

        // Trend by day: count statuses on submitted sessions by default;
        // draft sessions are included but missing are treated as unmarked.
        $sessions = DB::table('att_sessions')
            ->where('class_id', $classId)
            ->whereBetween('attendance_date', [$from, $to])
            ->select(['id', 'attendance_date', 'workflow_status'])
            ->orderBy('attendance_date')
            ->get();

        $sessionIds = $sessions->pluck('id')->map(fn ($v) => (int) $v)->all();

        $rosterCount = (int) DB::table('class_enrollments')
            ->where('class_id', $classId)
            ->where('status', 'active')
            ->count();

        $byDay = [];
        foreach ($sessions as $s) {
            $byDay[(string) $s->attendance_date] = [
                'date' => (string) $s->attendance_date,
                'workflow_status' => (string) ($s->workflow_status ?? 'draft'),
                'present' => 0,
                'permission' => 0,
                'absent' => 0,
                'unmarked' => $rosterCount,
                'total' => $rosterCount,
            ];
        }

        if (!empty($sessionIds)) {
            $rows = DB::table('att_attendance as aa')
                ->join('att_sessions as ses', 'ses.id', '=', 'aa.session_id')
                ->where('ses.class_id', $classId)
                ->whereBetween('ses.attendance_date', [$from, $to])
                ->selectRaw('ses.attendance_date as d, ses.workflow_status as wf, aa.status as st, COUNT(*) as c')
                ->groupBy('ses.attendance_date', 'ses.workflow_status', 'aa.status')
                ->get();

            foreach ($rows as $r) {
                $d = (string) $r->d;
                if (!isset($byDay[$d])) continue;
                $st = (string) $r->st;
                $c = (int) $r->c;
                if ($st === 'present') $byDay[$d]['present'] += $c;
                elseif ($st === 'permission') $byDay[$d]['permission'] += $c;
                elseif ($st === 'absent') $byDay[$d]['absent'] += $c;
                // unmarked is computed below
            }

            foreach ($byDay as $d => &$v) {
                $marked = $v['present'] + $v['permission'] + $v['absent'];
                $v['unmarked'] = max(0, $rosterCount - $marked);
                if ($v['workflow_status'] === 'submitted') {
                    // Submitted sessions treat unmarked as absent.
                    $v['absent'] += $v['unmarked'];
                    $v['unmarked'] = 0;
                }
                $v['present_rate'] = $v['total'] > 0 ? round(($v['present'] * 100.0) / $v['total'], 1) : null;
            }
            unset($v);
        }

        $trend = array_values($byDay);

        if ($format === 'csv') {
            $className = DB::table('classes')->where('id', $classId)->value('name');
            $fileBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($className ?: "class_{$classId}"));
            $filename = "report_trend_{$fileBase}_{$from}_to_{$to}.csv";

            $response = new StreamedResponse(function () use ($trend, $classId, $className, $from, $to) {
                $out = fopen('php://output', 'w');
                if ($out === false) return;
                fputcsv($out, ['from', 'to', 'class_id', 'class_name', 'date', 'workflow_status', 'present', 'permission', 'absent', 'unmarked', 'total', 'present_rate']);
                foreach ($trend as $row) {
                    fputcsv($out, [
                        $from,
                        $to,
                        $classId,
                        $className,
                        $row['date'],
                        $row['workflow_status'],
                        $row['present'],
                        $row['permission'],
                        $row['absent'],
                        $row['unmarked'],
                        $row['total'],
                        $row['present_rate'],
                    ]);
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            return $response;
        }

        return response()->json([
            'class_id' => $classId,
            'from' => $from,
            'to' => $to,
            'roster_count' => $rosterCount,
            'days' => $trend,
        ]);
    }

    public function studentDetail(Request $request, int $studentId)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'format' => ['nullable', 'string'],
        ]);

        $classId = (int) $data['class_id'];
        $from = Carbon::parse($data['from'])->toDateString();
        $to = Carbon::parse($data['to'])->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $format = (string) ($data['format'] ?? $request->query('format', 'json'));

        // Authorization:
        // - Admin can view any student's report.
        // - Teacher account can only view if assigned to class and student is in the class roster.
        $this->authorizeUserForStudentInClass($request, $studentId, $classId);

        $student = DB::table('students')
            ->where('id', $studentId)
            ->first(['id', 'full_name', 'gender', 'current_grade']);

        if (!$student) {
            abort(404, 'Student not found');
        }

        $className = DB::table('classes')->where('id', $classId)->value('name');

        // Per-session detail (including "missing" marks as unmarked/absent depending on workflow).
        $rows = DB::table('att_sessions as ses')
            ->leftJoin('att_attendance as aa', function ($join) use ($studentId) {
                $join->on('aa.session_id', '=', 'ses.id')
                    ->where('aa.student_id', '=', $studentId);
            })
            ->where('ses.class_id', $classId)
            ->whereBetween('ses.attendance_date', [$from, $to])
            ->orderBy('ses.attendance_date')
            ->orderBy('ses.id')
            ->get([
                'ses.id as session_id',
                'ses.attendance_date',
                'ses.workflow_status',
                'ses.submitted_at',
                'aa.status',
                'aa.method',
                'aa.marked_at',
                'aa.note',
            ])
            ->map(function ($r) {
                $wf = (string) ($r->workflow_status ?? 'draft');
                $status = $r->status;
                if (!$status) {
                    $status = ($wf === 'submitted') ? 'absent' : 'unmarked';
                }
                return [
                    'session_id' => (int) $r->session_id,
                    'attendance_date' => (string) $r->attendance_date,
                    'workflow_status' => $wf,
                    'submitted_at' => $r->submitted_at ? Carbon::parse($r->submitted_at)->toISOString() : null,
                    'status' => (string) $status,
                    'method' => $r->method,
                    'marked_at' => $r->marked_at ? Carbon::parse($r->marked_at)->toISOString() : null,
                    'note' => $r->note,
                ];
            })
            ->values();

        $counts = [
            'present' => (int) $rows->where('status', 'present')->count(),
            'permission' => (int) $rows->where('status', 'permission')->count(),
            'absent' => (int) $rows->where('status', 'absent')->count(),
            'unmarked' => (int) $rows->where('status', 'unmarked')->count(),
            'total' => (int) $rows->count(),
        ];
        $presentRate = $counts['total'] > 0 ? round(($counts['present'] * 100.0) / $counts['total'], 1) : null;

        if ($format === 'csv') {
            $fileBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($student->full_name ?: "student_{$studentId}"));
            $classBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($className ?: "class_{$classId}"));
            $filename = "report_student_{$fileBase}_{$classBase}_{$from}_to_{$to}.csv";

            $response = new StreamedResponse(function () use ($rows, $student, $classId, $className, $from, $to) {
                $out = fopen('php://output', 'w');
                if ($out === false) return;
                fputcsv($out, [
                    'from',
                    'to',
                    'class_id',
                    'class_name',
                    'student_id',
                    'full_name',
                    'session_id',
                    'attendance_date',
                    'workflow_status',
                    'status',
                    'method',
                    'marked_at',
                    'note',
                ]);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $from,
                        $to,
                        $classId,
                        $className,
                        (int) $student->id,
                        (string) $student->full_name,
                        $row['session_id'],
                        $row['attendance_date'],
                        $row['workflow_status'],
                        $row['status'],
                        $row['method'],
                        $row['marked_at'],
                        $row['note'],
                    ]);
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            return $response;
        }

        return response()->json([
            'class_id' => $classId,
            'class_name' => $className,
            'student' => [
                'id' => (int) $student->id,
                'full_name' => (string) $student->full_name,
                'gender' => $student->gender,
                'current_grade' => $student->current_grade,
            ],
            'from' => $from,
            'to' => $to,
            'counts' => $counts,
            'present_rate' => $presentRate,
            'rows' => $rows,
        ]);
    }

    protected function authorizeUserForClass(Request $request, int $classId): void
    {
        $user = $request->user();
        if ($user instanceof Admin) {
            return;
        }
        if ($user instanceof AttTeacherAccount) {
            $teacherId = (int) $user->teacher_id;

            $assigned = DB::table('att_teacher_class_assignments')
                ->where('class_id', $classId)
                ->where('teacher_id', $teacherId)
                ->where('is_active', 1)
                ->exists()
                || DB::table('class_teachers')
                    ->where('class_id', $classId)
                    ->where('teacher_id', $teacherId)
                    ->where('is_active', 1)
                    ->exists()
                || DB::table('classes')->where('id', $classId)->where('teacher_id', $teacherId)->exists();

            if (!$assigned) {
                abort(403, 'Teacher not assigned to this class');
            }
            return;
        }

        abort(403, 'Forbidden');
    }

    protected function authorizeUserForStudentInClass(Request $request, int $studentId, int $classId): void
    {
        $user = $request->user();
        if ($user instanceof Admin) {
            return;
        }

        // Reuse class authorization for teacher accounts.
        $this->authorizeUserForClass($request, $classId);

        // Teacher must only view students in that class.
        if ($user instanceof AttTeacherAccount) {
            $member = DB::table('class_enrollments')
                ->where('class_id', $classId)
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->exists();

            if (!$member) {
                abort(403, 'Student is not in this class');
            }

            return;
        }

        abort(403, 'Forbidden');
    }

    protected function authorizeAdmin(Request $request): void
    {
        if (!$request->user() instanceof Admin) {
            abort(403, 'Admin only');
        }
    }

    protected function audit(Request $request, string $action, array $meta = []): void
    {
        $user = $request->user();
        $actorType = 'system';
        $actorId = null;

        if ($user instanceof Admin) {
            $actorType = 'admin';
            $actorId = (int) $user->id;
        } elseif ($user instanceof AttTeacherAccount) {
            $actorType = 'teacher';
            $actorId = (int) $user->id;
        }

        DB::table('att_audit_logs')->insert([
            'session_id' => null,
            'class_id' => null,
            'student_id' => null,
            'attendance_id' => null,
            'action' => $action,
            'meta' => !empty($meta) ? json_encode($meta) : null,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'ip' => (string) $request->ip(),
            'user_agent' => substr((string) ($request->userAgent() ?? ''), 0, 255),
            'created_at' => now(),
        ]);
    }
}
