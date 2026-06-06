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

        $recentDeployments = Deployment::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('canceled_at')
            ->whereNotNull('deployed_at')
            ->with('project')
            ->latest()
            ->limit(5)
            ->get();

        // In-progress = queued or running (not yet finished, not canceled).
        $currentDeployments = Deployment::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('canceled_at')
            ->whereNull('deployed_at')
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
