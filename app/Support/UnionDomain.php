<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * union.monsory.net (UNION_DOMAIN) — той самий застосунок/база/логін,
 * що й monsory.net, різниться лише те, ЩО рендериться. Хто саме бачить
 * союзну версію сторінки (реєстрація, кабінет) вирішує ПОТОЧНИЙ ДОМЕН,
 * а не тип акаунту: учасник родини, який зайшов на union.monsory.net,
 * бачить кабінет союзу так само, як союзник, що зайшов на monsory.net,
 * бачить звичайний кабінет родини.
 */
class UnionDomain
{
    public static function matches(Request $request): bool
    {
        $domain = config('app.union_domain');

        return $domain && $request->getHost() === $domain;
    }
}
