<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates webhook calls against the project's secret. GitHub signs the
 * raw payload (X-Hub-Signature-256); other callers may send the secret itself
 * in an X-Deploy-Secret header. The deploy endpoint in the URL is random but
 * is deliberately not treated as a credential.
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Project $project */
        $project = $request->route('project');

        $secret = $project->webhook_secret;

        abort_if(! $secret, 403, 'This project has no webhook secret.');

        // GitHub: HMAC of the raw body, prefixed with the algorithm.
        if ($signature = $request->header('X-Hub-Signature-256')) {
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

            abort_unless(hash_equals($expected, $signature), 403, 'Invalid webhook signature.');

            return $next($request);
        }

        // Gitea: same HMAC, bare hex without the prefix.
        if ($signature = $request->header('X-Gitea-Signature')) {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);

            abort_unless(hash_equals($expected, $signature), 403, 'Invalid webhook signature.');

            return $next($request);
        }

        // GitLab sends the secret itself; so do generic callers.
        $token = $request->header('X-Gitlab-Token') ?? $request->header('X-Deploy-Secret');

        if ($token !== null) {
            abort_unless(hash_equals($secret, $token), 403, 'Invalid webhook secret.');

            return $next($request);
        }

        abort(403, 'Missing webhook signature.');
    }
}
