<?php

namespace App\Console\Commands;

use App\Models\AttSession;
use App\Models\AttSessionToken;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RotateSessionTokens extends Command
{
    protected $signature = 'attendance:rotate-tokens {--ttl=60 : Token TTL in seconds (15-300)}';
    protected $description = 'Rotate QR tokens for open attendance sessions';

    public function handle(): int
    {
        $ttl = max(15, min(300, (int) $this->option('ttl')));
        $now = now();

        $sessions = AttSession::where('status', 'open')
            ->where(function ($q) use ($now) {
                $q->whereNull('token_expires_at')
                  ->orWhere('token_expires_at', '<=', $now);
            })
            ->get();

        foreach ($sessions as $session) {
            $token = $this->generateToken();
            $expires = $now->copy()->addSeconds($ttl);

            $session->update([
                'current_token' => $token,
                'token_expires_at' => $expires,
            ]);

            AttSessionToken::create([
                'session_id' => $session->id,
                'token' => $token,
                'expires_at' => $expires,
            ]);
        }

        $this->info("Rotated tokens for {$sessions->count()} session(s).");
        return Command::SUCCESS;
    }

    protected function generateToken(): string
    {
        return Str::upper(Str::random(16));
    }
}
