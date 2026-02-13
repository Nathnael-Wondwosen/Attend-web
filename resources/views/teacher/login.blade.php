<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finot Attendance - Teacher Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(180deg, #070912, #0b1021); }
    </style>
</head>
<body class="min-h-screen text-slate-100 flex items-center justify-center p-5">
    <div class="w-full max-w-sm bg-white/5 border border-white/10 backdrop-blur rounded-2xl p-6 shadow-xl">
        <div class="mb-5">
            <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Finot</p>
            <h1 class="text-2xl font-semibold text-white mt-1">Teacher Login</h1>
            <p class="text-sm text-slate-300 mt-2">Sign in to take attendance.</p>
        </div>

        <div class="space-y-3">
            <div>
                <label class="text-xs text-slate-400">Username</label>
                <input id="username" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-4 py-3 text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-400/50" placeholder="your username" />
            </div>
            <div>
                <label class="text-xs text-slate-400">Password</label>
                <input id="password" type="password" class="mt-1 w-full rounded-xl bg-white/5 border border-white/10 px-4 py-3 text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-400/50" placeholder="password" />
            </div>

            <button id="login-btn" class="w-full rounded-xl px-4 py-3 bg-gradient-to-r from-cyan-400 to-emerald-400 text-slate-900 font-semibold">
                Login
            </button>
            <p id="error" class="text-sm text-red-300"></p>
        </div>
    </div>

    <script>
        const tokenKey = 'finot_teacher_token';
        const btn = document.getElementById('login-btn');
        const err = document.getElementById('error');

        const existing = localStorage.getItem(tokenKey);
        if (existing) {
            fetch('/api/v1/me', { headers: { Authorization: `Bearer ${existing}` } })
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(j => {
                    if (j.type === 'teacher') window.location.href = '/teacher/attendance';
                    else localStorage.removeItem(tokenKey);
                })
                .catch(() => localStorage.removeItem(tokenKey));
        }

        btn.addEventListener('click', async () => {
            err.textContent = '';
            btn.disabled = true;
            try {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                const res = await fetch('/api/v1/teacher/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password, device_name: 'teacher-web' })
                });
                const json = await res.json().catch(() => null);
                if (!res.ok) throw new Error(json?.message || 'Login failed');
                localStorage.setItem(tokenKey, json.token);
                window.location.href = '/teacher/attendance';
            } catch (e) {
                err.textContent = 'Login failed. Check username/password.';
            } finally {
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>

