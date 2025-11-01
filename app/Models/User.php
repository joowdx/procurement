<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUlids, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the folders created by this user.
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class, 'created_by');
    }

    /**
     * Get the files uploaded by this user.
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'created_by');
    }

    /**
     * Get the tags created by this user.
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'created_by');
    }

    /**
     * Get the comments created by this user.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'created_by');
    }

    /**
     * Get the versions created by this user.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Version::class, 'created_by');
    }

    /**
     * Get the workspaces where the user is a member.
     */
    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'memberships')
            ->withPivot('role', 'permissions', 'invited_at', 'invited_by', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Check if user has admin role.
     */
    protected function admin(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->role === 'admin',
        );
    }

    /**
     * Check if user has root role.
     */
    protected function root(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->role === 'root',
        );
    }

    /**
     * Check if user has user role.
     */
    protected function user(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->role === 'user',
        );
    }
}
