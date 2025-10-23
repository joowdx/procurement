<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Folder extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'path',
        'depth',
        'order',
        'created_by',
        'updated_by',
        'deactivated_by',
        'deactivated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Folder $folder) {
            $folder->path = static::buildPath($folder);
        });

        static::updating(function (Folder $folder) {
            if ($folder->isDirty(['name', 'parent_id'])) {
                $folder->path = static::buildPath($folder);
            }
        });

        static::deleting(function (Folder $folder) {
            if ($folder->isForceDeleting()) {
                return;
            }

            $folder->deleted_by = Auth::id();
            $folder->saveQuietly();
        });

        static::updated(function (Folder $folder) {
            // If name or parent changed, update all descendants
            if ($folder->wasChanged(['name', 'parent_id'])) {
                static::updateDescendantsPaths($folder);
            }
        });
    }

    /**
     * Build hierarchical path from parent relationship.
     */
    protected static function buildPath(Folder $folder): string
    {
        $parts = [$folder->name];
        $parent = $folder->parent;

        while ($parent) {
            array_unshift($parts, $parent->name);
            $parent = $parent->parent;
        }

        return implode('/', $parts);
    }

    /**
     * Update path for all descendants when folder name changes.
     */
    protected static function updateDescendantsPaths(Folder $folder): void
    {
        $descendants = Folder::where('parent_id', $folder->id)->get();

        foreach ($descendants as $descendant) {
            $descendant->path = static::buildPath($descendant);
            $descendant->saveQuietly();

            // Recursively update their descendants
            static::updateDescendantsPaths($descendant);
        }
    }

    /**
     * Get the parent folder.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * Get the child folders.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('order');
    }

    /**
     * Get all descendant folders recursively.
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the user who created the folder.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the folder.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deactivated the folder.
     */
    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * Get the user who deleted the folder.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Get the files in this folder.
     */
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'placements')
            ->using(Placement::class)
            ->orderByPivot('order');
    }

    /**
     * Get the placements for this folder.
     */
    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }
}
