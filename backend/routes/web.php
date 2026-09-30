<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\ActiveUser;
Route::prefix('api/v1/auth')->group(function(){
 Route::post('register',[AuthController::class,'register'])->middleware('throttle:5,1');Route::post('login',[AuthController::class,'login'])->middleware('throttle:login');Route::post('logout',[AuthController::class,'logout'])->middleware('auth');Route::post('forgot-password',[AuthController::class,'forgot'])->middleware('throttle:5,1');Route::post('reset-password',[AuthController::class,'reset'])->middleware('throttle:5,1');Route::post('verification',[AuthController::class,'resend'])->middleware(['auth',ActiveUser::class,'throttle:3,1']);
});
Route::get('/email/verify/{id}/{hash}',function(\Illuminate\Foundation\Auth\EmailVerificationRequest $r){$r->fulfill();return redirect('/settings?verified=1');})->middleware(['auth','signed','throttle:6,1'])->name('verification.verify');
Route::get('/reset-password/{token}',fn()=>view('app'))->name('password.reset');Route::get('/login',fn()=>view('app'))->name('login');Route::get('/verify-email',fn()=>view('app'))->name('verification.notice');
Route::get('/admin/preview/{exam}',function(\App\Models\Exam $exam){abort_unless(auth()->user()->role==='admin',403);return view('preview',['exam'=>$exam]);})->middleware(['auth',ActiveUser::class]);
Route::get('/admin/templates/questions.csv',function(){abort_unless(auth()->user()->role==='admin',403);return response("id,type,prompt,options,accepted,max_words,max_numbers,explanation\nq1,short_answer,What colour is the door?,,green,1,0,The passage describes a green door.\n",200,['Content-Type'=>'text/csv','Content-Disposition'=>'attachment; filename="questions.csv"']);})->middleware(['auth',ActiveUser::class]);
Route::get('/api-docs',fn()=>response()->file(base_path('../shared/openapi.yaml'),['Content-Type'=>'text/yaml']));
Route::get('/{path?}',fn()=>view('app'))->where('path','^(?!api/|admin/|livewire/|storage/|build/).*$');
