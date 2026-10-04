<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImagesRequest;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ImagesController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission(config('images.permissions')), 403);

        $disk = Storage::disk('images');
        $images = collect($disk->files())
            ->filter(fn (string $path): bool => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), config('images.gallery_extensions')))
            ->map(fn (string $path): array => $this->imageDetails($disk, $path))
            ->sortByDesc('modified')->values();

        return view('admin::settings.images', [
            'images' => $images,
            'maxFileSize' => UploadImagesRequest::maximumSizeKilobytes() * 1024,
            'maxFileSizeLabel' => Number::fileSize(UploadImagesRequest::maximumSizeKilobytes() * 1024),
        ]);
    }

    public function upload(UploadImagesRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $disk = Storage::disk('images');
        $images = [];

        foreach ($validated['images'] as $image) {
            $name = pathinfo($validated['file_name'] ?? $image->getClientOriginalName(), PATHINFO_FILENAME);
            $stem = Str::limit(Str::slug($name) ?: 'image', 180, '');
            $filename = $stem.'-'.Str::lower((string) Str::ulid()).'.'.$image->guessExtension();
            $path = $image->storeAs('', $filename, 'images');
            $images[] = $this->imageDetails($disk, $path);
        }

        $message = count($images) === 1 ? 'Image uploaded successfully.' : count($images).' images uploaded successfully.';

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'images' => $images], 201)
            : redirect()->route('admin.images.index')->with('success', $message);
    }

    /**
     * @return array{name: string, url: string, format: string, size: int, size_label: string, modified: int}
     */
    private function imageDetails(FilesystemAdapter $disk, string $path): array
    {
        $size = $disk->size($path);

        return [
            'name' => basename($path),
            'url' => asset('assets/common/img/'.rawurlencode(basename($path))),
            'format' => strtoupper(pathinfo($path, PATHINFO_EXTENSION)),
            'size' => $size,
            'size_label' => Number::fileSize($size),
            'modified' => $disk->lastModified($path),
        ];
    }
}
