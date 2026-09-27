<?php

namespace Addons\FamilyGoals\Events;

use Addons\FamilyGoals\Models\FamilyGoal;

class FamilyGoalCompleted
{
    public function __construct(public readonly FamilyGoal $goal)
    {
    }
}
