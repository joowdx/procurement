<?php

namespace App\Http\Middleware;

use App\Services\WorkspaceAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    public function __construct(
        private WorkspaceAuthorizationService $workspaceAuthorizationService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $workspace = $request->route('group');

        if (! $workspace) {
            return $next($request);
        }

        $this->groupAuthorizationService->ensureWorkspaceAccess(
            $request->user(),
            $workspace,
            $permission
        );

        return $next($request);
    }
}
