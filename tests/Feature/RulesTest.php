<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FamilyRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rules_page_renders_all_books(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/rules')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Rules/Index', false)
                ->has('books', count(FamilyRules::books()))
                ->has('levels', 6));
    }

    public function test_api_rules_for_mobile(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/rules')
            ->assertOk()
            ->assertJsonPath('books.0.slug', 'monsory')
            ->assertJsonStructure(['levels', 'books' => [['slug', 'title', 'sections' => [['title', 'rules' => [['code', 'text', 'notes', 'penalties']]]]]]]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/rules')->assertRedirect('/login');
    }

    public function test_parser_handles_discord_format(): void
    {
        $md = <<<'MD'
        # 1. Розділ

        **1.1 Перший пункт.**
        ```diff
        - [Jail 60 хвилин]
        ! Примітка до пункту.
        ```

        **1.2 Пункт, розбитий
        на два рядки.**
        MD;

        $sections = FamilyRules::parse($md, 'project');

        $this->assertSame('1. Розділ', $sections[0]['title']);
        $this->assertSame('1.1', $sections[0]['rules'][0]['code']);
        $this->assertSame('jail', $sections[0]['rules'][0]['penalties'][0]['level']);
        $this->assertSame(['Примітка до пункту.'], $sections[0]['rules'][0]['notes']);
        $this->assertSame('Пункт, розбитий на два рядки.', $sections[0]['rules'][1]['text']);
    }

    public function test_family_penalty_levels(): void
    {
        $this->assertSame('remark', FamilyRules::levelFor('Зауваження', 'family'));
        $this->assertSame('fine', FamilyRules::levelFor('Штраф 50 000₴', 'family'));
        $this->assertSame('reprimand', FamilyRules::levelFor('Штраф 50 000₴ / Догана', 'family'));
        $this->assertSame('kick', FamilyRules::levelFor('Догана; 7+ днів — виключення', 'family'));
        $this->assertSame('blacklist', FamilyRules::levelFor('Виключення + чорний список', 'family'));
        $this->assertSame('ban', FamilyRules::levelFor('Бан 30 днів + повернення бізнесу', 'project'));
    }
}
