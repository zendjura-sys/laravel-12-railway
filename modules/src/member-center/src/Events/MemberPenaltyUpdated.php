<?php

namespace Addons\MemberCenter\Events;

use Addons\MemberCenter\Models\MemberWarning;

/**
 * Зміна стану покарання: paid (штраф сплачено), revoked (скасовано),
 * overdue (штраф прострочено — сума подвоєна), expired (згоріло).
 */
class MemberPenaltyUpdated
{
    public function __construct(public readonly MemberWarning $warning, public readonly string $action)
    {
    }
}
