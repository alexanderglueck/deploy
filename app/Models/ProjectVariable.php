<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project-scoped environment variable, in the spirit of GitLab's CI/CD
 * variables: defined once on the project, forwarded into every deployment it
 * runs.
 *
 * Values are encrypted at rest and never returned by the API -- responses carry
 * the key and its flags, which is enough to see what a project defines without
 * turning the variable list into a way to read secrets back out.
 */
class ProjectVariable extends Model
{
    use HasFactory;
    use HasPublicUlid;

    /**
     * Keys are written into a shell as `export KEY=...`, so they must be plain
     * identifiers. Enforced by the controllers AND again where the script is
     * generated -- that is the actual trust boundary, and it cannot assume
     * every row reached the table through a validated request.
     */
    public const KEY_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * Names refused because setting them changes how the deployment itself
     * runs, rather than configuring the app being deployed.
     *
     * A deployment step runs as root-equivalent with the Docker socket, so
     * PATH, LD_PRELOAD or BASH_ENV would hijack every command in it, and
     * DOCKER_HOST or
     * DOCKER_CONFIG would point the build and `compose up` at a daemon or
     * registry credentials of someone else's choosing. Anyone who can define a
     * variable can usually also write a script step, so this is not a
     * privilege boundary -- it stops a field that looks like inert config from
     * quietly taking over workflows, and stops the obvious footguns.
     *
     * @var array<int, string>
     */
    public const RESERVED_KEYS = [
        'BASH_ENV', 'CDPATH', 'DOCKER_CERT_PATH', 'DOCKER_CONFIG', 'DOCKER_CONTEXT',
        'DOCKER_HOST', 'DOCKER_TLS_VERIFY', 'ENV', 'GIT_ASKPASS', 'GIT_CONFIG_GLOBAL',
        'GIT_CONFIG_SYSTEM', 'GIT_EXEC_PATH', 'GIT_SSH', 'GIT_SSH_COMMAND', 'HOME',
        'IFS', 'LD_AUDIT', 'LD_LIBRARY_PATH', 'LD_PRELOAD', 'PATH', 'PS4', 'SHELL',
    ];

    /**
     * Whether this key is safe to export into a deployment step.
     */
    public static function keyIsAllowed(string $key): bool
    {
        return preg_match(self::KEY_PATTERN, $key) === 1
            && ! in_array(strtoupper($key), self::RESERVED_KEYS, true);
    }

    protected $fillable = [
        'project_id',
        'key',
        'value',
        'build_arg',
        'masked',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'project_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'build_arg' => 'boolean',
            'masked' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
