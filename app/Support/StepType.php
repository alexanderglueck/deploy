<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class StepType
{
    /**
     * Clone the pushed repository, build its Docker image on the target and
     * bring the app's compose stack up.
     */
    const DOCKER_DEPLOY = 'docker_deploy';

    /**
     * Run a shell script stored on the workflow.
     */
    const INLINE_SCRIPT = 'inline_script';

    /**
     * Execute an existing script file on the target.
     */
    const SCRIPT_FILE = 'script_file';

    /**
     * Retag a previously built SHA image back to latest and recreate the
     * stack. Created by the rollback button, not offered in the editor.
     */
    const DOCKER_ROLLBACK = 'docker_rollback';

    /**
     * The types a workflow editor may configure (docker_rollback is internal).
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::DOCKER_DEPLOY,
            self::INLINE_SCRIPT,
            self::SCRIPT_FILE,
        ];
    }

    /**
     * The step types offered in the workflow editor.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return [
            [
                'value' => self::DOCKER_DEPLOY,
                'label' => 'Docker deploy',
                'description' => 'Clone the pushed repository, build its Dockerfile and run docker compose up.',
            ],
            [
                'value' => self::INLINE_SCRIPT,
                'label' => 'Script',
                'description' => 'Run shell commands you define here.',
            ],
            [
                'value' => self::SCRIPT_FILE,
                'label' => 'Script file',
                'description' => 'Execute a script that already exists on the server.',
            ],
        ];
    }

    /**
     * The config keys each type actually reads. Everything else is dropped when
     * a step is saved, so a stale or invented field can never sit in a config
     * looking like it does something.
     *
     * @return array<string, array<int, string>>
     */
    public static function configKeys(): array
    {
        return [
            self::INLINE_SCRIPT => ['script'],
            self::SCRIPT_FILE => ['path', 'args'],
            // docker_deploy needs no config at all: the app name derives from
            // the repository and the compose path from deploy.compose_file_pattern.
            self::DOCKER_DEPLOY => ['app', 'compose_file', 'target'],
        ];
    }

    /**
     * Validation rules for a list of steps, shared by the workflow screens and
     * the management API so the two cannot drift into accepting different
     * configs for the same step type.
     *
     * The caller owns the rule for `$prefix` itself (the editor requires at
     * least one step; a partial API update may omit the key entirely).
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = 'steps'): array
    {
        return [
            $prefix.'.*.type' => ['required', Rule::in(self::all())],
            $prefix.'.*.config' => 'nullable|array',
            $prefix.'.*.config.script' => 'required_if:'.$prefix.'.*.type,'.self::INLINE_SCRIPT.'|nullable|string',
            $prefix.'.*.config.path' => 'required_if:'.$prefix.'.*.type,'.self::SCRIPT_FILE.'|nullable|string',
            $prefix.'.*.config.args' => 'nullable|string',
            $prefix.'.*.config.app' => ['nullable', 'string', 'regex:/^[\w-]+$/'],
            // A filesystem path; flows into generated deploy scripts, so it is
            // shape-checked like the other script-bound fields.
            $prefix.'.*.config.compose_file' => ['nullable', 'string', 'regex:#^[\w./-]+$#'],
            $prefix.'.*.config.target' => ['nullable', 'string', 'regex:/^[\w-]+$/'],
        ];
    }

    public static function label(string $type): string
    {
        foreach (self::options() as $option) {
            if ($option['value'] === $type) {
                return $option['label'];
            }
        }

        return $type;
    }
}
