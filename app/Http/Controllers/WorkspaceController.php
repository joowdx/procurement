<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DelegateWorkspaceOwnership;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Repositories\WorkspaceRepository;
use App\Services\WorkspaceAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(
        private WorkspaceAuthorizationService $workspaceAuthorizationService,
        private WorkspaceRepository $workspaceRepository,
        private CreateWorkspace $createWorkspace,
        private UpdateWorkspace $updateWorkspace,
        private DelegateWorkspaceOwnership $delegateWorkspaceOwnership,
    ) {}

    /**
     * Store a newly created workspace.
     */
    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $this->createWorkspace->handle($request->validated(), Auth::user());

        // Set the newly created workspace as current workspace
        Session::put('current_workspace_id', $workspace->id);

        return redirect()->route('workspace.edit')
            ->with('success', 'Workspace created successfully.');
    }

    /**
     * Select a workspace as current workspace.
     */
    public function select(Workspace $workspace): JsonResponse
    {
        $this->workspaceAuthorizationService->ensureWorkspaceAccess(Auth::user(), $workspace);

        Session::put('current_workspace_id', $workspace->id);

        return response()->json([
            'message' => 'Workspace selected successfully.',
            'workspace' => $workspace,
        ]);
    }

    /**
     * Show the edit page for the current workspace.
     */
    public function edit(Request $request): Response|JsonResponse
    {
        $workspace = $request->current_workspace;

        // Check if user has settings permission
        $this->workspaceAuthorizationService->ensureWorkspaceAccess(Auth::user(), $workspace, 'settings');

        $workspace = $this->workspaceRepository->getWorkspaceWithMembers($workspace);
        $stats = $this->workspaceRepository->getWorkspaceStats($workspace);

        // Return JSON for API requests
        if ($request->expectsJson()) {
            return response()->json([
                'workspace' => (new WorkspaceResource($workspace))->resolve(),
                'stats' => $stats,
            ]);
        }

        return Inertia::render('workspaces/edit', [
            'workspace' => (new WorkspaceResource($workspace))->resolve(),
            'stats' => $stats,
        ]);
    }

    /**
     * Update the current workspace.
     */
    public function update(UpdateWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $request->current_workspace;

        $this->workspaceAuthorizationService->ensureWorkspaceAccess(Auth::user(), $workspace, 'settings');

        $data = $request->validated();

        $isDelegation = false;

        // Handle delegation if new_owner_id is provided
        if (isset($data['new_owner_id'])) {
            $newOwner = \App\Models\User::findOrFail($data['new_owner_id']);
            $this->delegateWorkspaceOwnership->handle($workspace, $newOwner);
            $isDelegation = true;

            // Remove delegation data from update data
            unset($data['new_owner_id']);
        }

        // Update workspace with remaining data
        if (! empty($data)) {
            $this->updateWorkspace->handle($workspace, $data, Auth::user());
        }

        $message = $isDelegation ? 'Workspace ownership delegated successfully.' : 'Workspace updated successfully.';

        return redirect()->back()->with('success', $message);
    }

    /**
     * Delete the current workspace.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $workspace = $request->current_workspace;

        // Only workspace owner can delete (not even root users)
        if ($workspace->user_id !== Auth::id()) {
            abort(403, 'Only the workspace owner can delete it.');
        }

        $workspace->delete();

        // Clear the current workspace from session
        Session::forget('current_workspace_id');

        return redirect()->route('dashboard')
            ->with('success', 'Workspace deleted successfully.');
    }
}
