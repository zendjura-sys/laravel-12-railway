<?php

namespace Addons\Progression\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = ['code', 'name', 'description'];
}
