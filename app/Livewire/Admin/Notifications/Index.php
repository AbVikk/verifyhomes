<?php

namespace App\Livewire\Admin\Notifications;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    use HasAdminLayout;

    public function render(): View
    {
        $notificationsAvailable = Schema::hasTable('user_notifications');
        $notifications = $notificationsAvailable
            ? UserNotification::forUser(auth()->id())->latest()->take(40)->get()
            : collect();

        return $this->adminPage(view('livewire.admin.notifications.index', [
            'notificationsAvailable' => $notificationsAvailable,
            'notifications' => $notifications,
        ]), 'Notifications');
    }
}
