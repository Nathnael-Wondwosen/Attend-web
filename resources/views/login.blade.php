<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finot Attendance - Login</title>
    <style>
        :root { --bg:#0f172a; --card:#111827; --accent:#6366f1; --muted:#94a3b8; --text:#e2e8f0; --border:#1f2937; }
        * { box-sizing:border-box; }
        body { margin:0; height:100vh; display:flex; align-items:center; justify-content:center; background:radial-gradient(circle at 20% 20%, rgba(99,102,241,0.15), transparent 25%), radial-gradient(circle at 80% 30%, rgba(14,165,233,0.12), transparent 25%), var(--bg); color:var(--text); font-family:"Inter", system-ui, -apple-system, sans-serif; }
        .card { width: 360px; background:var(--card); border:1px solid var(--border); border-radius:16px; padding:24px; box-shadow:0 24px 60px rgba(0,0,0,0.35); }
        h2 { margin:0 0 12px 0; }
        .tabs { display:flex; gap:8px; margin:8px 0 14px 0; }
        .tab { flex:1; padding:10px 12px; border-radius:10px; border:1px solid var(--border); background:#0b1224; color:var(--muted); cursor:pointer; font-weight:700; font-size:13px; }
        .tab.active { background: linear-gradient(135deg, rgba(99,102,241,0.22), rgba(20,184,166,0.12)); color:var(--text); border-color: rgba(99,102,241,0.35); }
        label { display:block; margin:10px 0 6px 0; color:var(--muted); font-size:13px; }
        input { width:100%; padding:11px 12px; border-radius:10px; border:1px solid var(--border); background:#0b1224; color:var(--text); font-size:14px; }
        button { width:100%; margin-top:14px; padding:11px 12px; border:none; border-radius:10px; background:linear-gradient(135deg, #6366f1, #14b8a6); color:#fff; font-weight:700; cursor:pointer; font-size:15px; }
        .muted { color:var(--muted); font-size:13px; margin-top:8px; text-align:center; }
        .error { color:#f87171; font-size:13px; margin-top:8px; text-align:center; }
    </style>
</head>
<body>
    <div class="card" id="login-card">
        <h2 id="title">Login</h2>
        <div class="tabs" role="tablist" aria-label="role">
            <button type="button" id="tab-admin" class="tab active">Admin</button>
            <button type="button" id="tab-teacher" class="tab">Teacher</button>
        </div>
        <label for="username">Username</label>
        <input id="username" placeholder="admin">
        <label for="password">Password</label>
        <input id="password" type="password" placeholder="********">
        <button id="login-btn">
            <span id="login-text">Login</span>
            <span id="login-spinner" style="display: none;">Logging in...</span>
        </button>
        <div class="muted" id="hint">Choose Admin or Teacher.</div>
        <div class="error" id="error"></div>
    </div>

    <script>
        const btn = document.getElementById('login-btn');
        const err = document.getElementById('error');
        const u = document.getElementById('username');
        const p = document.getElementById('password');
        const tabAdmin = document.getElementById('tab-admin');
        const tabTeacher = document.getElementById('tab-teacher');
        const title = document.getElementById('title');
        const hint = document.getElementById('hint');

        const adminTokenKey = 'finot_token';
        const adminUserKey = 'finot_user';
        const teacherTokenKey = 'finot_teacher_token';

        const params = new URLSearchParams(window.location.search);
        let role = (params.get('role') || 'admin').toLowerCase();
        if (role !== 'teacher') role = 'admin';

        const setRole = (r) => {
            role = r;
            tabAdmin.classList.toggle('active', role === 'admin');
            tabTeacher.classList.toggle('active', role === 'teacher');
            title.textContent = role === 'teacher' ? 'Teacher Login' : 'Admin Login';
            hint.textContent = role === 'teacher'
                ? 'Teachers will be redirected to /takeattendance.'
                : 'Admins will be redirected to the dashboard.';
        };

        tabAdmin.addEventListener('click', () => setRole('admin'));
        tabTeacher.addEventListener('click', () => setRole('teacher'));
        setRole(role);

        // Auto-redirect if already logged in
        const adminToken = localStorage.getItem(adminTokenKey);
        if (adminToken) {
            fetch('/api/v1/classes', { headers: {'Authorization': `Bearer ${adminToken}`} })
                .then(res => {
                    if (res.ok) window.location.href = '/admin';
                    else { localStorage.removeItem(adminTokenKey); localStorage.removeItem(adminUserKey); }
                })
                .catch(() => { localStorage.removeItem(adminTokenKey); localStorage.removeItem(adminUserKey); });
        }

        const teacherToken = localStorage.getItem(teacherTokenKey);
        if (teacherToken) {
            fetch('/api/v1/me', { headers: {'Authorization': `Bearer ${teacherToken}`} })
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(j => {
                    if (j.type === 'teacher') window.location.href = '/takeattendance';
                    else localStorage.removeItem(teacherTokenKey);
                })
                .catch(() => localStorage.removeItem(teacherTokenKey));
        }

        btn.addEventListener('click', () => {
            err.textContent = '';
            
            // Show loading state
            document.getElementById('login-text').style.display = 'none';
            document.getElementById('login-spinner').style.display = 'inline';
            btn.disabled = true;

            const endpoint = role === 'teacher' ? '/api/v1/teacher/login' : '/api/v1/login';
            const device = role === 'teacher' ? 'teacher-web' : 'admin-web';
            const payload = { username: u.value, password: p.value, device_name: device };

            fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            }).then(async res => {
                const json = await res.json().catch(() => null);
                if (!res.ok) throw new Error(json?.message || 'Bad credentials');
                return json;
            }).then(data => {
                if (role === 'teacher') {
                    localStorage.setItem(teacherTokenKey, data.token);
                    window.location.href = '/takeattendance';
                } else {
                    localStorage.setItem(adminTokenKey, data.token);
                    localStorage.setItem(adminUserKey, JSON.stringify(data.user));
                    window.location.href = '/admin';
                }
            }).catch(() => {
                err.textContent = 'Login failed. Check username/password.';
                document.getElementById('login-text').style.display = 'inline';
                document.getElementById('login-spinner').style.display = 'none';
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>
