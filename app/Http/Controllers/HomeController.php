<?php

namespace App\Http\Controllers;

use App\Deployment;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        return view('home', [
            'teams' => $request->user()->teams()->with('projects')->get(),
            'recentDeployments' => Deployment::query()->whereIn('project_id',
                $request->user()->teams()->with('projects')->get()->map(function ($team) {
                    return $team->projects->pluck('id');
                })->flatten()
            )->whereNull('canceled_at')
                ->whereNotNull('deployed_at')
                ->latest()->limit(5)->get(),
            'currentDeployments' => Deployment::query()->whereIn('project_id',
                $request->user()->teams()->with('projects')->get()->map(function ($team) {
                    return $team->projects->pluck('id');
                })->flatten()
            )->whereNull('canceled_at')
                ->whereNotNull('processed_at')
                ->whereNull('deployed_at')
                ->latest()->limit(5)->get()
        ]);
    }
}
