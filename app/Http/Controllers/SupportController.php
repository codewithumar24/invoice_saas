<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Support tickets from a workspace to the platform team. */
class SupportController extends Controller
{
    public const CATEGORIES = ['general' => 'General', 'billing' => 'Billing', 'technical' => 'Technical problem', 'feature' => 'Feature request'];

    public function index()
    {
        return view('support.index', ['tickets' => SupportTicket::with('user')->latest('last_reply_at')->paginate($this->perPage), 'categories' => self::CATEGORIES]);
    }

    public function store(Request $request, FileStorageService $files): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'body' => ['required', 'string', 'max:10000'],
            'attachment' => array_merge(['nullable'], FileStorageService::rule()),
        ]);

        $ticket = SupportTicket::create(['user_id' => $request->user()->id, 'subject' => $data['subject'], 'category' => $data['category'], 'priority' => $data['priority'], 'last_reply_at' => now()]);
        $this->addMessage($request, $ticket, $files, false);

        User::where('is_super_admin', true)->get()->each->notify(new GenericNotification('system', __('New support ticket: :subject', ['subject' => $ticket->subject]), tenant()->name, route('admin.support.show', $ticket)));

        return redirect()->route('support.show', $ticket)->with('success', __('Ticket created. We will reply here and by notification.'));
    }

    public function show(SupportTicket $ticket)
    {
        return view('support.show', ['ticket' => $ticket->load('messages.user')]);
    }

    public function reply(Request $request, SupportTicket $ticket, FileStorageService $files): RedirectResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:10000'], 'attachment' => array_merge(['nullable'], FileStorageService::rule())]);
        abort_if($ticket->status === 'closed', 403, __('This ticket is closed.'));
        $this->addMessage($request, $ticket, $files, false);
        $ticket->update(['status' => 'open', 'last_reply_at' => now()]);

        return back()->with('success', __('Reply sent.'));
    }

    public function attachment(SupportTicket $ticket, int $message, FileStorageService $files)
    {
        $message = $ticket->messages()->whereKey($message)->firstOrFail();
        abort_unless($message->attachment_path, 404);

        return $files->download($files->privateDisk(), $message->attachment_path, $message->attachment_name);
    }

    private function addMessage(Request $request, SupportTicket $ticket, FileStorageService $files, bool $staff): void
    {
        $stored = $request->hasFile('attachment') ? $files->store($request->file('attachment'), 'support') : null;

        $ticket->messages()->create([
            'user_id' => $request->user()->id, 'is_staff' => $staff, 'body' => $request->input('body'),
            'attachment_path' => $stored['path'] ?? null, 'attachment_name' => $stored['original_name'] ?? null,
        ]);
    }
}
