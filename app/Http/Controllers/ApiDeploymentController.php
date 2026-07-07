<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDeployments;
use App\Models\Deployment;
use App\Models\Project;
use App\Support\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ApiDeploymentController extends Controller
{
    public function store(Request $request, Project $project): string
    {
        $data = $request->hasHeader('X-GitHub-Event')
            ? $this->fromGitHub($request, $project)
            : $this->fromGeneric($request, $project);

        if ($data === null) {
            // Acknowledged but nothing to deploy (e.g. GitHub's ping event).
            return 'PONG';
        }

        // Cancel older pending deployments
        Deployment::query()
            ->where([
                'project_id' => $data['project_id'],
                'ref' => $data['ref'],
                'event' => $data['event'],
                'repository' => $data['repository'],
            ])
            ->whereNull('processed_at')
            ->whereNull('deployed_at')
            ->whereNull('canceled_at')
            ->update([
                'canceled_at' => Carbon::now(),
            ]);

        // Queue new pending deployment
        ProcessDeployments::dispatch(Deployment::create($data));

        return 'OK';
    }

    /**
     * Extract deployment data from a native GitHub webhook payload.
     *
     * @return array<string, mixed>|null null when the event only needs an ack
     */
    private function fromGitHub(Request $request, Project $project): ?array
    {
        $githubEvent = $request->header('X-GitHub-Event');

        // GitHub sends `ping` right after a webhook is configured.
        if ($githubEvent === 'ping') {
            return null;
        }

        $event = Event::getEvent($githubEvent);
        abort_if($event === null, 422, "Unsupported event [$githubEvent].");

        $ref = $request->input('ref');
        $repository = $request->input('repository.full_name');
        abort_if(! $ref || ! $repository, 422, 'Payload is missing ref or repository.');

        abort_unless($project->matchesRepository($repository), 422, 'Repository does not match this project.');

        return $this->deploymentData($project, $event, $ref, $repository,
            $request->input('head_commit.id') ?? $request->input('after'));
    }

    /**
     * Extract deployment data from a generic (non-GitHub) trigger.
     *
     * @return array<string, mixed>
     */
    private function fromGeneric(Request $request, Project $project): array
    {
        $validated = $request->validate([
            'event' => 'required',
            'ref' => 'required',
            'repo' => 'required',
        ]);

        $event = Event::getEvent($validated['event']);
        abort_if($event === null, 422, "Unsupported event [{$validated['event']}].");

        abort_unless($project->matchesRepository($validated['repo']), 422, 'Repository does not match this project.');

        return $this->deploymentData($project, $event, $validated['ref'], $validated['repo'], $request->input('sha'));
    }

    /**
     * Assemble (and shape-check) the attributes for a new deployment. These
     * values end up in generated deploy scripts, so they are strictly
     * validated regardless of where the webhook came from.
     *
     * @return array<string, mixed>
     */
    private function deploymentData(Project $project, int $event, string $ref, string $repository, ?string $commitSha): array
    {
        abort_unless(preg_match('#^[\w.-]+/[\w.-]+$#', $repository), 422, 'Invalid repository name.');
        abort_unless(preg_match('#^[\w./-]+$#', $ref), 422, 'Invalid ref.');
        abort_if($commitSha !== null && ! preg_match('/^[0-9a-f]{6,64}$/i', $commitSha), 422, 'Invalid commit SHA.');

        return [
            'project_id' => $project->id,
            'event' => $event,
            'ref' => $ref,
            'repository' => $repository,
            'commit_sha' => $commitSha,
            'received_at' => Carbon::now(),
        ];
    }
}
