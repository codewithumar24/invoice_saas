<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use App\Services\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Customer portal. Every query is limited to the signed-in customer, on top of the tenant scope. */
class PortalController extends Controller
{
    private function customer(Request $request): Customer
    {
        return $request->attributes->get('portal_customer');
    }

    public function showLogin()
    {
        return view('portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'portal-login:'.tenant()->id.'|'.mb_strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        $customer = Customer::where('email', $data['email'])->where('portal_enabled', true)->whereNotNull('portal_password')->first();

        if (! $customer || ! Hash::check($data['password'], $customer->portal_password)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('portal', ['tenant_id' => tenant()->id, 'customer_id' => $customer->id]);

        return redirect()->route('portal.dashboard', tenant()->slug);
    }

    /** The invitation link lets the customer choose a password. Only a hash of the token is stored. */
    public function showPassword(string $token)
    {
        abort_unless(Customer::where('portal_invite_token', hash('sha256', $token))->where('portal_enabled', true)->exists(), 404);

        return view('portal.password', ['token' => $token]);
    }

    public function setPassword(Request $request, string $token): RedirectResponse
    {
        $customer = Customer::where('portal_invite_token', hash('sha256', $token))->where('portal_enabled', true)->firstOrFail();
        $data = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);
        $customer->forceFill(['portal_password' => $data['password'], 'portal_invite_token' => null])->save();
        $request->session()->regenerate();
        $request->session()->put('portal', ['tenant_id' => tenant()->id, 'customer_id' => $customer->id]);

        return redirect()->route('portal.dashboard', tenant()->slug)->with('success', __('Your password is set. Welcome.'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('portal');
        $request->session()->regenerate();

        return redirect()->route('portal.login', tenant()->slug);
    }

    public function dashboard(Request $request)
    {
        $customer = $this->customer($request);
        $invoices = $customer->invoices()->where('status', '!=', 'draft')->get();

        return view('portal.dashboard', [
            'outstanding' => $invoices->whereIn('status', Invoice::OPEN)->groupBy('currency')->map(fn ($group) => $group->sum('balance_due')),
            'overdueCount' => $invoices->filter->isOverdue()->count(),
            'openInvoices' => $customer->invoices()->open()->orderBy('due_date')->limit(5)->get(),
            'pendingQuotes' => $customer->quotes()->whereIn('status', ['sent', 'viewed'])->latest()->limit(5)->get(),
        ]);
    }

    public function invoices(Request $request)
    {
        return view('portal.invoices', ['invoices' => $this->customer($request)->invoices()->where('status', '!=', 'draft')->latest('issue_date')->paginate(15)]);
    }

    public function quotes(Request $request)
    {
        return view('portal.quotes', ['quotes' => $this->customer($request)->quotes()->where('status', '!=', 'draft')->latest('issue_date')->paginate(15)]);
    }

    public function quoteRespond(Request $request, int $quote, QuoteService $quotes): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', 'in:accept,reject']]);
        $quote = $this->customer($request)->quotes()->where('status', '!=', 'draft')->findOrFail($quote);
        $data['response'] === 'accept' ? $quotes->accept($quote) : $quotes->reject($quote);

        return back()->with('success', $data['response'] === 'accept' ? __('Quote accepted.') : __('Quote declined.'));
    }

    public function payments(Request $request)
    {
        return view('portal.payments', ['payments' => $this->customer($request)->payments()->with('invoices')->latest('paid_at')->paginate(15)]);
    }

    public function documents(Request $request)
    {
        $customer = $this->customer($request);

        return view('portal.documents', ['documents' => $this->visibleDocuments($customer)->latest()->paginate(20)]);
    }

    public function download(Request $request, int $document, FileStorageService $files)
    {
        $document = $this->visibleDocuments($this->customer($request))->findOrFail($document);

        return $files->download($document->disk, $document->path, $document->original_name);
    }

    /** Documents shared with this customer: their own files and the PDFs of their own invoices. */
    private function visibleDocuments(Customer $customer)
    {
        $invoiceIds = $customer->invoices()->where('status', '!=', 'draft')->pluck('id');

        return Attachment::where('visible_to_customer', true)->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('attachable_type', $customer->getMorphClass())->where('attachable_id', $customer->id))
            ->orWhere(fn ($q) => $q->where('attachable_type', (new Invoice)->getMorphClass())->whereIn('attachable_id', $invoiceIds)));
    }

    public function profile(Request $request)
    {
        return view('portal.profile', ['customer' => $this->customer($request)->load('addresses')]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'company' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'],
            'address.line1' => ['nullable', 'string', 'max:255'], 'address.city' => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'max:100'], 'address.postal_code' => ['nullable', 'string', 'max:30'], 'address.country' => ['nullable', 'string', 'size:2'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $customer->update(collect($data)->only(['name', 'company', 'phone'])->all());
        $customer->addresses()->updateOrCreate(['type' => 'billing'], $data['address'] ?? []);

        if (! empty($data['password'])) {
            $customer->forceFill(['portal_password' => $data['password']])->save();
        }

        return back()->with('success', __('Profile saved.'));
    }

    public function messages(Request $request)
    {
        $customer = $this->customer($request);
        $customer->messages()->where('from_customer', false)->whereNull('read_at')->update(['read_at' => now()]);

        return view('portal.messages', ['messages' => $customer->messages()->with('user')->oldest()->limit(200)->get()]);
    }

    public function sendMessage(Request $request, NotificationService $notifications): RedirectResponse
    {
        $customer = $this->customer($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $customer->messages()->create(['body' => $data['body'], 'from_customer' => true]);
        $notifications->team('system', __('New message from :customer', ['customer' => $customer->name]), mb_substr($data['body'], 0, 120), route('customers.show', $customer), 'customers.view');

        return back()->with('success', __('Message sent.'));
    }
}
