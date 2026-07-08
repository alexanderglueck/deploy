<?php

namespace App\Support;

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
