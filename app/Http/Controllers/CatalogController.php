<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\GivingType;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    private function catalog(string $catalog): array
    {
        return match ($catalog) {
            'districts' => [District::class, 'Districts'],
            'groups' => [Group::class, 'Groups'],
            'giving-types' => [GivingType::class, 'Giving types'],
            default => abort(404),
        };
    }

    public function index(string $catalog): View
    {
        [$model, $title] = $this->catalog($catalog);

        return view('catalogs.index', ['catalog' => $catalog, 'title' => $title, 'items' => $model::orderBy('name')->paginate(25)]);
    }

    public function store(Request $request, string $catalog): RedirectResponse
    {
        [$model] = $this->catalog($catalog);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique((new $model)->getTable())]]);
        $model::create([...$data, 'is_active' => true]);

        return redirect()->route('catalogs.index', $catalog)->with('success', 'Item added and available in the dropdown.');
    }

    public function update(Request $request, string $catalog, int $item): RedirectResponse
    {
        [$model] = $this->catalog($catalog);
        $record = $model::findOrFail($item);
        $record->update($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique($record->getTable())->ignore($record->id)],
            'is_active' => ['required', 'boolean'],
        ]));

        return redirect()->route('catalogs.index', $catalog)->with('success', 'Item updated. Existing records have been preserved.');
    }
}
