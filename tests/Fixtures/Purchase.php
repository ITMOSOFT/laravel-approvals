<?php

namespace ItmoSoft\Approvals\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $table = 'purchases';
    protected $guarded = [];
    public $timestamps = false;
}
