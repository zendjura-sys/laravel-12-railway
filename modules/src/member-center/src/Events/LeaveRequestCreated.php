<?php

namespace Addons\MemberCenter\Events;

use Addons\MemberCenter\Models\LeaveRequest;

class LeaveRequestCreated
{
    public function __construct(public readonly LeaveRequest $leaveRequest)
    {
    }
}
