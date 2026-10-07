<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\CustomField;
use App\Models\Tag;
use App\Services\CustomerService;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $customers) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Customer::class);

        $query = Customer::with('tags')
            ->where('status', $request->query('status') === 'archived' ? 'archived' : 'active')
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', $this->like($term))
                ->orWhere('email', 'like', $this->like($term))->orWhere('company', 'like', $this->like($term))->orWhere('phone', 'like', $this->like($term))))
            ->when($request->query('tag'), fn ($q, $tag) => $q->whereHas('tags', fn ($q) => $q->where('tags.id', $tag)))
            ->when($request->query('balance') === 'owing', fn ($q) => $q->where('balance', '>', 0));

        return view('customers.index', [
            'customers' => $this->sorted($query, $request, ['name', 'balance', 'created_at', 'last_activity_at'], 'name', 'asc')->paginate($this->perPage)->withQueryString(),
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Customer::class);

        return view('customers.form', ['customer' => new Customer(), 'customFields' => CustomField::where('entity', 'customer')->orderBy('sort_order')->get()]);
    }

    public function store(StoreCustomerRequest $request)
    {
        Gate::authorize('create', Customer::class);
        $customer = $this->customers->create($request->validated());

        // Created from the invoice form's "new customer" dialog
        if ($request->expectsJson()) {
            return $this->ok(['id' => $customer->id, 'name' => $customer->displayName(), 'currency' => $customer->currency, 'payment_terms_days' => $customer->payment_terms_days], __('Customer created.'), 201);
        }

        return redirect()->route('customers.show', $customer)->with('success', __('Customer created.'));
    }

    public function show(Customer $customer)
    {
        Gate::authorize('view', $customer);
        $customer->load(['addresses', 'contacts', 'tags', 'customerNotes.user', 'attachments', 'customFieldValues']);

        $invoices = $customer->invoices()->whereNotIn('status', ['draft', 'cancelled'])->get();

        return view('customers.show', [
            'customer' => $customer,
            'stats' => [
                'revenue' => $invoices->sum(fn ($i) => (float) $i->total * (float) $i->exchange_rate),
                'paid' => $invoices->sum(fn ($i) => (float) $i->amount_paid * (float) $i->exchange_rate),
                'outstanding' => (float) $customer->balance,
                'overdue' => $invoices->filter->isOverdue()->sum(fn ($i) => (float) $i->balance_due * (float) $i->exchange_rate),
                'last_transaction' => $customer->payments()->latest('paid_at')->first(),
            ],
            'behavior' => $this->customers->paymentBehavior($customer),
            'invoices' => $customer->invoices()->latest('issue_date')->limit(10)->get(),
            'quotes' => $customer->quotes()->latest('issue_date')->limit(10)->get(),
            'payments' => $customer->payments()->latest('paid_at')->limit(10)->get(),
            'timeline' => $customer->activities()->with('user')->limit(30)->get(),
            'messages' => $customer->messages()->with('user')->latest()->limit(20)->get()->reverse(),
        ]);
    }

    public function edit(Customer $customer)
    {
        Gate::authorize('update', $customer);
        $customer->load(['addresses', 'tags', 'customFieldValues']);

        return view('customers.form', ['customer' => $customer, 'customFields' => CustomField::where('entity', 'customer')->orderBy('sort_order')->get()]);
    }

    public function update(StoreCustomerRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $this->customers->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', __('Customer saved.'));
    }

    /** Customers with financial history are archived, never destroyed. */
    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        if ($customer->invoices()->exists() || $customer->payments()->exists()) {
            $customer->update(['status' => 'archived']);

            return redirect()->route('customers.index')->with('success', __('This customer has invoices, so it was archived instead of deleted.'));
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', __('Customer deleted.'));
    }

    public function archive(Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $customer->update(['status' => $customer->status === 'archived' ? 'active' : 'archived']);

        return back()->with('success', $customer->status === 'archived' ? __('Customer archived.') : __('Customer restored.'));
    }

    public function storeNote(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $customer->customerNotes()->create(['body' => $data['body'], 'user_id' => $request->user()->id]);
        app(\App\Services\ActivityService::class)->log('customer.note', __('Note added'), $customer, $customer->id);

        return back()->with('success', __('Note added.'));
    }

    public function storeMessage(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $customer->messages()->create(['body' => $data['body'], 'user_id' => $request->user()->id, 'from_customer' => false]);

        return back()->with('success', __('Message sent to the customer portal.'));
    }

    public function storeDocument(Request $request, Customer $customer, FileStorageService $files): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $request->validate(['file' => array_merge(['required'], FileStorageService::rule())]);
        $files->attach($request->file('file'), $customer, 'customer_files', $request->boolean('visible_to_customer'));
        app(\App\Services\ActivityService::class)->log('customer.document', __('Document uploaded'), $customer, $customer->id);

        return back()->with('success', __('Document uploaded.'));
    }

    /** Open (or re-send) the customer's portal account. */
    public function invitePortal(Customer $customer, NotificationService $notifications): RedirectResponse
    {
        Gate::authorize('update', $customer);
        abort_unless(feature('customer_portal'), 403);

        if (! $customer->email) {
            return back()->with('error', __('Add an email address before inviting this customer to the portal.'));
        }

        $token = Str::random(64);
        $customer->forceFill(['portal_enabled' => true, 'portal_invite_token' => hash('sha256', $token)])->save();
        $url = route('portal.password', [tenant()->slug, $token]);
        $notifications->email($customer->email, 'portal_invitation', ['customer_name' => $customer->name, 'action_url' => $url], [], $url, __('Set your password'));

        return back()->with('success', __('Portal invitation sent.'));
    }

    public function disablePortal(Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $customer->forceFill(['portal_enabled' => false, 'portal_invite_token' => null])->save();

        return back()->with('success', __('Portal access removed.'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:archive,export,tag'],
            'ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer'],
            'tag' => ['nullable', 'required_if:action,tag', 'string', 'max:50'],
        ]);

        if ($data['action'] === 'export') {
            Gate::authorize('reports.export');

            return redirect()->route('exports.download-now', ['type' => 'customers', 'format' => 'csv', 'ids' => $data['ids']]);
        }

        Gate::authorize('customers.update');
        $customers = Customer::whereIn('id', $data['ids'])->get();

        foreach ($customers as $customer) {
            if ($data['action'] === 'archive') {
                $customer->update(['status' => 'archived']);
            } else {
                $customer->tags()->syncWithoutDetaching([Tag::firstOrCreate(['name' => trim($data['tag'])])->id]);
            }
        }

        return back()->with('success', trans_choice(':count customer updated.|:count customers updated.', $customers->count(), ['count' => $customers->count()]));
    }
}
