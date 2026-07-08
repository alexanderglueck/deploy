<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Show the dashboard for the user's current team.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $projectIds = $team->projects()->pluck('id');

        // Recent = concluded, successfully or not.
        $recentDeployments = Deployment::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('canceled_at')
            ->where(fn ($query) => $query->whereNotNull('deployed_at')->orWhereNotNull('failed_at'))
            ->with('project')
            ->latest()
            ->limit(5)
            ->get();

        // In-progress = queued or running (not finished, failed, or canceled).
        $currentDeployments = Deployment::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('canceled_at')
            ->whereNull('deployed_at')
            ->whereNull('failed_at')
            ->with('project')
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'team' => $team,
            'projects' => $team->projects,
            'recentDeployments' => $recentDeployments,
            'currentDeployments' => $currentDeployments,
        ]);
    }
}
