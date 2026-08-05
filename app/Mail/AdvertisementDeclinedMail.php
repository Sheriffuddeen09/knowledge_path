<?php
namespace App\Mail;
use App\Models\Advertisement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
class AdvertisementDeclinedMail extends Mailable
{
use Queueable, SerializesModels;
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
"Advertisement Declined" )->view(
"emails.advertisement.declined" );
}
}