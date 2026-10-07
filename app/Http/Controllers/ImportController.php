<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Services\FileStorageService;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CSV import in four screens: upload, map columns, preview, result. */
class ImportController extends Controller
{
    public function index(Request $request)
    {
        return view('imports.index', ['imports' => Import::with('user')->latest()->paginate($this->perPage), 'types' => array_keys(ImportService::FIELDS)]);
    }

    public function store(Request $request, FileStorageService $files, ImportService $imports): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ImportService::FIELDS))],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $this->authorizeType($request, $data['type']);
        $stored = $files->store($request->file('file'), 'imports');
        $import = Import::create(['user_id' => $request->user()->id, 'type' => $data['type'], 'disk' => $stored['disk'], 'path' => $stored['path']]);
        $import->update(['mapping' => $imports->guessMapping($data['type'], $imports->preview($import)['header'])]);

        return redirect()->route('imports.show', $import);
    }

    public function show(Import $import, ImportService $imports)
    {
        return view('imports.show', [
            'import' => $import,
            'fields' => ImportService::FIELDS[$import->type],
            'preview' => $import->status === 'uploaded' ? $imports->preview($import) : null,
        ]);
    }

    /** Save the column mapping and start the import in the queue. */
    public function start(Request $request, Import $import): RedirectResponse
    {
        abort_unless($import->status === 'uploaded', 409);
        $this->authorizeType($request, $import->type);
        $fields = ImportService::FIELDS[$import->type];

        $data = $request->validate(['mapping' => ['required', 'array'], 'mapping.*' => ['nullable', 'integer', 'min:0', 'max:200']]);
        $mapping = array_filter(array_intersect_key($data['mapping'], $fields), fn ($index) => $index !== null && $index !== '');

        // Every required field must be mapped to a column
        foreach ($fields as $field => [$label, $rules]) {
            if (str_contains($rules, 'required') && ! array_key_exists($field, $mapping)) {
                return back()->withErrors(['mapping' => __('Choose a column for ":field".', ['field' => __($label)])])->withInput();
            }
        }

        $import->update(['mapping' => $mapping]);
        ProcessImportJob::dispatch($import->tenant_id, $import->id);

        return redirect()->route('imports.show', $import)->with('success', __('Import started. This page shows the result when it is finished.'));
    }

    private function authorizeType(Request $request, string $type): void
    {
        abort_unless($request->user()->hasPermission($type.'.create'), 403);
    }
}
