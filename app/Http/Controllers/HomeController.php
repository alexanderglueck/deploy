<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index(Request $request): Response
    {
        $teams = $request->user()->allTeams()->load('projects');

        $projectIds = $teams->flatMap(function ($team) {
            return $team->projects->pluck('id');
        });

        $recentDeployments = Deployment::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('canceled_at')
            ->whereNotNull('deployed_at')
            ->with('project.team')
            ->latest()
            ->limit(5)
            ->get();

        $currentDeployments = Deployment::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('canceled_at')
            ->whereNotNull('processed_at')
            ->whereNull('deployed_at')
            ->with('project.team')
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'teams' => $teams,
            'recentDeployments' => $recentDeployments,
            'currentDeployments' => $currentDeployments,
        ]);
    }
}
