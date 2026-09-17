<?php

namespace Addons\Bonuses\Models;

use Illuminate\Database\Eloquent\Model;

class BonusSettings extends Model
{
    protected $table = 'bonus_settings';

    protected $fillable = [
        'bizwar_base_rate',
        'contract_light_rate',
        'contract_medium_rate',
        'contract_heavy_rate',
        'streak_threshold',
        'streak_bonus_amount',
        'contracts_count_threshold',
        'contracts_count_bonus_amount',
        'min_digest_amount',
    ];

    /** Один рядок на весь модуль — міграція вже його створює. */
    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}
