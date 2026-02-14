<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class OpsController extends Controller
{
    public function index(Request $request)
    {
        // Hide the endpoint entirely unless a key is configured.
        $key = (string) env('OPS_RUN_KEY', '');
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
        $key = (string) env('OPS_RUN_KEY', '');
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
