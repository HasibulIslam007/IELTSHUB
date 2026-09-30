<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditEvent extends Model {
 protected $guarded = [];
 public $timestamps = false;
 protected function casts(): array { return ['changes'=>'array', 'created_at'=>'datetime']; }
}
