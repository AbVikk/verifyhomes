<?php

namespace App\Livewire\Admin\Concerns;

use App\Support\LivewirePageView;
use Illuminate\View\View;

trait HasAdminLayout
{
    protected function adminPage(View $view, string $pageHeading, ?string $pageActionLabel = null, ?string $pageActionHref = null): View
    {
        /** @var View&LivewirePageView $pageView */
        $pageView = $view;

        return $pageView
            ->layout('components.admin-layout')
            ->layoutData([
                'pageHeading' => $pageHeading,
                'pageActionLabel' => $pageActionLabel,
                'pageActionHref' => $pageActionHref,
            ]);
    }
}
