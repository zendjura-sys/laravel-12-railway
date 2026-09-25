<?php

namespace Addons\MemberCenter\Events;

use App\Models\User;

/** 3/3 активні догани — учасник підлягає виключенню (п. 8.6 правил родини). */
class MemberReprimandLimitReached
{
    public function __construct(public readonly User $user, public readonly int $count)
    {
    }
}
