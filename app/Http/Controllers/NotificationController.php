<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\SearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Notification centre, announcements and global search. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->when($request->query('filter') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($request->query('filter') === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->paginate(20)->withQueryString();

        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        // Only follow links that point back into this application
        return $url && str_starts_with($url, url('/')) ? redirect($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', __('All notifications marked as read.'));
    }

    public function dismissAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $request->session()->push('dismissed_announcements', $announcement->id);

        return back();
    }

    public function search(Request $request, SearchService $search)
    {
        $term = (string) $request->query('q', '');
        $groups = $search->search($request->user(), $term);

        return $request->expectsJson() ? $this->ok($groups) : view('search.index', ['groups' => $groups, 'term' => $term]);
    }
}
