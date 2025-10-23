<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'extension',
        'locked',
        'metadata',
        'created_by',
        'updated_by',
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array<int, string>
     */
    protected $with = [
        'version',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'url',
        'size',
        'hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locked' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (File $file) {
            if ($file->isForceDeleting()) {
                return;
            }

            $file->deleted_by = Auth::id();
            $file->saveQuietly();
        });
    }

    /**
     * Get the user who uploaded the file.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the file.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deleted the file.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Get all versions of the file.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Version::class)->orderBy('number', 'desc');
    }

    /**
     * Get the latest version of the file.
     */
    public function version(): HasOne
    {
        return $this->hasOne(Version::class)->latestOfMany('number');
    }

    /**
     * Get the comments on the file.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at');
    }

    /**
     * Get the folders containing this file.
     */
    public function folders(): BelongsToMany
    {
        return $this->belongsToMany(Folder::class, 'placements')
            ->using(Placement::class)
            ->orderByPivot('order');
    }

    /**
     * Get the tags applied to this file.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'markings')
            ->using(Marking::class);
    }

    /**
     * Get the placements for this file.
     */
    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    /**
     * Get the markings for this file.
     */
    public function markings(): HasMany
    {
        return $this->hasMany(Marking::class);
    }

    /**
     * Get the accessible URL for the file.
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: function () {
                $version = $this->version;

                if (! $version) {
                    return null;
                }

                return match ($version->disk) {
                    'external' => $version->path,
                    'local' => route('files.preview', $this->id),
                    's3' => Storage::disk('s3')->url($version->path),
                    default => null,
                };
            }
        );
    }

    /**
     * Get the size of the latest version.
     */
    protected function size(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->version?->size
        );
    }

    /**
     * Get the hash of the latest version.
     */
    protected function hash(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->version?->hash
        );
    }
}
