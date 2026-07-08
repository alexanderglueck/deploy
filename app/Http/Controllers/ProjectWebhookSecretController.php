<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectWebhookSecretController extends Controller
{
    /**
     * Rotate the project's webhook secret. Existing webhooks keep failing
     * with 403 until they are updated with the new secret.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        // Not mass-assignable by design; set explicitly.
        $project->webhook_secret = Str::random(40);
        $project->save();

        return redirect()->route('project.show', $project)
            ->banner('Webhook secret rotated. Update it wherever the webhook is configured.');
    }
}
