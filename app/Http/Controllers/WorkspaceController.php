<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A user can own or belong to several separate workspaces and switch between them. */
class WorkspaceController extends Controller
{
    public function create()
    {
        return view('workspaces.create', ['timezones' => timezone_identifiers_list(), 'countries' => config('countries')]);
    }

    public function store(Request $request, TenantService $tenants): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2', Rule::in(array_keys(config('countries')))],
            'timezone' => ['required', 'timezone'],
        ]);

        $tenants->create($request->user(), $data);

        return redirect()->route('onboarding.show');
    }

    public function switch(Request $request, Tenant $tenant): RedirectResponse
    {
        // Membership is checked here; SetTenant checks it again on every request
        abort_unless($request->user()->tenants()->where('tenants.id', $tenant->id)->wherePivot('status', 'active')->exists(), 403);
        $request->user()->forceFill(['current_tenant_id' => $tenant->id])->save();

        return redirect()->route('dashboard');
    }
}
