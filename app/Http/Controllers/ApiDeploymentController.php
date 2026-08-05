<?php

namespace App\Http\Controllers;

use App\Actions\TriggerDeployment;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\Workflow;
use App\Support\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ApiDeploymentController extends Controller
{
    public function store(Request $request, Project $project, TriggerDeployment $trigger): string
    {
        $data = match (true) {
            $request->hasHeader('X-Gitlab-Event') => $this->fromGitLab($request, $project),
            // Gitea sends GitHub-compatible headers and payloads.
            $request->hasHeader('X-GitHub-Event') => $this->fromGitHub($request, $project),
            default => $this->fromGeneric($request, $project),
        };

        if ($data === null) {
            // Acknowledged but nothing to deploy (e.g. GitHub's ping event).
            return 'PONG';
        }

        // Pushes no workflow cares about (e.g. feature branches) are ignored
        // instead of piling up failed deployments for every push.
        $probe = new Deployment($data);
        $probe->setRelation('project', $project);

        $matches = $project->workflows()
            ->where('event', $data['event'])
            ->get()
            ->contains(fn (Workflow $workflow) => $workflow->matchesBranch($probe));

        if (! $matches) {
            return 'IGNORED';
        }

        // Superseding pending work, creating the deployment and queueing it lives
        // in TriggerDeployment so the management API behaves identically.
        $trigger($data);

        return 'OK';
    }

    /**
     * Extract deployment data from a native GitHub (or Gitea) webhook payload.
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
            $request->input('head_commit.id') ?? $request->input('after'),
            $request->input('repository.default_branch'));
    }

    /**
     * Extract deployment data from a GitLab webhook payload.
     *
     * @return array<string, mixed>|null
     */
    private function fromGitLab(Request $request, Project $project): ?array
    {
        $gitlabEvent = $request->header('X-Gitlab-Event');

        abort_unless($gitlabEvent === 'Push Hook', 422, "Unsupported event [$gitlabEvent].");

        $ref = $request->input('ref');
        $repository = $request->input('project.path_with_namespace');
        abort_if(! $ref || ! $repository, 422, 'Payload is missing ref or repository.');

        abort_unless($project->matchesRepository($repository), 422, 'Repository does not match this project.');

        return $this->deploymentData($project, Event::PUSH, $ref, $repository,
            $request->input('checkout_sha') ?? $request->input('after'),
            $request->input('project.default_branch'));
    }

    /**
     * Extract deployment data from a generic (non-forge) trigger.
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

        return $this->deploymentData($project, $event, $validated['ref'], $validated['repo'],
            $request->input('sha'), $request->input('default_branch'));
    }

    /**
     * Assemble (and shape-check) the attributes for a new deployment. These
     * values end up in generated deploy scripts, so they are strictly
     * validated regardless of where the webhook came from.
     *
     * @return array<string, mixed>
     */
    private function deploymentData(Project $project, int $event, string $ref, string $repository, ?string $commitSha, ?string $defaultBranch = null): array
    {
        abort_unless(preg_match('#^[\w.-]+/[\w.-]+$#', $repository), 422, 'Invalid repository name.');
        abort_unless(preg_match('#^[\w./-]+$#', $ref), 422, 'Invalid ref.');
        abort_if($commitSha !== null && ! preg_match('/^[0-9a-f]{6,64}$/i', $commitSha), 422, 'Invalid commit SHA.');
        abort_if($defaultBranch !== null && ! preg_match('#^[\w./-]+$#', $defaultBranch), 422, 'Invalid default branch.');

        return [
            'project_id' => $project->id,
            'event' => $event,
            'ref' => $ref,
            'default_branch' => $defaultBranch,
            'repository' => $repository,
            'commit_sha' => $commitSha,
            'received_at' => Carbon::now(),
        ];
    }
}
