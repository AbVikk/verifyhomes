<?php

namespace App\Livewire\Concerns;

trait InteractsWithRoleShells
{
    protected function landlordShell(string $pageHeading, array $overrides = []): array
    {
        return array_merge([
            'brandTitle' => 'VerifyHomes Landlord',
            'homeHref' => route('landlord.dashboard'),
            'profileHref' => route('landlord.profile'),
            'roleLabel' => 'Landlord Workspace',
            'navigationLinks' => [
                [
                    'label' => 'Dashboard',
                    'href' => route('landlord.dashboard'),
                    'active' => request()->routeIs('landlord.dashboard'),
                    'icon' => 'dashboard',
                ],
                [
                    'label' => 'Profile',
                    'href' => route('landlord.profile'),
                    'active' => request()->routeIs('landlord.profile'),
                    'icon' => 'profile',
                ],
                [
                    'label' => 'Documents',
                    'href' => route('landlord.documents'),
                    'active' => request()->routeIs('landlord.documents'),
                    'icon' => 'documents',
                ],
                [
                    'label' => 'Properties',
                    'href' => route('landlord.properties'),
                    'active' => request()->routeIs('landlord.properties.*'),
                    'icon' => 'properties',
                ],
                [
                    'label' => 'Inspection Requests',
                    'href' => route('landlord.inspection-requests.index'),
                    'active' => request()->routeIs('landlord.inspection-requests.*'),
                    'icon' => 'inspection-requests',
                ],
                [
                    'label' => 'Payments',
                    'href' => route('landlord.payments.index'),
                    'active' => request()->routeIs('landlord.payments.*'),
                    'icon' => 'payments',
                ],
                [
                    'label' => 'Notifications',
                    'href' => route('landlord.notifications.index'),
                    'active' => request()->routeIs('landlord.notifications.*'),
                    'icon' => 'notifications',
                ],
                [
                    'label' => 'Occupants',
                    'href' => route('landlord.occupancy.index'),
                    'active' => request()->routeIs('landlord.occupancy.*'),
                    'icon' => 'occupancy',
                ],
            ],
            'pageHeading' => $pageHeading,
            'shellKey' => 'landlord',
            'menuTitle' => 'Workspace Menu',
            'menuCopy' => 'Track your listing pipeline, document readiness, payments, and inspection coordination from one landlord workspace.',
        ], $overrides);
    }

    protected function tenantShell(string $pageHeading, array $overrides = []): array
    {
        return array_merge([
            'brandTitle' => 'VerifyHomes Tenant',
            'homeHref' => route('tenant.dashboard'),
            'profileHref' => route('tenant.profile'),
            'roleLabel' => 'Tenant Workspace',
            'navigationLinks' => [
                [
                    'label' => 'Dashboard',
                    'href' => route('tenant.dashboard'),
                    'active' => request()->routeIs('tenant.dashboard'),
                    'icon' => 'dashboard',
                ],
                [
                    'label' => 'Profile',
                    'href' => route('tenant.profile'),
                    'active' => request()->routeIs('tenant.profile'),
                    'icon' => 'profile',
                ],
                [
                    'label' => 'Saved Listings',
                    'href' => route('tenant.saved-listings.index'),
                    'active' => request()->routeIs('tenant.saved-listings.*'),
                    'icon' => 'properties',
                ],
                [
                    'label' => 'Payments',
                    'href' => route('tenant.payments.index'),
                    'active' => request()->routeIs('tenant.payments.*'),
                    'icon' => 'payments',
                ],
                [
                    'label' => 'Notifications',
                    'href' => route('tenant.notifications.index'),
                    'active' => request()->routeIs('tenant.notifications.*'),
                    'icon' => 'notifications',
                ],
                [
                    'label' => 'My Stays',
                    'href' => route('tenant.occupancy.index'),
                    'active' => request()->routeIs('tenant.occupancy.*'),
                    'icon' => 'occupancy',
                ],
                [
                    'label' => 'Inspection Requests',
                    'href' => route('tenant.inspection-requests.index'),
                    'active' => request()->routeIs('tenant.inspection-requests.*'),
                    'icon' => 'inspection-requests',
                ],
                [
                    'label' => 'Browse Properties',
                    'href' => route('properties.index'),
                    'active' => request()->routeIs('properties.*'),
                    'icon' => 'browse-properties',
                ],
            ],
            'pageHeading' => $pageHeading,
            'shellKey' => 'tenant',
            'menuTitle' => 'Workspace Menu',
            'menuCopy' => 'Track your profile, saved listings, payments, scheduled visits, and latest inspection updates from one tenant workspace.',
        ], $overrides);
    }
}
