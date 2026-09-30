<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
class FeedbackPublished extends Notification implements ShouldQueue {
 use Queueable;
 public function __construct(public string $attemptId) { $this->afterCommit(); }
 public function via($notifiable): array { return ['database']; }
 public function toArray($notifiable): array { return ['title'=>'Your teacher feedback is ready','message'=>'Open your submission to see criterion scores and next steps.','url'=>'/results/'.$this->attemptId]; }
}
