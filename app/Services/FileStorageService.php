<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only place that knows where files live. Controllers never build storage paths themselves.
 * Private files go to the default disk (local or S3-compatible); public images go to the "public" disk.
 */
class FileStorageService
{
    public function __construct(private UsageService $usage, private FeatureService $features) {}

    public function privateDisk(): string
    {
        return config('filesystems.default', 'local');
    }

    /** Validation rule for user uploads (type is checked from the file content, not the client). */
    public static function rule(?array $extensions = null): array
    {
        return ['file', 'max:'.config('saas.upload.max_kb'), 'mimes:'.implode(',', $extensions ?? config('saas.upload.mimes'))];
    }

    /**
     * Store a private upload under a generated name and return its details.
     *
     * @return array{disk:string,path:string,original_name:string,mime:?string,size:int}
     */
    public function store(UploadedFile $file, string $directory): array
    {
        $tenantId = tenant()?->id ?? 0;
        $this->guardStorageLimit($file->getSize());

        // Extension comes from the detected MIME type; the client file name is never used for the stored path
        $extension = $file->guessExtension() ?: 'bin';
        $path = $file->storeAs("tenants/{$tenantId}/{$directory}", Str::uuid().'.'.$extension, $this->privateDisk());
        $this->usage->increment('storage_bytes', (int) $file->getSize());

        return [
            'disk' => $this->privateDisk(),
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ];
    }

    /** Store raw content (generated PDFs, exports). */
    public function put(string $directory, string $filename, string $contents): array
    {
        $tenantId = tenant()?->id ?? 0;
        $path = "tenants/{$tenantId}/{$directory}/".Str::uuid().'-'.Str::slug(pathinfo($filename, PATHINFO_FILENAME)).'.'.pathinfo($filename, PATHINFO_EXTENSION);
        Storage::disk($this->privateDisk())->put($path, $contents);

        return ['disk' => $this->privateDisk(), 'path' => $path, 'original_name' => $filename, 'mime' => null, 'size' => strlen($contents)];
    }

    /** Store a public image (logo, product image) and return its path on the public disk. */
    public function storePublicImage(UploadedFile $file, string $directory): string
    {
        $tenantId = tenant()?->id ?? 0;

        return $file->storeAs("tenants/{$tenantId}/{$directory}", Str::uuid().'.'.($file->guessExtension() ?: 'png'), 'public');
    }

    public function attach(UploadedFile $file, ?Model $owner, string $category, bool $visibleToCustomer = false): Attachment
    {
        return Attachment::create($this->store($file, $category) + [
            'attachable_type' => $owner?->getMorphClass(),
            'attachable_id' => $owner?->getKey(),
            'category' => $category,
            'visible_to_customer' => $visibleToCustomer,
            'uploaded_by' => auth()->id(),
        ]);
    }

    public function download(string $disk, string $path, string $name): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $name);
    }

    public function delete(string $disk, ?string $path): void
    {
        if ($path) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function publicUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** Base64 data URI of a public image, for embedding in PDFs. */
    public function dataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode(Storage::disk('public')->get($path));
    }

    private function guardStorageLimit(int $bytes): void
    {
        $limitMb = $this->features->limit('max_storage');

        if ($limitMb !== null && ($this->usage->total('storage_bytes') + $bytes) > $limitMb * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => __('Your plan storage limit of :limit MB is reached.', ['limit' => $limitMb])]);
        }
    }
}
