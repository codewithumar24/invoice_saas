<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\KbArticle;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/** Marketing website, help centre, legal pages, SEO files and the health check. */
class PublicController extends Controller
{
    public const SOLUTIONS = [
        'freelancers' => ['title' => 'Freelancers', 'headline' => 'Send the invoice, track the hours, know what you earned', 'flow' => ['Customer', 'Quote', 'Invoice', 'Payment', 'Expense', 'Profit'], 'points' => ['Bill tracked time in one click', 'Quotes your client can accept online', 'See who pays late before you start the next job']],
        'agencies' => ['title' => 'Agencies', 'headline' => 'From proposal to profit, with the whole team in one place', 'flow' => ['Lead', 'Customer', 'Proposal', 'Project', 'Milestone', 'Invoice', 'Payment', 'Profit'], 'points' => ['Projects, tasks and time per client', 'Roles and permissions for every team member', 'White-label invoices, emails and client portal']],
        'consultants' => ['title' => 'Consultants', 'headline' => 'Professional invoicing with a clear view of receivables', 'flow' => ['Customer', 'Quote', 'Invoice', 'Payment', 'Reports'], 'points' => ['Recurring invoices for retainers', 'Automatic payment reminders', 'Multi-currency for international clients']],
        'retail' => ['title' => 'Retail', 'headline' => 'Sell, restock and know your margins', 'flow' => ['Product', 'Inventory', 'Customer', 'Sale', 'Invoice', 'Payment', 'Stock', 'Profit'], 'points' => ['Stock per location with low-stock alerts', 'Every sale updates inventory automatically', 'Find low-profit products before they cost you']],
        'service-business' => ['title' => 'Service businesses', 'headline' => 'Jobs done, invoices out, cash collected', 'flow' => ['Customer', 'Service', 'Job', 'Invoice', 'Payment', 'Expense', 'Reports'], 'points' => ['A service catalogue with prices and tax', 'Customers pay online from a secure link', 'Expenses and receipts next to your income']],
    ];

    public const LEGAL = [
        'terms' => 'Terms of Service', 'privacy' => 'Privacy Policy', 'cookies' => 'Cookie Policy', 'refund-policy' => 'Refund Policy',
        'acceptable-use' => 'Acceptable Use Policy', 'data-processing' => 'Data Processing', 'security' => 'Security',
    ];

    public function home()
    {
        return view('public.home', ['plans' => $this->plans()]);
    }

    public function features()
    {
        return view('public.features');
    }

    public function pricing()
    {
        return view('public.pricing', ['plans' => $this->plans()]);
    }

    public function solutions(?string $type = null)
    {
        abort_if($type !== null && ! isset(self::SOLUTIONS[$type]), 404);

        return view('public.solutions', ['solutions' => self::SOLUTIONS, 'current' => $type, 'solution' => $type ? self::SOLUTIONS[$type] : null]);
    }

    public function about()
    {
        return view('public.about');
    }

    public function contact()
    {
        return view('public.contact');
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'message' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'max:0'], // honeypot: real visitors leave it empty
        ]);

        ContactMessage::create(collect($data)->except('website')->all());

        return back()->with('success', __('Thanks. We received your message and will reply by email.'));
    }

    public function blog()
    {
        return view('public.blog', ['posts' => BlogPost::whereNotNull('published_at')->where('published_at', '<=', now())->latest('published_at')->paginate(9)]);
    }

    public function blogPost(string $slug)
    {
        return view('public.blog-post', ['post' => BlogPost::where('slug', $slug)->whereNotNull('published_at')->where('published_at', '<=', now())->firstOrFail()]);
    }

    public function help(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $articles = KbArticle::where('is_published', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $this->like($term))->orWhere('body', 'like', $this->like($term))))
            ->orderBy('category')->orderBy('title')->get();

        return view('public.help', ['groups' => $articles->groupBy('category'), 'term' => $term]);
    }

    public function helpArticle(string $slug)
    {
        $article = KbArticle::where('slug', $slug)->where('is_published', true)->firstOrFail();

        return view('public.help-article', ['article' => $article, 'related' => KbArticle::where('category', $article->category)->where('id', '!=', $article->id)->where('is_published', true)->limit(6)->get()]);
    }

    public function legal(Request $request)
    {
        $page = $request->route()->getName() === 'legal.security' ? 'security' : str_replace('legal.', '', $request->route()->getName());
        abort_unless(isset(self::LEGAL[$page]), 404);

        return view('public.legal', ['page' => $page, 'title' => self::LEGAL[$page]]);
    }

    /** Referral link: remember the code, count the click, continue to registration. */
    public function referral(Request $request, string $code): RedirectResponse
    {
        if ($tenant = Tenant::where('referral_code', strtoupper($code))->first()) {
            $tenant->increment('referral_clicks');
            $request->session()->put('referral_code', $tenant->referral_code);
        }

        return redirect()->route('register');
    }

    public function locale(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('saas.locales')), 404);
        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }

    public function sitemap()
    {
        $urls = collect(['home', 'features', 'pricing', 'solutions', 'about', 'contact', 'blog', 'help'])->map(fn ($name) => route($name))
            ->merge(collect(array_keys(self::SOLUTIONS))->map(fn ($type) => route('solutions', $type)))
            ->merge(collect(array_keys(self::LEGAL))->map(fn ($page) => route('legal.'.$page)))
            ->merge(BlogPost::whereNotNull('published_at')->pluck('slug')->map(fn ($slug) => route('blog.show', $slug)))
            ->merge(KbArticle::where('is_published', true)->pluck('slug')->map(fn ($slug) => route('help.show', $slug)));

        return response()->view('public.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        $lines = ['User-agent: *', 'Disallow: /dashboard', 'Disallow: /admin', 'Disallow: /portal', 'Disallow: /invoice/public', 'Disallow: /quote/public', 'Disallow: /api', '', 'Sitemap: '.route('sitemap')];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }

    /** /health: application, database, cache/Redis and queue. Reports only ok / fail, never details. */
    public function health()
    {
        $checks = ['application' => true];

        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (\Throwable) {
            $checks['database'] = false;
        }

        try {
            Cache::put('health:ping', 1, 10);
            $checks['cache'] = Cache::get('health:ping') === 1;
        } catch (\Throwable) {
            $checks['cache'] = false;
        }

        if (config('queue.default') === 'redis' || config('cache.default') === 'redis') {
            try {
                Redis::connection()->ping();
                $checks['redis'] = true;
            } catch (\Throwable) {
                $checks['redis'] = false;
            }
        }

        try {
            $checks['queue'] = config('queue.default') === 'sync' || DB::table('failed_jobs')->where('failed_at', '>=', now()->subMinutes(10))->count() < 25;
        } catch (\Throwable) {
            $checks['queue'] = false;
        }

        $healthy = ! in_array(false, $checks, true);

        return response()->json(['status' => $healthy ? 'ok' : 'fail', 'checks' => array_map(fn ($ok) => $ok ? 'ok' : 'fail', $checks)], $healthy ? 200 : 503);
    }

    private function plans()
    {
        return Plan::with('features')->where('is_active', true)->where('is_public', true)->orderBy('sort_order')->get();
    }
}
