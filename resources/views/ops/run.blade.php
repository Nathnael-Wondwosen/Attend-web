<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ops | Run</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #070912; color: #e2e8f0; }
        .glass { background: linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02)); border: 1px solid rgba(255,255,255,0.06); backdrop-filter: blur(14px); }
    </style>
</head>
<body class="min-h-screen">
    <main class="max-w-3xl mx-auto px-4 py-8 space-y-4">
        <div class="glass rounded-2xl p-5">
            <h1 class="text-xl text-white font-semibold">Ops Runner</h1>
            <p class="text-sm text-slate-300 mt-1">Runs: <code>storage:link</code>, <code>config:cache</code>, <code>route:cache</code>, <code>view:cache</code></p>

            <form method="POST" action="/run" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="key" value="{{ $key }}">
                <button class="h-11 px-4 rounded-xl bg-white/10 hover:bg-white/15 border border-white/10 text-white text-sm font-medium">
                    Run Commands
                </button>
            </form>
        </div>

        @if(is_array($results) && count($results))
            <div class="glass rounded-2xl p-5">
                <h2 class="text-white font-medium">Results</h2>
                <div class="mt-3 space-y-3">
                    @foreach($results as $r)
                        <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-slate-100 text-sm font-mono">{{ $r['command'] ?? '--' }}</div>
                                <div class="text-xs {{ ($r['exit_code'] ?? 1) === 0 ? 'text-emerald-300' : 'text-red-300' }}">
                                    exit {{ $r['exit_code'] ?? 1 }}
                                </div>
                            </div>
                            <pre class="mt-2 text-xs text-slate-300 whitespace-pre-wrap">{{ trim((string)($r['output'] ?? '')) }}</pre>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </main>
</body>
</html>
