<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Hash,Password};
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
 public function register(Request $r){$d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:254|unique:users','password'=>['required','confirmed',PasswordRule::min(10)]]);$u=User::create($d);Auth::login($u);$r->session()->regenerate();if(config('hub.mail_enabled'))$u->sendEmailVerificationNotification();return response()->json(['user'=>$u,'email_delivery'=>config('hub.mail_enabled')?'queued_by_mailer':'not_configured'],201);}
 public function login(Request $r){$d=$r->validate(['email'=>'required|email','password'=>'required|string']);if(!Auth::attempt($d+['suspended'=>false]))throw ValidationException::withMessages(['email'=>'These credentials are invalid or the account is suspended.']);$r->session()->regenerate();return ['user'=>$r->user()];}
 public function logout(Request $r){Auth::guard('web')->logout();$r->session()->invalidate();$r->session()->regenerateToken();return response()->noContent();}
 public function forgot(Request $r){$r->validate(['email'=>'required|email']);if(!config('hub.mail_enabled'))return response()->json(['message'=>'Password-reset email is unavailable until the mail service is configured. Contact the administrator.'],503);Password::sendResetLink($r->only('email'));return ['message'=>'If an account matches, a reset email has been requested.'];}
 public function reset(Request $r){$d=$r->validate(['token'=>'required','email'=>'required|email','password'=>['required','confirmed',PasswordRule::min(10)]]);$status=Password::reset($d,function(User $u,string $password){$u->forceFill(['password'=>$password,'remember_token'=>\Illuminate\Support\Str::random(60)])->save();$u->tokens()->delete();\Illuminate\Support\Facades\DB::table('sessions')->where('user_id',$u->id)->delete();});abort_unless($status===Password::PASSWORD_RESET,422,__($status));return ['message'=>__($status)];}
 public function resend(Request $r){if(!config('hub.mail_enabled'))return response()->json(['message'=>'Verification email is not configured.'],503);if(!$r->user()->hasVerifiedEmail())$r->user()->sendEmailVerificationNotification();return ['message'=>'Verification email requested.'];}
 public function token(Request $r){$d=$r->validate(['email'=>'required|email','password'=>'required|string','device_name'=>'required|string|max:100']);$u=User::where('email',$d['email'])->first();abort_unless($u&&!$u->suspended&&Hash::check($d['password'],$u->password),401,'Invalid credentials.');return ['token'=>$u->createToken($d['device_name'],['student'],now()->addDays(30))->plainTextToken,'expires_in_days'=>30];}
}
