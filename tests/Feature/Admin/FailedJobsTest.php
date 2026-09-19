<?php

namespace Tests\Feature\Admin;

use App\Models\FailedJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Без цього списку провалена чергова джоба (розсилка, тижневі премії)
 * зникала б у failed_jobs непомітно — перевіряємо, що адмін бачить список,
 * може повторити чи видалити запис, а звичайний учасник — ні.
 */
class FailedJobsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function failedJob(): FailedJob
    {
        // Ніколи не створюється через Eloquent create() у продакшні — цей
        // рядок пише сам Laravel (DatabaseFailedJobProvider), тому в моделі
        // немає $fillable. Тут наповнюємо таблицю напряму, як і фреймворк.
        $id = DB::table('failed_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\TestJob']),
            'exception' => "RuntimeException: test\nStack trace:\n#0 {main}",
            'failed_at' => now(),
        ]);

        return FailedJob::findOrFail($id);
    }

    public function test_an_admin_can_see_the_failed_jobs_list(): void
    {
        $this->failedJob();

        $this->actingAs($this->admin())
            ->get(route('admin.failed-jobs.index'))
            ->assertOk();
    }

    public function test_a_member_without_permission_cannot_see_the_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.failed-jobs.index'))
            ->assertForbidden();
    }

    public function test_an_admin_can_delete_a_failed_job(): void
    {
        $job = $this->failedJob();

        $this->actingAs($this->admin())
            ->delete(route('admin.failed-jobs.destroy', $job->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['id' => $job->id]);
    }

    public function test_an_admin_can_retry_a_failed_job(): void
    {
        $job = $this->failedJob();

        $this->actingAs($this->admin())
            ->post(route('admin.failed-jobs.retry', $job->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['id' => $job->id]);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_an_admin_can_clear_all_failed_jobs(): void
    {
        $this->failedJob();
        $this->failedJob();

        $this->actingAs($this->admin())
            ->delete(route('admin.failed-jobs.clear'))
            ->assertRedirect();

        $this->assertDatabaseCount('failed_jobs', 0);
    }
}
