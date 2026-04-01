<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Avatar {
    // Uploads file to supabase then returns url
    public function upload(UploadedFile $file, string $userId): string {
        $extension = $file->getClientOriginalExtenstion();
        $filename = "mentor-{$userId}-" . Str::uuid() . ".{$extension}";

        // Grab data of image and post into supabase bucket
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.supabase.key'),
            'Content-Type' => $file->getMimeType(),
        ])->withBody(
            file_get_contents($file->getRealPath()),
            $file->getMimeType()
        )->post(
            config('services.supabase.storage_url') . "/object/mentor-avatars/{$filename}"
        );
        // Return error if upload failed
        if ($response->failed()) {
            throw new \RuntimeException('Upload failed: ' . $response->body());
        }
        // Return URL of image in bucket
        return config('services.supabase.storage_url') . "/object/public/mentor-avatars/{$filename}";
    }

    // Delete old pictures if replaced
    public function delete(string $avatarUrl): void {
        // Get filename from URL
        $filename = basename(parse_url($avatarUrl, PHP_URL_PATH));
        Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.supabase.key'),
        ])->delete(
            config('services.supabase.storage_url') . "/object/mentor-avatars/{$filename}"
        );
    }

    // Generate placeholder avatar for no avatar users
    public function placeholder(string $name): string {
        $initials = collect(explode(' ', $name))->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(1);

        // API key for initials avatar
        return "https://api.dicebear.com/8.x/initials/svg?seed={$initials}&backgroundColor=1a3c2f&textColor=fffffa";
    }
}