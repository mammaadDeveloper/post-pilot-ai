<?php

namespace App\Http\Controllers;

use App\Enums\PublicationTargetStatus;
use App\Models\PublicationTarget;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicationMediaController extends Controller
{
    public function show(PublicationTarget $target): StreamedResponse
    {
        abort_unless(
            $target->status === PublicationTargetStatus::Processing
                && $target->scheduledPost->media_disk !== null
                && $target->scheduledPost->media_path !== null,
            404,
        );

        $disk = Storage::disk($target->scheduledPost->media_disk);
        $stream = $disk->readStream($target->scheduledPost->media_path);

        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $target->scheduledPost->media_mime_type ?? 'application/octet-stream',
            'Content-Length' => (string) $disk->size($target->scheduledPost->media_path),
            'Cache-Control' => 'private, max-age=300, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
