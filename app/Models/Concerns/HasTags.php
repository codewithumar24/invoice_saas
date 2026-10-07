<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /** Sync tags from a list of names, creating missing tags in the current workspace. */
    public function syncTagNames(array $names): void
    {
        $ids = collect($names)->map(fn ($name) => trim((string) $name))->filter()->unique()
            ->map(fn ($name) => Tag::firstOrCreate(['name' => $name])->id);

        $this->tags()->sync($ids->all());
    }
}
