<?php

namespace App\Livewire\Tenant\Notifications;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public function render(): View
    {
        $notificationsAvailable = Schema::hasTable('user_notifications');
        $notifications = $notificationsAvailable
            ? UserNotification::forUser($this->currentUserId())->latest()->take(30)->get()
            : collect();

        return view('livewire.tenant.notifications.index', [
            'notificationsAvailable' => $notificationsAvailable,
            'notifications' => $notifications,
        ])->layout('layouts.dashboard-shell', $this->tenantShell('Notifications'));
    }
}
