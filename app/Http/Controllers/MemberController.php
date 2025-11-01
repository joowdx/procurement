<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\AddWorkspaceMember;
use App\Actions\Workspaces\RemoveWorkspaceMember;
use App\Actions\Workspaces\UpdateMemberPermissions;
use App\Http\Requests\Workspaces\AddMemberRequest;
use App\Http\Requests\Workspaces\RemoveMemberRequest;
use App\Http\Requests\Workspaces\UpdateMemberPermissionsRequest;
use App\Models\User;
use App\Services\WorkspaceAuthorizationService;
use Illuminate\Http\JsonResponse;

class MemberController extends Controller
{
    public function __construct(
        private WorkspaceAuthorizationService $workspaceAuthorizationService,
        private AddWorkspaceMember $addWorkspaceMember,
        private RemoveWorkspaceMember $removeWorkspaceMember,
        private UpdateMemberPermissions $updateMemberPermissions,
    ) {}

    /**
     * Add a member to the current group.
     */
    public function store(AddMemberRequest $request): JsonResponse
    {
        $workspace = $request->current_workspace;

        // Ensure user has permission to add members
        $this->workspaceAuthorizationService->ensureWorkspaceAccess(
            $request->user(),
            $workspace,
            'users'
        );

        $result = $this->addWorkspaceMember->handle(
            $workspace,
            User::findOrFail($request->validated()['user_id']),
            $request->validated()['permissions'] ?? []
        );

        return response()->json([
            'message' => 'Member added successfully',
            'data' => $result,
        ], 201);
    }

    /**
     * Remove a member from the current group.
     */
    public function destroy(User $user, RemoveMemberRequest $request): JsonResponse
    {
        $workspace = $request->current_workspace;

        // Ensure user has permission to remove members
        $this->workspaceAuthorizationService->ensureWorkspaceAccess(
            $request->user(),
            $workspace,
            'users'
        );

        $this->removeWorkspaceMember->handle(
            $workspace,
            $user
        );

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }

    /**
     * Update member permissions in the current group.
     */
    public function update(User $user, UpdateMemberPermissionsRequest $request): JsonResponse
    {
        $workspace = $request->current_workspace;

        // Ensure user has permission to update member permissions
        $this->workspaceAuthorizationService->ensureWorkspaceAccess(
            $request->user(),
            $workspace,
            'users'
        );

        $this->updateMemberPermissions->handle(
            $workspace,
            $user,
            $request->validated()['permissions']
        );

        return response()->json([
            'message' => 'Member permissions updated successfully',
        ]);
    }
}
