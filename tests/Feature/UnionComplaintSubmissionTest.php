<?php

namespace Tests\Feature;

use App\Models\UnionComplaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Подача скарги союзником, історія власних скарг і розгляд з боку
 * лідера/заступника обвинуваченої родини (UnionComplaintController,
 * member-facing — не плутати з Admin\UnionComplaintController).
 */
class UnionComplaintSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function unionMember(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'union_family_name' => 'Family Corvo',
            'union_role' => 'member',
        ], $overrides));
    }

    public function test_a_union_member_can_submit_a_complaint_with_photos(): void
    {
        Storage::fake('public');

        $this->actingAs($this->unionMember())
            ->post(route('union.complaints.store'), [
                'against_family' => 'Family Del Rio',
                'against_name' => 'Порушник',
                'reasons' => ['rdm', 'disrespect'],
                'description' => 'Опис ситуації.',
                'photos' => [UploadedFile::fake()->image('proof.jpg')],
            ])
            ->assertRedirect();

        $complaint = UnionComplaint::where('against_family', 'Family Del Rio')->firstOrFail();
        $this->assertSame('pending', $complaint->status);
        $this->assertCount(1, $complaint->attachments);
    }

    public function test_a_monsory_family_member_without_union_registration_cannot_submit_a_complaint(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('union.complaints.store'), [
                'against_family' => 'x', 'against_name' => 'x', 'reasons' => ['rdm'],
            ])
            ->assertForbidden();
    }

    public function test_a_member_sees_only_their_own_complaints_without_review_rights(): void
    {
        $reporter = $this->unionMember();
        $other = $this->unionMember(['union_family_name' => 'Family Del Rio']);

        UnionComplaint::create([
            'reporter_id' => $reporter->id, 'against_family' => 'Family Del Rio',
            'against_name' => 'x', 'reasons' => ['rdm'], 'status' => 'pending',
        ]);
        UnionComplaint::create([
            'reporter_id' => $other->id, 'against_family' => 'Family Corvo',
            'against_name' => 'y', 'reasons' => ['rdm'], 'status' => 'pending',
        ]);

        $response = $this->actingAs($reporter)->get(route('union.complaints.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('complaints', 1)
            ->where('canReview', false));
    }

    public function test_a_family_leader_can_review_a_complaint_against_their_own_family(): void
    {
        $leader = $this->unionMember(['union_family_name' => 'Family Del Rio', 'union_role' => 'leader']);
        $reporter = $this->unionMember();

        $complaint = UnionComplaint::create([
            'reporter_id' => $reporter->id, 'against_family' => 'Family Del Rio',
            'against_name' => 'Порушник', 'reasons' => ['rdm'], 'status' => 'pending',
        ]);

        $this->actingAs($leader)
            ->get(route('union.complaints.index'))
            ->assertInertia(fn ($page) => $page->has('complaints', 1)->where('canReview', true));

        $this->actingAs($leader)
            ->put(route('union.complaints.update', $complaint), [
                'status' => 'resolved',
                'reviewer_note' => 'Проговорили, більше не повториться.',
            ])
            ->assertRedirect();

        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertSame($leader->id, $complaint->reviewed_by);
    }

    public function test_a_plain_member_of_the_accused_family_cannot_review_the_complaint(): void
    {
        $plainMember = $this->unionMember(['union_family_name' => 'Family Del Rio', 'union_role' => 'member']);
        $reporter = $this->unionMember();

        $complaint = UnionComplaint::create([
            'reporter_id' => $reporter->id, 'against_family' => 'Family Del Rio',
            'against_name' => 'x', 'reasons' => ['rdm'], 'status' => 'pending',
        ]);

        $this->actingAs($plainMember)
            ->put(route('union.complaints.update', $complaint), ['status' => 'resolved'])
            ->assertForbidden();
    }

    public function test_a_leader_of_an_unrelated_family_cannot_review_the_complaint(): void
    {
        $unrelatedLeader = $this->unionMember(['union_family_name' => 'Family Otherside', 'union_role' => 'leader']);
        $reporter = $this->unionMember();

        $complaint = UnionComplaint::create([
            'reporter_id' => $reporter->id, 'against_family' => 'Family Del Rio',
            'against_name' => 'x', 'reasons' => ['rdm'], 'status' => 'pending',
        ]);

        $this->actingAs($unrelatedLeader)
            ->put(route('union.complaints.update', $complaint), ['status' => 'resolved'])
            ->assertForbidden();
    }
}
