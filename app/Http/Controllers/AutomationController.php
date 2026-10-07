<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Services\AutomationService;
use App\Support\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Automation builder: WHEN (trigger) / IF (conditions) / THEN (actions). */
class AutomationController extends Controller
{
    public function index()
    {
        return view('automation.index', ['rules' => AutomationRule::latest()->paginate($this->perPage), 'triggers' => AutomationService::TRIGGERS]);
    }

    public function create()
    {
        return view('automation.form', ['rule' => new AutomationRule(['is_active' => true, 'conditions' => [], 'actions' => []])] + $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        AutomationRule::create($this->validated($request));

        return redirect()->route('automation.index')->with('success', __('Automation rule created.'));
    }

    public function edit(AutomationRule $automation)
    {
        return view('automation.form', ['rule' => $automation, 'logs' => $automation->logs()->limit(15)->get()] + $this->options());
    }

    public function update(Request $request, AutomationRule $automation): RedirectResponse
    {
        $automation->update($this->validated($request));

        return redirect()->route('automation.index')->with('success', __('Automation rule saved.'));
    }

    public function toggle(AutomationRule $automation): RedirectResponse
    {
        $automation->update(['is_active' => ! $automation->is_active]);

        return back()->with('success', $automation->is_active ? __('Rule switched on.') : __('Rule switched off.'));
    }

    public function destroy(AutomationRule $automation): RedirectResponse
    {
        $automation->delete();

        return redirect()->route('automation.index')->with('success', __('Automation rule deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', Rule::in(array_keys(AutomationService::TRIGGERS))],
            'is_active' => ['nullable', 'boolean'],
            'conditions' => ['nullable', 'array', 'max:10'],
            'conditions.*.field' => ['required_with:conditions', Rule::in(array_keys(AutomationService::FIELDS))],
            'conditions.*.operator' => ['required_with:conditions', Rule::in(array_keys(AutomationService::OPERATORS))],
            'conditions.*.value' => ['required_with:conditions', 'string', 'max:255'],
            'actions' => ['required', 'array', 'min:1', 'max:10'],
            'actions.*.type' => ['required', Rule::in(array_keys(AutomationService::ACTIONS))],
            'actions.*.subject' => ['nullable', 'string', 'max:255'],
            'actions.*.message' => ['nullable', 'string', 'max:2000'],
            'actions.*.tag' => ['nullable', 'string', 'max:50'],
            'actions.*.url' => ['nullable', 'url', 'max:500'],
            'actions.*.email' => ['nullable', 'boolean'],
        ], ['actions.required' => __('Add at least one action.')]);

        foreach ($data['actions'] as $index => $action) {
            if ($action['type'] === 'webhook' && (empty($action['url']) || ! SafeUrl::isPublic($action['url']))) {
                throw ValidationException::withMessages(["actions.{$index}.url" => __('Enter a public http(s) URL for the webhook.')]);
            }

            if ($action['type'] === 'add_tag' && empty($action['tag'])) {
                throw ValidationException::withMessages(["actions.{$index}.tag" => __('Enter the tag to add.')]);
            }

            if ($action['type'] === 'notify_team' && empty($action['message'])) {
                throw ValidationException::withMessages(["actions.{$index}.message" => __('Enter the notification text.')]);
            }
        }

        return [
            'name' => $data['name'], 'trigger' => $data['trigger'], 'is_active' => $request->boolean('is_active'),
            'conditions' => array_values($data['conditions'] ?? []),
            'actions' => array_values(array_map(fn ($action) => array_filter($action, fn ($value) => $value !== null && $value !== ''), $data['actions'])),
        ];
    }

    private function options(): array
    {
        return ['triggers' => AutomationService::TRIGGERS, 'fields' => AutomationService::FIELDS, 'operators' => AutomationService::OPERATORS, 'actionTypes' => AutomationService::ACTIONS];
    }
}
