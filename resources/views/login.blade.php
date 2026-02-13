<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finot Attendance – Admin Login</title>
    <style>
        :root { --bg:#0f172a; --card:#111827; --accent:#6366f1; --muted:#94a3b8; --text:#e2e8f0; --border:#1f2937; }
        * { box-sizing:border-box; }
        body { margin:0; height:100vh; display:flex; align-items:center; justify-content:center; background:radial-gradient(circle at 20% 20%, rgba(99,102,241,0.15), transparent 25%), radial-gradient(circle at 80% 30%, rgba(14,165,233,0.12), transparent 25%), var(--bg); color:var(--text); font-family:"Inter", system-ui, -apple-system, sans-serif; }
        .card { width: 360px; background:var(--card); border:1px solid var(--border); border-radius:16px; padding:24px; box-shadow:0 24px 60px rgba(0,0,0,0.35); }
        h2 { margin:0 0 12px 0; }
        label { display:block; margin:10px 0 6px 0; color:var(--muted); font-size:13px; }
        input { width:100%; padding:11px 12px; border-radius:10px; border:1px solid var(--border); background:#0b1224; color:var(--text); font-size:14px; }
        button { width:100%; margin-top:14px; padding:11px 12px; border:none; border-radius:10px; background:linear-gradient(135deg, #6366f1, #14b8a6); color:#fff; font-weight:700; cursor:pointer; font-size:15px; }
        .muted { color:var(--muted); font-size:13px; margin-top:8px; text-align:center; }
        .error { color:#f87171; font-size:13px; margin-top:8px; text-align:center; }
    </style>
</head>
<body>
    <div class="card" id="login-card">
        <h2>Admin Login</h2>
        <label for="username">Username</label>
        <input id="username" placeholder="admin">
        <label for="password">Password</label>
        <input id="password" type="password" placeholder="••••••••">
        <button id="login-btn">
            <span id="login-text">Login</span>
            <span id="login-spinner" style="display: none;">Logging in...</span>
        </button>
        <div class="muted">Use your admin credentials to access the dashboard.</div>
        <div class="error" id="error"></div>
    </div>

    <script>
        const btn = document.getElementById('login-btn');
        const err = document.getElementById('error');
        const u = document.getElementById('username');
        const p = document.getElementById('password');

        // Check if already logged in
        const token = localStorage.getItem('finot_token');
        if (token) {
            // Verify token is still valid
            fetch('/api/v1/classes', {
                headers: {'Authorization': `Bearer ${token}`}
            }).then(res => {
                if (res.ok) {
                    window.location.href = '/admin';
                } else {
                    // Token invalid, clear storage
                    localStorage.removeItem('finot_token');
                    localStorage.removeItem('finot_user');
                }
            }).catch(() => {
                // Network error, clear storage
                localStorage.removeItem('finot_token');
                localStorage.removeItem('finot_user');
            });
        }

        btn.addEventListener('click', () => {
            err.textContent = '';
            
            // Show loading state
            document.getElementById('login-text').style.display = 'none';
            document.getElementById('login-spinner').style.display = 'inline';
            btn.disabled = true;
            
            console.log('Attempting login...');
            
            fetch('/api/v1/login', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({username: u.value, password: p.value, device_name: 'admin-web'})
            }).then(async res => {
                console.log('Login response status:', res.status);
                if (!res.ok) throw new Error('Bad credentials');
                return res.json();
            }).then(data => {
                console.log('Login successful, token received:', data.token);
                localStorage.setItem('finot_token', data.token);
                localStorage.setItem('finot_user', JSON.stringify(data.user));
                
                // Set up default Authorization header for future requests
                const originalFetch = window.fetch;
                window.fetch = function(url, options = {}) {
                    const token = localStorage.getItem('finot_token');
                    if (token && !options.headers) {
                        options.headers = {};
                    }
                    if (token && options.headers) {
                        options.headers['Authorization'] = `Bearer ${token}`;
                    }
                    return originalFetch(url, options);
                };
                
                console.log('Redirecting to admin...');
                window.location.href = '/admin';
            }).catch(error => {
                console.error('Login error:', error);
                err.textContent = 'Login failed. Check username/password.';
                
                // Reset button state
                document.getElementById('login-text').style.display = 'inline';
                document.getElementById('login-spinner').style.display = 'none';
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>
