<?php

namespace App\Models\Concerns;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasCustomFields
{
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'model');
    }

    /** Save values keyed by custom field id: [fieldId => value]. */
    public function saveCustomFields(?array $values): void
    {
        if ($values === null) {
            return;
        }

        $fields = CustomField::where('entity', static::customFieldEntity())->get()->keyBy('id');

        foreach ($values as $fieldId => $value) {
            if (! $fields->has($fieldId)) {
                continue;
            }

            $this->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $fieldId],
                ['value' => is_array($value) ? json_encode($value) : $value],
            );
        }
    }

    /** [label => value] for display. */
    public function customFieldsForDisplay(bool $pdfOnly = false): array
    {
        $values = $this->customFieldValues->keyBy('custom_field_id');

        return CustomField::where('entity', static::customFieldEntity())
            ->when($pdfOnly, fn ($q) => $q->where('show_on_pdf', true))
            ->orderBy('sort_order')->get()
            ->mapWithKeys(fn ($field) => [$field->label => $values->get($field->id)?->value])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }
}
