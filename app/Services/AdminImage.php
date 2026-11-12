<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminImage
{
    public const RULES = ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'];

    public function save(Model $record, string $directory, ?UploadedFile $image, callable $apply): void
    {
        $path = $image?->store($directory, 'public');

        if ($path === false) {
            throw ValidationException::withMessages(['avatar' => 'Không lưu được ảnh.']);
        }

        try {
            $previousPath = $record->getConnection()->transaction(function () use ($record, $path, $apply) {
                $target = $record->exists
                    ? $record->newQuery()->lockForUpdate()->findOrFail($record->getKey())
                    : $record;

                $previousPath = $target->getAttribute('avatar_path');
                $apply($target);

                if ($path !== null) {
                    $target->setAttribute('avatar_path', $path);
                }

                $target->save();

                return $previousPath;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        if ($path !== null && $previousPath !== null) {
            Storage::disk('public')->delete($previousPath);
        }
    }
}
