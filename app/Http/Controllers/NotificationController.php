<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function open(Request $request, UserNotification $notification): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = UserNotification::query()
            ->forUser($user->getKey())
            ->findOrFail($notification->getKey());

        $notification->markRead();

        return redirect()->to($this->destinationFor($notification, $request, $user));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        UserNotification::query()
            ->forUser($user->getKey())
            ->unread()
            ->update(['read_at' => now()]);

        return redirect()->back(fallback: route($user->dashboardRouteName()));
    }

    protected function destinationFor(UserNotification $notification, Request $request, User $user): string
    {
        $link = $notification->link;

        if (! is_string($link) || $link === '') {
            return route($user->dashboardRouteName());
        }

        $parts = parse_url($link);

        if ($parts === false || str_starts_with($link, '//')) {
            return route($user->dashboardRouteName());
        }

        if (! isset($parts['host'])) {
            return str_starts_with($link, '/') ? $link : route($user->dashboardRouteName());
        }

        $allowedHosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            $request->getHost(),
        ]);

        return in_array($parts['host'], $allowedHosts, true)
            ? $link
            : route($user->dashboardRouteName());
    }
}
