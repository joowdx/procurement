<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RequireWorkspace
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Check if group is already selected in session
        $currentWorkspaceId = session('current_workspace_id');

        if ($currentWorkspaceId) {
            $workspace = Workspace::find($currentWorkspaceId);

            // Verify user still has access to this group
            if ($workspace && $this->userHasAccess($user, $workspace)) {
                $request->merge(['current_workspace' => $workspace]);

                return $next($request);
            }
        }

        // Auto-select group if none selected or current selection is invalid
        $workspace = $this->autoSelectWorkspace($user);

        if (! $workspace) {
            // User has no workspaces - show invitation page
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No workspaces available'], 404);
            }

            return Inertia::render('invitation')->toResponse($request);
        }

        // Store selected workspace in session
        session(['current_workspace_id' => $workspace->id]);
        $request->merge(['current_workspace' => $workspace]);

        return $next($request);
    }

    /**
     * Check if user has access to the group.
     */
    private function userHasAccess($user, Workspace $workspace): bool
    {
        // Workspace owner always has access
        if ($workspace->user_id === $user->id) {
            return true;
        }

        // Root users have access to all workspaces
        if ($user->role === 'root') {
            return true;
        }

        // Check if user is a member
        return $user->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    /**
     * Auto-select a group for the user.
     */
    private function autoSelectWorkspace($user): ?Workspace
    {
        // Priority 1: Oldest owned group
        $ownedWorkspace = Workspace::where('user_id', $user->id)
            ->oldest()
            ->first();

        if ($ownedWorkspace) {
            return $ownedWorkspace;
        }

        // Priority 2: Oldest group where user is a member
        $memberWorkspace = $user->workspaces()
            ->oldest('memberships.joined_at')
            ->first();

        return $memberWorkspace;
    }
}
