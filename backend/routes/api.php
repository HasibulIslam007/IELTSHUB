<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{AuthController,CatalogueController,AttemptController,StudentController,WorkspaceController};
use App\Http\Middleware\ActiveUser;
Route::prefix('v1')->middleware('throttle:api')->group(function(){
 Route::get('config',[CatalogueController::class,'config']);Route::get('tests',[CatalogueController::class,'index']);Route::get('tests/{exam}',[CatalogueController::class,'show']);Route::get('sample',[CatalogueController::class,'sample']);Route::get('collections',[CatalogueController::class,'collections']);
 Route::post('contact',[CatalogueController::class,'contact'])->middleware('throttle:5,1');
 Route::post('auth/token',[AuthController::class,'token'])->middleware('throttle:login');
 Route::post('billing/checkout',fn()=>response()->json(['message'=>'Checkout is unavailable. A payment provider has not been configured.'],503));
 Route::post('billing/webhook',fn()=>response()->json(['message'=>'No payment provider is enabled. No events or access grants were processed.'],503));
 Route::middleware(['auth:sanctum',ActiveUser::class])->group(function(){
  Route::get('profile',[StudentController::class,'profile']);Route::put('profile',[StudentController::class,'updateProfile']);
  Route::get('progress',[StudentController::class,'progress']);Route::get('attempts',[AttemptController::class,'index']);
  Route::post('tests/{exam}/attempts',[AttemptController::class,'start']);Route::get('attempts/{attempt}',[AttemptController::class,'show']);
  Route::put('attempts/{attempt}',[AttemptController::class,'mutate']);Route::post('attempts/{attempt}/{action}',[AttemptController::class,'mutate'])->whereIn('action',['submit','pause','resume']);
  Route::post('attempts/{attempt}/recordings',[AttemptController::class,'upload'])->middleware('throttle:10,1');
  Route::post('attempts/{attempt}/audio/{section}',[AttemptController::class,'audio']);Route::get('attempts/{attempt}/audio/{section}/recover',[AttemptController::class,'audioRecovery']);
  Route::get('attempts/{attempt}/audio/{section}/stream',[AttemptController::class,'audioStream'])->middleware('signed')->name('attempt.audio');
  Route::get('recordings/{recording}/url',[AttemptController::class,'mediaUrl']);Route::get('recordings/{recording}/stream',[AttemptController::class,'stream'])->middleware('signed')->name('recording.stream');
  Route::get('bookmarks',[StudentController::class,'bookmarks']);Route::post('attempts/{attempt}/bookmarks',[StudentController::class,'bookmark']);Route::delete('bookmarks/{bookmark}',[StudentController::class,'deleteBookmark']);
  Route::get('notifications',[StudentController::class,'notifications']);Route::post('notifications/read',[StudentController::class,'readNotifications']);
  Route::get('account/export',[StudentController::class,'export']);Route::post('account/deletion',[StudentController::class,'deletion']);Route::delete('auth/token',function(\Illuminate\Http\Request $r){$r->user()->currentAccessToken()?->delete();return response()->noContent();});
  Route::get('reviews',[WorkspaceController::class,'reviews']);Route::get('reviews/{assessment}',[WorkspaceController::class,'review']);Route::put('reviews/{assessment}',[WorkspaceController::class,'saveReview']);Route::post('reviews/{assessment}/assign',[WorkspaceController::class,'assign']);
  Route::post('admin/tests/{exam}/publish',[WorkspaceController::class,'publish']);Route::post('admin/import',[WorkspaceController::class,'import']);Route::get('admin/tests/{exam}/export',[WorkspaceController::class,'export']);
 });
});
