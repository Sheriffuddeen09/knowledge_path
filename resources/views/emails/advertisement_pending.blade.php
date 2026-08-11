<!DOCTYPE html>
<html>
<body>
<h1>
New Advertisement Request
</h1>
<p>
A new advertisement has been submitted. </p>
<p>
Title:
{{$advertisement->title}}
</p>
<p>
Type:
{{$advertisement->type}}
</p>
<p>
Description:
{{$advertisement->description}}
</p>
@if($advertisement->link)
<p>
Link:
{{$advertisement->link}}
</p>
@endif
<a href="{{env('FRONTEND_URL')}}/admin/advertisement">
Review Advertisement
</a>
<br>
<br>
Thank you. </body>
</html>