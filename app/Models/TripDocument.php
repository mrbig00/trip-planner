<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TripDocument extends Model
{
    /** @use HasFactory<\Database\Factories\TripDocumentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'user_id',
        'title',
        'description',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Delete the underlying stored file whenever the row goes away, so
        // uploads never outlive the record that points at them.
        static::deleting(function (TripDocument $document) {
            Storage::disk($document->disk)->delete($document->path);
        });
    }

    /**
     * Get the trip this document belongs to.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Get the user who uploaded this document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the file size formatted for display (e.g. "1.2 MB").
     *
     * Deliberately hand-rolled rather than Illuminate\Support\Number::fileSize():
     * that helper requires the "intl" PHP extension, which neither
     * production.Dockerfile nor the plain Dockerfile installs — using it
     * here would throw on every document render outside of Sail (which does
     * have intl).
     */
    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) (int) $size : number_format($size, 1)).' '.$units[$unit];
    }

    /**
     * Get the Flux icon name to display for this document's file type.
     */
    public function icon(): string
    {
        return match (true) {
            $this->mime_type === 'application/pdf' => 'document-text',
            str_starts_with($this->mime_type, 'image/') => 'photo',
            in_array($this->mime_type, [
                'application/zip',
                'application/x-zip-compressed',
            ], true) => 'archive-box',
            default => 'paper-clip',
        };
    }
}
