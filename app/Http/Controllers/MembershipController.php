<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\AddWorkspaceMember;
use App\Actions\Workspaces\RemoveWorkspaceMember;
use App\Actions\Workspaces\UpdateMemberPermissions;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MembershipController extends Controller
{
    public function __construct(
        private WorkspaceAuthorizationService $workspaceAuthorizationService,
        private AddWorkspaceMember $addWorkspaceMember,
        private UpdateMemberPermissions $updateMemberPermissions,
        private RemoveWorkspaceMember $removeWorkspaceMember,
    ) {}

    /**
     * Add member to the current workspace.
     */
    public function store(Request $request)
    {
        $workspace = $request->current_workspace;

        $this->workspaceAuthorizationService->ensureWorkspaceAccess(Auth::user(), $workspace, 'users');

        $validated = $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($workspace) {
                    $exists = Membership::where('workspace_id', $workspace->id)
                        ->where('user_id', $value)
                        ->exists();
                    if ($exists) {
                        $fail('This user is already a member of the workspace.');
                    }
                },
            ],
            'permissions' => 'nullable|array',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $permissions = $validated['permissions'] ?? [];

        $membership = $this->addWorkspaceMember->handle($workspace, $user, $permissions);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Member added successfully.',
                'data' => $membership,
            ], 201);
        }

        return redirect()->back()->with('success', 'Member added successfully.');
    }

    /**
     * Update member permissions.
     */
    public function update(Request $request, Membership $membership)
    {
        $workspace = $request->current_workspace;

        $this->workspaceAuthorizationService->ensureWorkspaceAccess(Auth::user(), $workspace, 'users');

        // Ensure membership belongs to current workspace
        if ($membership->workspace_id !== $workspace->id) {
            abort(403, 'This membership does not belong to the current workspace.');
        }

        $validated = $request->validate([
            'permissions' => 'required|array',
        ]);

        $user = User::findOrFail($membership->user_id);

        $updated = $this->updateMemberPermissions->handle($workspace, $user, $validated['permissions']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Member permissions updated successfully.',
                'data' => $updated,
            ]);
        }

        return redirect()->back()->with('success', 'Member permissions updated successfully.');
    }

    /**
     * Remove member from the current workspace.
     */
    public function destroy(Request $request, Membership $membership)
    {
        $workspace = $request->current_workspace;

        $this->workspaceAuthorizationService->ensureWorkspaceAccess(Auth::user(), $workspace, 'users');

        // Ensure membership belongs to current workspace
        if ($membership->workspace_id !== $workspace->id) {
            abort(403, 'This membership does not belong to the current workspace.');
        }

        $user = User::findOrFail($membership->user_id);

        // Cannot remove workspace owner
        if ($workspace->user_id === $user->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Cannot remove workspace owner.',
                    'errors' => ['user' => ['Cannot remove workspace owner.']],
                ], 422);
            }

            return redirect()->back()->withErrors(['user' => 'Cannot remove workspace owner.']);
        }

        // Cannot remove yourself
        if (Auth::id() === $user->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You cannot remove yourself from the workspace.',
                    'errors' => ['user' => ['You cannot remove yourself from the workspace.']],
                ], 422);
            }

            return redirect()->back()->withErrors(['user' => 'You cannot remove yourself from the workspace.']);
        }

        $this->removeWorkspaceMember->handle($workspace, $user);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Member removed successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Member removed successfully.');
    }

    /**
     * Accept workspace invitation.
     */
    public function accept(Workspace $workspace): RedirectResponse
    {
        $user = Auth::user();

        // Check if user is a member
        $membership = $workspace->users()->wherePivot('user_id', $user->id)->first();

        if (! $membership) {
            return redirect()->back()->withErrors(['error' => 'You are not a member of this workspace.']);
        }

        // Update joined_at if not already set
        if (! $membership->pivot->joined_at) {
            Membership::where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->update(['joined_at' => now()]);
        }

        return redirect()->back()->with('success', 'Invitation accepted successfully.');
    }

    /**
     * Decline workspace invitation.
     */
    public function decline(Workspace $workspace): RedirectResponse
    {
        $user = Auth::user();

        // Remove membership
        $workspace->users()->detach($user->id);

        return redirect()->route('dashboard')->with('success', 'Invitation declined.');
    }

    /**
     * Leave workspace voluntarily.
     */
    public function leave(Workspace $workspace): RedirectResponse
    {
        $user = Auth::user();

        // Cannot leave if owner
        if ($workspace->user_id === $user->id) {
            return redirect()->back()->withErrors(['error' => 'Workspace owner cannot leave the workspace.']);
        }

        // Check if user is a member
        $membership = $workspace->users()->wherePivot('user_id', $user->id)->first();

        if (! $membership) {
            return redirect()->back()->withErrors(['error' => 'You are not a member of this workspace.']);
        }

        // Remove membership
        $workspace->users()->detach($user->id);

        return redirect()->route('dashboard')->with('success', 'You have left the workspace.');
    }
}
