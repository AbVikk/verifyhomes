<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

class TermsGateService
{
    public function requiredSeconds(): int
    {
        return (int) config('payments.terms_gate_seconds', 10);
    }

    public function open(string $gate): array
    {
        $state = [
            'opened_at' => now()->toIso8601String(),
            'opened_at_ms' => $this->nowMilliseconds(),
            'completed_at' => null,
        ];

        Session::put($this->sessionKey($gate), $state);

        return $state;
    }

    public function complete(string $gate): array
    {
        $state = $this->state($gate);
        $openedAtMs = $this->openedAtMilliseconds($gate);

        if ($openedAtMs === null || ($this->nowMilliseconds() - $openedAtMs) < ($this->requiredSeconds() * 1000)) {
            return $state;
        }

        $state['completed_at'] = now()->toIso8601String();

        Session::put($this->sessionKey($gate), $state);

        return $state;
    }

    public function isCompleted(string $gate): bool
    {
        return filled($this->state($gate)['completed_at'] ?? null);
    }

    public function secondsRemaining(string $gate): int
    {
        if ($this->isCompleted($gate)) {
            return 0;
        }

        $openedAtMs = $this->openedAtMilliseconds($gate);

        if ($openedAtMs === null) {
            return $this->requiredSeconds();
        }

        $remainingMs = max(0, ($this->requiredSeconds() * 1000) - ($this->nowMilliseconds() - $openedAtMs));

        return (int) ceil($remainingMs / 1000);
    }

    public function clear(string $gate): void
    {
        Session::forget($this->sessionKey($gate));
    }

    public function state(string $gate): array
    {
        return Session::get($this->sessionKey($gate), []);
    }

    public function openedAt(string $gate): ?Carbon
    {
        $openedAt = $this->state($gate)['opened_at'] ?? null;

        return filled($openedAt) ? Carbon::parse($openedAt) : null;
    }

    public function openedAtMilliseconds(string $gate): ?int
    {
        $state = $this->state($gate);
        $openedAtMs = $state['opened_at_ms'] ?? null;

        if (is_numeric($openedAtMs)) {
            return (int) $openedAtMs;
        }

        $openedAt = $this->openedAt($gate);

        return $openedAt?->getTimestampMs();
    }

    protected function nowMilliseconds(): int
    {
        return now()->getTimestampMs();
    }

    protected function sessionKey(string $gate): string
    {
        return 'terms_gates.'.md5($gate);
    }
}
