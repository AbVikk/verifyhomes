<?php

namespace App\Support;

use App\Models\Occupancy;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class RentReminderGenerator
{
    protected const STAGES = [60 => 'due_in_60_days', 30 => 'due_in_30_days', 7 => 'due_in_7_days', 0 => 'due_today', -7 => 'overdue_7_days', -30 => 'overdue_30_days'];

    public function generate(?Carbon $now = null): int
    {
        if (! Schema::hasTable('occupancies') || ! Schema::hasTable('user_notifications')) return 0;
        $now ??= now();
        $created = 0;

        Occupancy::query()->where('status', 'active')->whereHas('property', fn ($query) => $query->where('listing_intent', 'for_rent'))->with(['property.landlord', 'tenant'])->get()
            ->each(function (Occupancy $occupancy) use ($now, &$created): void {
                $stage = $this->stageFor($occupancy, $now);
                if (! $stage) return;
                $created += $this->notifyTenant($occupancy, $stage);
                if ($stage['days'] <= 30) $created += $this->notifyLandlord($occupancy, $stage);
                if ($stage['days'] <= 0) $created += $this->notifyAdmins($occupancy, $stage);
            });

        return $created;
    }

    public function stageFor(Occupancy $occupancy, Carbon $now): ?array
    {
        $dueAt = $occupancy->computedNextPaymentDueAt();
        if (! $dueAt) return null;
        $days = (int) $now->copy()->startOfDay()->diffInDays($dueAt->copy()->startOfDay(), false);
        $key = self::STAGES[$days] ?? null;
        return $key ? ['key' => $key, 'days' => $days, 'due_at' => $dueAt, 'cycle' => $dueAt->toDateString()] : null;
    }

    protected function notifyTenant(Occupancy $occupancy, array $stage): int
    {
        return $this->notify($occupancy->tenant, $occupancy, $stage, 'tenant', $this->tenantTitle($stage), $this->tenantBody($occupancy, $stage), route('tenant.occupancy.index'), 'View My Stay');
    }

    protected function notifyLandlord(Occupancy $occupancy, array $stage): int
    {
        return $this->notify($occupancy->property?->landlord, $occupancy, $stage, 'landlord', $this->landlordTitle($stage), $this->landlordBody($occupancy, $stage), route('landlord.occupancy.index', ['tenant' => $occupancy->tenant_id]), 'View occupancy');
    }

    protected function notifyAdmins(Occupancy $occupancy, array $stage): int
    {
        return User::role(['admin', 'staff'])->get()->sum(fn (User $admin) => $this->notify($admin, $occupancy, $stage, 'admin', $this->adminTitle($stage), $this->adminBody($occupancy, $stage), route('admin.occupancy.index'), 'Review occupancy'));
    }

    protected function notify(?User $user, Occupancy $occupancy, array $stage, string $role, string $title, string $body, string $link, string $actionLabel): int
    {
        if (! $user) return 0;
        $eventKey = "rent-reminder:occupancy:{$occupancy->getKey()}:{$stage['cycle']}:{$stage['key']}:{$role}";
        $category = "rent_reminder:{$role}:{$occupancy->getKey()}:{$stage['key']}:{$stage['cycle']}";
        return app(WorkflowNotifier::class)->notify($user, $eventKey, $title, $body, $link, $category, $actionLabel) ? 1 : 0;
    }

    protected function tenantTitle(array $stage): string { return match ($stage['days']) { 60 => 'Your rent is due in 60 days', 30 => 'Your rent is due in 30 days', 7 => 'Your rent is due in 7 days', 0 => 'Your rent is due today', -7 => 'Your rent is overdue by 7 days', -30 => 'Your rent is overdue by 30 days' }; }
    protected function landlordTitle(array $stage): string { return match ($stage['days']) { 60 => 'Tenant rent due in 60 days', 30 => 'Tenant rent due in 30 days', 7 => 'Tenant rent due in 7 days', 0 => 'Tenant rent due today', -7 => 'Tenant rent overdue by 7 days', -30 => 'Tenant rent overdue by 30 days' }; }
    protected function adminTitle(array $stage): string { return match ($stage['days']) { 0 => 'Rent due today', -7 => 'Rent overdue by 7 days', -30 => 'Rent overdue by 30 days' }; }

    protected function tenantBody(Occupancy $occupancy, array $stage): string
    {
        $property = $occupancy->property?->title ?? 'your rented property';
        return "Rent for {$property} is ".($stage['days'] < 0 ? 'overdue' : 'due')." on {$stage['due_at']->format('M j, Y')}. Renewal period: {$occupancy->rentalPeriodLabel()}.";
    }

    protected function landlordBody(Occupancy $occupancy, array $stage): string
    {
        return ($occupancy->tenant?->name ?? 'A tenant')."'s rent for ".($occupancy->property?->title ?? 'your property').' is '.($stage['days'] < 0 ? 'overdue' : 'due')." on {$stage['due_at']->format('M j, Y')}. Rental period: {$occupancy->rentalPeriodLabel()}.";
    }

    protected function adminBody(Occupancy $occupancy, array $stage): string
    {
        return ($occupancy->tenant?->name ?? 'Tenant').'; landlord: '.($occupancy->property?->landlord?->name ?? 'Landlord').'; property: '.($occupancy->property?->title ?? 'property')."; rent due {$stage['due_at']->format('M j, Y')}; rental period: {$occupancy->rentalPeriodLabel()}.";
    }
}
