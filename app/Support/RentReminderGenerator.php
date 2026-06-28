<?php

namespace App\Support;

use App\Models\Occupancy;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class RentReminderGenerator
{
    protected const STAGES = [
        60 => 'due_in_60_days',
        30 => 'due_in_30_days',
        7 => 'due_in_7_days',
        0 => 'due_today',
        -7 => 'overdue_7_days',
        -30 => 'overdue_30_days',
    ];

    public function generate(?Carbon $now = null): int
    {
        if (! Schema::hasTable('occupancies') || ! Schema::hasTable('user_notifications')) {
            return 0;
        }

        $now ??= now();
        $created = 0;

        Occupancy::query()
            ->where('status', 'active')
            ->whereHas('property', fn ($query) => $query->where('listing_intent', 'for_rent'))
            ->with(['property.landlord', 'tenant'])
            ->get()
            ->each(function (Occupancy $occupancy) use ($now, &$created): void {
                $stage = $this->stageFor($occupancy, $now);

                if (! $stage) {
                    return;
                }

                $created += $this->notifyTenant($occupancy, $stage);
                $created += $this->notifyLandlord($occupancy, $stage);
                $created += $this->notifyAdmins($occupancy, $stage);
            });

        return $created;
    }

    public function stageFor(Occupancy $occupancy, Carbon $now): ?array
    {
        $dueAt = $occupancy->computedNextPaymentDueAt();

        if (! $dueAt) {
            return null;
        }

        $days = (int) $now->copy()->startOfDay()->diffInDays($dueAt->copy()->startOfDay(), false);
        $stage = self::STAGES[$days] ?? null;

        if (! $stage) {
            return null;
        }

        return [
            'key' => $stage,
            'days' => $days,
            'due_at' => $dueAt,
            'cycle' => $dueAt->copy()->toDateString(),
        ];
    }

    protected function notifyTenant(Occupancy $occupancy, array $stage): int
    {
        if (! $occupancy->tenant) {
            return 0;
        }

        return $this->createNotification(
            user: $occupancy->tenant,
            category: $this->category($occupancy, $stage, 'tenant'),
            title: $this->tenantTitle($stage),
            body: $this->tenantBody($occupancy, $stage),
            link: route('tenant.occupancy.index'),
        );
    }

    protected function notifyLandlord(Occupancy $occupancy, array $stage): int
    {
        $landlord = $occupancy->property?->landlord;

        if (! $landlord) {
            return 0;
        }

        return $this->createNotification(
            user: $landlord,
            category: $this->category($occupancy, $stage, 'landlord'),
            title: $this->landlordTitle($stage),
            body: $this->landlordBody($occupancy, $stage),
            link: route('landlord.occupancy.index', ['tenant' => $occupancy->tenant_id]),
        );
    }

    protected function notifyAdmins(Occupancy $occupancy, array $stage): int
    {
        return User::role(['admin', 'staff'])
            ->get()
            ->sum(fn (User $admin) => $this->createNotification(
                user: $admin,
                category: $this->category($occupancy, $stage, 'admin'),
                title: $this->adminTitle($stage),
                body: $this->adminBody($occupancy, $stage),
                link: route('admin.occupancy.index'),
            ));
    }

    protected function createNotification(User $user, string $category, string $title, string $body, string $link): int
    {
        $exists = UserNotification::query()
            ->where('user_id', $user->getKey())
            ->where('category', $category)
            ->exists();

        if ($exists) {
            return 0;
        }

        UserNotification::create([
            'user_id' => $user->getKey(),
            'title' => $title,
            'body' => $body,
            'category' => $category,
            'link' => $link,
        ]);

        return 1;
    }

    protected function category(Occupancy $occupancy, array $stage, string $role): string
    {
        return "rent_reminder:{$role}:{$occupancy->getKey()}:{$stage['key']}:{$stage['cycle']}";
    }

    protected function tenantTitle(array $stage): string
    {
        return match ($stage['days']) {
            60 => 'Your rent is due in 60 days',
            30 => 'Your rent is due in 30 days',
            7 => 'Your rent is due in 7 days',
            0 => 'Your rent is due today',
            -7 => 'Your rent is overdue by 7 days',
            -30 => 'Your rent is overdue by 30 days',
            default => 'Rent reminder',
        };
    }

    protected function landlordTitle(array $stage): string
    {
        return match ($stage['days']) {
            60 => 'Tenant rent due in 60 days',
            30 => 'Tenant rent due in 30 days',
            7 => 'Tenant rent due in 7 days',
            0 => 'Tenant rent due today',
            -7 => 'Tenant rent overdue by 7 days',
            -30 => 'Tenant rent overdue by 30 days',
            default => 'Tenant rent reminder',
        };
    }

    protected function adminTitle(array $stage): string
    {
        return match ($stage['days']) {
            60 => 'Upcoming rent due in 60 days',
            30 => 'Upcoming rent due in 30 days',
            7 => 'Upcoming rent due in 7 days',
            0 => 'Rent due today',
            -7 => 'Rent overdue by 7 days',
            -30 => 'Rent overdue by 30 days',
            default => 'Rent reminder generated',
        };
    }

    protected function tenantBody(Occupancy $occupancy, array $stage): string
    {
        $property = $occupancy->property?->title ?? 'your rented property';
        $dueDate = $stage['due_at']->format('M j, Y');

        return $stage['days'] < 0
            ? "Rent for {$property} was due on {$dueDate}. Please complete your next payment or contact support if you need help."
            : "Rent for {$property} is due on {$dueDate}. Please plan your next payment early.";
    }

    protected function landlordBody(Occupancy $occupancy, array $stage): string
    {
        $property = $occupancy->property?->title ?? 'your property';
        $tenant = $occupancy->tenant?->name ?? 'A tenant';
        $dueDate = $stage['due_at']->format('M j, Y');

        return $stage['days'] < 0
            ? "{$tenant}'s rent for {$property} was due on {$dueDate}. This is landlord-relevant rent follow-up only."
            : "{$tenant}'s rent for {$property} is due on {$dueDate}.";
    }

    protected function adminBody(Occupancy $occupancy, array $stage): string
    {
        $property = $occupancy->property?->title ?? 'a rented property';
        $tenant = $occupancy->tenant?->name ?? 'Tenant';
        $dueDate = $stage['due_at']->format('M j, Y');

        return $stage['days'] < 0
            ? "{$tenant} has overdue rent for {$property}. Due date: {$dueDate}."
            : "{$tenant} has upcoming rent for {$property}. Due date: {$dueDate}.";
    }
}
