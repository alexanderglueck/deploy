<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only viewer for deploy logs written by an external hook (e.g. the
 * adnanh webhook), so those deploys get some UI too during the migration.
 */
class LegacyLogController extends Controller
{
    /**
     * Show at most this much of a log's tail.
     */
    private const TAIL_BYTES = 512 * 1024;

    public function index(Request $request): Response
    {
        $this->currentTeam($request);
        $path = $this->basePath();

        $logs = collect(glob($path.'/*.log') ?: [])
            ->map(fn (string $file) => [
                'name' => basename($file),
                'size' => filesize($file),
                'modified_at' => date('c', filemtime($file)),
            ])
            ->sortByDesc('modified_at')
            ->values();

        return Inertia::render('LegacyLog/Index', [
            'logs' => $logs,
        ]);
    }

    public function show(Request $request, string $file): Response
    {
        $this->currentTeam($request);
        $path = $this->basePath();

        // The route constraint already restricts the shape; belt & braces
        // against traversal before touching the filesystem.
        abort_unless(preg_match('/^[\w][\w.-]*\.log$/', $file), 404);

        $fullPath = realpath($path.'/'.$file);
        abort_unless($fullPath && str_starts_with($fullPath, $path.'/') && is_file($fullPath), 404);

        $size = filesize($fullPath);
        $truncated = $size > self::TAIL_BYTES;
        $handle = fopen($fullPath, 'r');
        if ($truncated) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
        }
        $content = stream_get_contents($handle);
        fclose($handle);

        return Inertia::render('LegacyLog/Show', [
            'name' => $file,
            'content' => $content,
            'truncated' => $truncated,
            'modifiedAt' => date('c', filemtime($fullPath)),
        ]);
    }

    private function basePath(): string
    {
        $path = config('deploy.legacy_logs_path');

        abort_unless($path && is_dir($path), 404);

        return rtrim(realpath($path), '/');
    }
}
