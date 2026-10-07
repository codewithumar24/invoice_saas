<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\BlogPost;
use App\Models\EmailTemplate;
use App\Models\KbArticle;
use App\Models\Plan;
use App\Models\SupportTicket;
use App\Notifications\GenericNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Announcements, platform email templates, knowledge base, blog and support desk. */
class ContentController extends Controller
{
    /* ------------------------------ Announcements ------------------------------ */

    public function announcements()
    {
        return view('admin.announcements', ['announcements' => Announcement::latest()->paginate(20), 'plans' => Plan::orderBy('sort_order')->get()]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['system', 'feature', 'maintenance'])],
            'target_type' => ['required', Rule::in(['all', 'plans', 'countries', 'tenants'])],
            'target_values' => ['nullable', 'required_unless:target_type,all', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $values = array_values(array_filter(array_map('trim', explode(',', (string) ($data['target_values'] ?? '')))));
        Announcement::create(['target_values' => $data['target_type'] === 'countries' ? array_map('strtoupper', $values) : $values] + $data);

        return back()->with('success', __('Announcement published.'));
    }

    public function destroyAnnouncement(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', __('Announcement removed.'));
    }

    /* ------------------------------ Platform email templates ------------------------------ */

    public function emailTemplates()
    {
        return view('admin.email-templates', ['templates' => config('email_templates'), 'customised' => EmailTemplate::whereNull('tenant_id')->pluck('key')->all()]);
    }

    public function editEmailTemplate(string $key)
    {
        abort_unless(config("email_templates.{$key}"), 404);
        $override = EmailTemplate::whereNull('tenant_id')->where('key', $key)->first();
        $default = config("email_templates.{$key}");

        return view('admin.email-template-form', ['key' => $key, 'name' => $default['name'], 'subject' => $override->subject ?? $default['subject'], 'body' => $override->body ?? $default['body'], 'isCustom' => (bool) $override]);
    }

    public function updateEmailTemplate(Request $request, string $key): RedirectResponse
    {
        abort_unless(config("email_templates.{$key}"), 404);

        if ($request->boolean('reset')) {
            EmailTemplate::whereNull('tenant_id')->where('key', $key)->delete();

            return redirect()->route('admin.email-templates')->with('success', __('Template reset to the default.'));
        }

        $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:10000']]);
        $template = EmailTemplate::whereNull('tenant_id')->where('key', $key)->first() ?? new EmailTemplate(['key' => $key]);
        $template->fill($data)->save();

        return redirect()->route('admin.email-templates')->with('success', __('Template saved.'));
    }

    /* ------------------------------ Knowledge base & blog ------------------------------ */

    public function articles(Request $request)
    {
        $type = $request->route()->getName() === 'admin.blog' ? 'blog' : 'kb';

        return view('admin.articles', ['type' => $type, 'articles' => ($type === 'blog' ? BlogPost::query() : KbArticle::query())->latest()->paginate(25)]);
    }

    public function editArticle(string $type, ?int $id = null)
    {
        abort_unless(in_array($type, ['kb', 'blog'], true), 404);
        $article = $id ? ($type === 'blog' ? BlogPost::findOrFail($id) : KbArticle::findOrFail($id)) : ($type === 'blog' ? new BlogPost : new KbArticle(['is_published' => true]));

        return view('admin.article-form', ['type' => $type, 'article' => $article]);
    }

    public function saveArticle(Request $request, string $type, ?int $id = null): RedirectResponse
    {
        abort_unless(in_array($type, ['kb', 'blog'], true), 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'category' => [$type === 'kb' ? 'required' : 'nullable', 'string', 'max:100'],
            'excerpt' => ['nullable', 'string', 'max:500'],
        ]);

        $model = $id ? ($type === 'blog' ? BlogPost::findOrFail($id) : KbArticle::findOrFail($id)) : ($type === 'blog' ? new BlogPost : new KbArticle);

        if (! $model->exists) {
            $slug = Str::slug($data['title']) ?: Str::random(8);
            $model->slug = $model->newQuery()->where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
        }

        $type === 'blog'
            ? $model->fill(['title' => $data['title'], 'body' => $data['body'], 'excerpt' => $data['excerpt'] ?? null, 'published_at' => $request->boolean('is_published') ? ($model->published_at ?? now()) : null])
            : $model->fill(['title' => $data['title'], 'body' => $data['body'], 'category' => $data['category'], 'is_published' => $request->boolean('is_published')]);
        $model->save();

        return redirect()->route($type === 'blog' ? 'admin.blog' : 'admin.kb')->with('success', __('Saved.'));
    }

    public function destroyArticle(string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['kb', 'blog'], true), 404);
        ($type === 'blog' ? BlogPost::findOrFail($id) : KbArticle::findOrFail($id))->delete();

        return back()->with('success', __('Deleted.'));
    }

    /* ------------------------------ Support desk ------------------------------ */

    public function tickets(Request $request)
    {
        $tickets = SupportTicket::withoutGlobalScope('tenant')->with(['tenant', 'user'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status), fn ($q) => $q->whereIn('status', ['open', 'pending']))
            ->orderByDesc('last_reply_at')->paginate(25)->withQueryString();

        return view('admin.support.index', ['tickets' => $tickets]);
    }

    public function showTicket(int $ticket)
    {
        return view('admin.support.show', ['ticket' => SupportTicket::withoutGlobalScope('tenant')->with(['tenant', 'user', 'messages.user'])->findOrFail($ticket)]);
    }

    public function replyTicket(Request $request, int $ticket): RedirectResponse
    {
        $ticket = SupportTicket::withoutGlobalScope('tenant')->with('user')->findOrFail($ticket);
        $data = $request->validate(['body' => ['nullable', 'required_without:status', 'string', 'max:10000'], 'status' => ['nullable', Rule::in(['open', 'pending', 'resolved', 'closed'])]]);

        if (filled($data['body'] ?? null)) {
            $ticket->messages()->create(['user_id' => $request->user()->id, 'is_staff' => true, 'body' => $data['body']]);
            $ticket->user->notify(new GenericNotification('system', __('Support replied to: :subject', ['subject' => $ticket->subject]), mb_substr($data['body'], 0, 120), route('support.show', $ticket->id), $ticket->tenant_id));
        }

        $ticket->forceFill(['status' => $data['status'] ?? 'pending', 'last_reply_at' => now()])->save();

        return back()->with('success', __('Ticket updated.'));
    }
}
