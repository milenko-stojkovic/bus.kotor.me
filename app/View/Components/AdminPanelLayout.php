<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AdminPanelLayout extends Component
{
    public function __construct(
        public ?string $pageTitle = null,
        public string $navActive = 'dashboard',
    ) {}

    public function render(): View
    {
        return view('layouts.admin-panel');
    }
}
