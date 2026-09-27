<?php

namespace Addons\MemberCenter\Events;

use Addons\MemberCenter\Models\MemberWarning;

class MemberWarningIssued
{
    public function __construct(public readonly MemberWarning $warning)
    {
    }
}
