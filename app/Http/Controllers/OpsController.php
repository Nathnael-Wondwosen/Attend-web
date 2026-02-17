<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class OpsController extends Controller
{
    protected function opsRunKey(): string
    {
        // `env()` is unreliable at runtime after config cache.
        // Prefer config, then process env vars as fallback.
        $fromConfig = config('app.ops_run_key');
        if (is_string($fromConfig) && trim($fromConfig) !== '') {
            return trim($fromConfig);
        }

        foreach (['OPS_RUN_KEY'] as $name) {
            $v = getenv($name);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (isset($_ENV[$name]) && is_string($_ENV[$name]) && trim($_ENV[$name]) !== '') {
                return trim($_ENV[$name]);
            }
            if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && trim($_SERVER[$name]) !== '') {
                return trim($_SERVER[$name]);
            }
        }

        $fromEnv = env('OPS_RUN_KEY', '');
        return is_string($fromEnv) ? trim($fromEnv) : '';
    }

    public function index(Request $request)
    {
        // Hide the endpoint entirely unless a key is configured.
        $key = $this->opsRunKey();
        if ($key === '') {
            abort(404);
        }

        $provided = (string) $request->query('key', '');
        if (!hash_equals($key, $provided)) {
            abort(404);
        }

        return view('ops.run', [
            'key' => $provided,
            'results' => session('ops_results'),
        ]);
    }

    public function run(Request $request)
    {
        $key = $this->opsRunKey();
        if ($key === '') {
            abort(404);
        }

        $data = $request->validate([
            'key' => ['required', 'string'],
        ]);

        $provided = (string) ($data['key'] ?? '');
        if (!hash_equals($key, $provided)) {
            abort(404);
        }

        $commands = [
            ['storage:link', []],
            ['config:cache', []],
            ['route:cache', []],
            ['view:cache', []],
        ];

        $results = [];
        foreach ($commands as [$cmd, $args]) {
            try {
                $exit = Artisan::call($cmd, $args);
                $out = Artisan::output();
            } catch (\Throwable $e) {
                $exit = 1;
                $out = $e->getMessage();
            }
            $results[] = [
                'command' => $cmd,
                'exit_code' => (int) $exit,
                'output' => (string) $out,
            ];
        }

        return redirect()->to('/run?key='.urlencode($provided))->with('ops_results', $results);
    }
}
