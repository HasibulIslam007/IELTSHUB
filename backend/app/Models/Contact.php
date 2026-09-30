<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $guarded = [];

    protected $attributes = ['delivery_status' => 'stored_not_sent', 'status' => 'new'];

    protected function casts(): array
    {
        return [];
    }
}
