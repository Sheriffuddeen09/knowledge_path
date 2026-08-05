<?php
namespace App\Mail;
use App\Models\Advertisement;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
class AdvertisementPendingMail extends Mailable
{
use Queueable,SerializesModels;
public $advertisement;
public function __construct(
Advertisement $advertisement
)
{
$this->advertisement=$advertisement;
}
public function build()
{
return $this->subject(
"New Advertisement Request" )->view(
"emails.advertisement.pending" );
}
}