<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\DesignSettings;
use App\Support\FamilyContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Акцент уезжает в <style> НЕэкранированным ({!! !!}), поэтому всё,
     * что не является строгим hex, обязано схлопываться в значение по
     * умолчанию, а не попадать на страницу.
     */
    public function test_accent_rejects_anything_that_is_not_a_hex_colour(): void
    {
        foreach (['</style><script>alert(1)</script>', 'red', '#ABC', '#GGGGGG', '', '#D4AF37; --x: y'] as $bad) {
            Setting::set('design_accent', $bad, 'design');

            $this->assertSame(
                DesignSettings::DEFAULT_ACCENT,
                DesignSettings::accent(),
                "значення {$bad} мало впасти до типового",
            );
        }
    }

    public function test_accent_accepts_a_real_hex_and_builds_the_palette(): void
    {
        Setting::set('design_accent', '#3fa7d6', 'design');

        $this->assertSame('#3FA7D6', DesignSettings::accent());

        $css = DesignSettings::accentCss();
        $this->assertStringContainsString('--gold-400:63 167 214;', $css);
        // Светлые ступени выше акцента, тёмные ниже — иначе от одного
        // цвета выходит плоская заливка вместо градиентов.
        $this->assertMatchesRegularExpression('/--gold-100:\d+ \d+ \d+;/', $css);
        $this->assertMatchesRegularExpression('/--gold-600:\d+ \d+ \d+;/', $css);
    }

    /** Пока цвет не меняли, инлайн-переопределения быть не должно вовсе. */
    public function test_default_accent_emits_no_inline_css(): void
    {
        $this->assertSame('', DesignSettings::accentCss());
    }

    public function test_effects_level_falls_back_when_unknown(): void
    {
        Setting::set('design_effects', 'sparkles', 'design');
        $this->assertSame('full', DesignSettings::effects());

        Setting::set('design_effects', 'off', 'design');
        $this->assertSame('off', DesignSettings::effects());
    }

    /**
     * Пустой список из админки и «не трогали» — разные вещи: во втором
     * случае берём значения из config/family.php.
     */
    public function test_content_falls_back_to_config_until_it_is_edited(): void
    {
        $this->assertSame(config('family.positions'), FamilyContent::positions());

        FamilyContent::save('positions', [
            ['title' => 'Стажер', 'text' => 'Точка входу', 'image' => '/images/roles/rank-trainee.webp'],
        ]);

        $this->assertCount(1, FamilyContent::positions());

        FamilyContent::reset('positions');

        $this->assertSame(config('family.positions'), FamilyContent::positions());
    }

    /** Строки без названия — мусор из формы, в настройках им делать нечего. */
    public function test_saving_drops_empty_rows_and_unknown_keys(): void
    {
        FamilyContent::save('leadership', [
            ['title' => 'Директор', 'text' => 'Глава родини', 'nickname' => 'Mark', 'evil' => 'x'],
            ['title' => '  ', 'text' => 'без назви', 'nickname' => 'Y'],
        ]);

        $saved = FamilyContent::leadership();

        $this->assertCount(1, $saved);
        $this->assertSame(['title', 'text', 'nickname'], array_keys($saved[0]));
        $this->assertSame('Mark', $saved[0]['nickname']);
    }

    /** Повреждённый JSON в настройке не должен ронять главную страницу. */
    public function test_broken_stored_json_falls_back_to_config(): void
    {
        Setting::set('leadership', '{не json', 'content');

        $this->assertSame(config('family.leadership'), FamilyContent::leadership());
    }
}
