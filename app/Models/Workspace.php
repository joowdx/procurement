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

class Workspace extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'settings',
        'active',
        'user_id',
        'updated_by',
        'deactivated_by',
        'deleted_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'active' => 'boolean',
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
        static::deleting(function (Workspace $workspace) {
            if ($workspace->isForceDeleting()) {
                return;
            }

            $workspace->deleted_by = Auth::id();
            $workspace->saveQuietly();

            // Cascade soft delete to folders and files
            $workspace->folders()->update(['deleted_at' => now(), 'deleted_by' => Auth::id()]);
            $workspace->files()->update(['deleted_at' => now(), 'deleted_by' => Auth::id()]);
        });
    }

    /**
     * Get the owner of the workspace.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who last updated the workspace.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deactivated the workspace.
     */
    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * Get the user who deleted the workspace.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Get the users belonging to this workspace.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->using(Membership::class)
            ->withPivot('role', 'permissions', 'invited_at', 'invited_by', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Alias for users relationship for API consistency.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->using(Membership::class)
            ->withPivot('role', 'permissions', 'invited_at', 'invited_by', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get the folders in this workspace.
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * Get the files in this workspace.
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * Scope to filter active workspaces.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
