<?php

namespace App\Support;

use Illuminate\View\View;

interface LivewirePageView
{
    public function layout(string $view, array $params = []): self;

    public function layoutData(array $data = []): View;
}
