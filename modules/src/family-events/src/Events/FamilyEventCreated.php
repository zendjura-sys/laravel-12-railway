<?php

namespace Addons\FamilyEvents\Events;

use Addons\FamilyEvents\Models\FamilyEvent;

class FamilyEventCreated
{
    public function __construct(public readonly FamilyEvent $event)
    {
    }
}
