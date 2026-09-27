<?php

namespace Tests\Unit;

use App\Support\GuideContent;
use Tests\TestCase;

class GuideContentTest extends TestCase
{
    /** Бот і сайт лінкуються на категорії за slug — дублікат тихо ламав би навігацію одного з них. */
    public function test_category_slugs_are_unique(): void
    {
        $slugs = array_column(GuideContent::categories(), 'slug');

        $this->assertSame($slugs, array_unique($slugs));
    }

    /** Порожня категорія — це або помилка контенту, або мертва кнопка в боті/на сайті. */
    public function test_every_category_has_title_icon_and_items(): void
    {
        foreach (GuideContent::categories() as $category) {
            $this->assertNotSame('', trim($category['title']));
            $this->assertNotSame('', trim($category['icon']));
            $this->assertNotEmpty($category['items']);

            foreach ($category['items'] as $item) {
                $this->assertNotSame('', trim($item['q']));
                $this->assertNotSame('', trim($item['a']));
            }
        }
    }

    public function test_category_by_slug_finds_an_existing_category(): void
    {
        $first = GuideContent::categories()[0];

        $this->assertSame($first, GuideContent::categoryBySlug($first['slug']));
    }

    public function test_category_by_slug_returns_null_for_unknown_or_empty_slug(): void
    {
        $this->assertNull(GuideContent::categoryBySlug('not-a-real-category'));
        $this->assertNull(GuideContent::categoryBySlug(null));
        $this->assertNull(GuideContent::categoryBySlug(''));
    }
}
