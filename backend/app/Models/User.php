<?php
namespace App\Models;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable implements FilamentUser, MustVerifyEmail {
 use HasFactory, Notifiable, HasApiTokens;
 protected $fillable = ['name','email','password','profile'];
 protected $hidden = ['password','remember_token'];
 protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','suspended'=>'boolean','profile'=>'array','deletion_requested_at'=>'datetime']; }
 public function canAccessPanel(Panel $panel): bool { return !$this->suspended && in_array($this->role, ['admin','teacher']); }
 public function attempts() { return $this->hasMany(Attempt::class); }
 public function entitlements() { return $this->hasMany(Entitlement::class); }
}
