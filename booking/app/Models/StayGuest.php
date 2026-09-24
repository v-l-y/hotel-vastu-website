<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StayGuest extends Model
{
    protected $fillable = ['stay_id', 'guest_id', 'role'];
}
