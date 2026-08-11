<!DOCTYPE html>
<html>
<body>
<h1>
Advertisement Declined
</h1>
<p>
Unfortunately, your advertisement has not been
approved at this time. </p>
<p>
Reason:
</p>
<p>
{{$advertisement->decline_reason}}
</p>
<p>
You may edit and resubmit your advertisement. </p>
<a href="{{env('FRONTEND_URL')}}/advertisement/edit/{{$advertisement->id}}">
Edit Advertisement
</a>
<br>
<br>
Thank you. </body>
</html>