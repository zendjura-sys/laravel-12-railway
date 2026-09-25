<?php

namespace Addons\Bonuses\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentAchievementTier extends Model
{
    protected $table = 'investment_achievement_tiers';

    protected $fillable = ['label', 'threshold_amount', 'bonus_amount', 'sort_order'];
}
