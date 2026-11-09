<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders every menu item registered in config/menu.php that doesn't have
 * a real controller yet. As each module gets its backend built, replace its
 * route registration in routes/web.php with a dedicated controller and
 * remove the item's "uri" fallback here — the sidebar keeps working either
 * way since it only reads config/menu.php.
 */
class ModulePlaceholderController extends Controller
{
    public function __invoke(Request $request): View
    {
        $routeName = $request->route()->getName();

        $item = collect(config('menu'))
            ->flatMap(fn (array $group) => collect($group['sections'])->flatMap(fn (array $section) => $section['items']))
            ->firstWhere('route', $routeName);

        abort_unless($item, 404);

        return view('modules.placeholder', [
            'title' => $item['label'],
            'icon' => $item['icon'] ?? 'fa-circle-info',
            'description' => $item['description'] ?? null,
        ]);
    }
}
