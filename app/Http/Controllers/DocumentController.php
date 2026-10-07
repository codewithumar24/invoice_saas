<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ExpenseAttachment;
use App\Services\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Document centre: one place for invoices, receipts, quotes, contracts and customer files. */
class DocumentController extends Controller
{
    public const CATEGORIES = ['invoices' => 'Invoices', 'receipts' => 'Receipts', 'quotes' => 'Quotes', 'contracts' => 'Contracts', 'customer_files' => 'Customer files', 'general' => 'General'];

    public function index(Request $request)
    {
        $documents = Attachment::with(['attachable', 'uploader'])
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->when($request->query('search'), fn ($q, $term) => $q->where('original_name', 'like', $this->like($term)))
            ->latest()->paginate(20)->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'categories' => self::CATEGORIES,
            // Expense receipts live with their expenses; they are listed here read-only
            'receipts' => $request->query('category') === 'expense_receipts'
                ? ExpenseAttachment::with('expense')->when($request->query('search'), fn ($q, $term) => $q->where('original_name', 'like', $this->like($term)))->latest()->paginate(20)->withQueryString()
                : null,
        ]);
    }

    public function store(Request $request, FileStorageService $files): RedirectResponse
    {
        $request->validate([
            'file' => array_merge(['required'], FileStorageService::rule()),
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
        ]);

        $files->attach($request->file('file'), null, $request->input('category'));

        return back()->with('success', __('Document uploaded.'));
    }

    /** Files are streamed through this authorised route; storage paths are never exposed. */
    public function download(Attachment $document, FileStorageService $files)
    {
        return $files->download($document->disk, $document->path, $document->original_name);
    }

    public function destroy(Attachment $document, FileStorageService $files): RedirectResponse
    {
        $files->delete($document->disk, $document->path);
        $document->delete();

        return back()->with('success', __('Document deleted.'));
    }
}
