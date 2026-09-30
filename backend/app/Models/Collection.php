<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Collection extends Model {
 protected $guarded = [];
 protected $table = 'collections';
 protected function casts(): array { return ['is_mock'=>'boolean', 'sequence'=>'array']; }
}
