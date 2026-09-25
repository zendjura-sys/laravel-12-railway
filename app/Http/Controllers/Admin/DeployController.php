<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Кнопка "Задеплоїти" в адмінці НІКОЛИ не виконує деплой сама — сайт працює
 * під www-data і root не отримує. Вона лише лишає мітку (trigger-файл),
 * яку на сервері підхоплює systemd-path юніт і сам запускає
 * deploy/setup-vps.sh від root. Див. deploy/systemd/README.md для
 * одноразового налаштування цього юніта на сервері.
 */
class DeployController extends Controller
{
    public function trigger(Request $request): JsonResponse
    {
        $dir = storage_path('app/deploy');
        File::ensureDirectoryExists($dir);

        $current = $this->readStatus($dir);
        if (in_array($current['status'] ?? 'idle', ['pending', 'running'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Деплой вже виконується.',
                'data' => null,
                'errors' => null,
                'redirect' => null,
            ], 422);
        }

        file_put_contents($dir.'/status.json', json_encode([
            'status' => 'pending',
            'started_at' => null,
            'finished_at' => null,
            'exit_code' => null,
            'requested_by' => $request->user()->name,
            'requested_at' => now()->toIso8601String(),
        ]));
        // file_put_contents (не touch!) завжди робить справжній
        // open+write+close — systemd-юніт monsory-deploy.path чекає саме
        // на PathChanged (IN_CLOSE_WRITE), а touch() на вже існуючий файл
        // лише міняє mtime через utime() без запису, подія не спрацює.
        file_put_contents($dir.'/trigger', now()->toIso8601String());

        return response()->json([
            'ok' => true,
            'message' => 'Деплой заплановано. Зазвичай займає 1–3 хвилини.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function status(): JsonResponse
    {
        $dir = storage_path('app/deploy');
        $status = $this->readStatus($dir);

        $logPath = $dir.'/log.txt';
        $log = '';
        if (File::exists($logPath)) {
            $lines = file($logPath) ?: [];
            $log = implode('', array_slice($lines, -80));
            // Прибираємо ANSI-коди кольору з log() /warn()/die() у самому
            // скрипті — у браузері вони показались би сирими escape-послідовностями.
            $log = preg_replace('/\x1B\[[0-9;]*m/', '', $log);
        }

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['status' => $status, 'log' => $log],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    private function readStatus(string $dir): array
    {
        $path = $dir.'/status.json';
        if (! File::exists($path)) {
            return ['status' => 'idle'];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : ['status' => 'idle'];
    }
}
