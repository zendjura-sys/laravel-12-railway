<?php

namespace Addons\MemberCenter\Events;

use Addons\MemberCenter\Models\LeaveRequest;

class LeaveRequestReviewed
{
    public function __construct(public readonly LeaveRequest $leaveRequest)
    {
    }
}
