<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Таблиця failed_jobs — стандартна для Laravel (мігрується разом із jobs/
 * job_batches у 0001_01_01_000002_create_jobs_table.php), сюди чергу пише
 * сам фреймворк при падінні джоби. Модель лише читає й видаляє — записів
 * сюди руками ніхто не додає.
 */
class FailedJob extends Model
{
    public $timestamps = false;

    protected $table = 'failed_jobs';

    protected $casts = [
        'failed_at' => 'datetime',
    ];

    /** Ім'я класу джоби з серіалізованого payload — те, що реально впало. */
    public function jobName(): string
    {
        $payload = json_decode($this->payload, true);

        return $payload['displayName'] ?? $payload['job'] ?? 'Невідома джоба';
    }

    /** Перший рядок стек-трейсу — короткий підсумок без стіни тексту в таблиці. */
    public function exceptionSummary(): string
    {
        return str((string) $this->exception)->before("\n")->limit(180)->toString();
    }
}
