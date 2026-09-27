<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\FamilyContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyContentUnionTest extends TestCase
{
    use RefreshDatabase;

    public function test_union_rules_and_terms_fall_back_to_config(): void
    {
        $this->assertSame(config('family.union_rules'), FamilyContent::unionRules());
        $this->assertSame(config('family.union_terms'), FamilyContent::unionTerms());
    }

    public function test_union_rules_can_be_overridden_from_settings(): void
    {
        Setting::set('union_rules', json_encode(['Правило раз', 'Правило два'], JSON_UNESCAPED_UNICODE), 'content');

        $this->assertSame(['Правило раз', 'Правило два'], FamilyContent::unionRules());
    }
}
