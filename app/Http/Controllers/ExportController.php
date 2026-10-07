<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateExportJob;
use App\Models\Export;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\FileStorageService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExportController extends Controller
{
    public function __construct(private ExportService $exports) {}

    public function index(Request $request)
    {
        return view('exports.index', [
            'exports' => Export::where('user_id', $request->user()->id)->latest()->paginate($this->perPage),
            'types' => ExportService::TYPES,
        ]);
    }

    /** Small exports download immediately; large ones are generated in the queue and the user is notified. */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        app(AuditService::class)->log('export.requested', $data['type'], ['format' => $data['format']]);

        if ($this->exports->count($data['type'], $data['filters']) > ExportService::QUEUE_THRESHOLD) {
            $export = Export::create(['user_id' => $request->user()->id, 'type' => $data['type'], 'format' => $data['format'], 'filters' => $data['filters']]);
            GenerateExportJob::dispatch($export->tenant_id, $export->id);

            return redirect()->route('exports.index')->with('success', __('This is a large export. We are preparing it and will notify you when it is ready.'));
        }

        return $this->stream($data['type'], $data['format'], $data['filters']);
    }

    /** GET variant used by bulk actions and report pages. */
    public function downloadNow(Request $request)
    {
        $data = $this->validated($request);

        return $this->stream($data['type'], $data['format'], $data['filters']);
    }

    public function download(Request $request, Export $export, FileStorageService $files)
    {
        abort_unless($export->user_id === $request->user()->id && $export->status === 'completed' && $export->path, 404);

        return $files->download($export->disk, $export->path, $this->exports->filename($export->type, $export->format));
    }

    private function stream(string $type, string $format, array $filters)
    {
        $contents = $this->exports->render($this->exports->dataset($type, $filters), $format);

        return response($contents, 200, [
            'Content-Type' => ExportService::FORMATS[$format],
            'Content-Disposition' => 'attachment; filename="'.$this->exports->filename($type, $format).'"',
        ]);
    }

    private function validated(Request $request): array
    {
        $reportTypes = array_map(fn ($key) => 'report:'.$key, array_keys(ReportService::REPORTS));

        $data = $request->validate([
            'type' => ['required', Rule::in(array_merge(ExportService::TYPES, $reportTypes))],
            'format' => ['required', Rule::in(array_keys(ExportService::FORMATS))],
            'ids' => ['nullable', 'array'], 'ids.*' => ['integer'],
            'status' => ['nullable', 'string', 'max:30'], 'period' => ['nullable', 'string', 'max:10'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'customer_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'], 'location_id' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'], 'product_type' => ['nullable', 'in:product,service'],
        ]);

        // Each export type needs the matching view permission as well as reports.export
        $permission = str_starts_with($data['type'], 'report:') ? 'reports.view' : $data['type'].'.view';
        abort_unless($request->user()->hasPermission($permission), 403);

        return ['type' => $data['type'], 'format' => $data['format'], 'filters' => collect($data)->except(['type', 'format'])->filter()->all()];
    }
}
