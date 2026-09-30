<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Entitlement extends Model {
 protected $guarded = [];
 public function user() { return $this->belongsTo(User::class); }
 public function plan() { return $this->belongsTo(Plan::class); }
 public function exam() { return $this->belongsTo(Exam::class); }
 protected function casts(): array { return ['expires_at'=>'datetime', 'revoked_at'=>'datetime']; }
}
