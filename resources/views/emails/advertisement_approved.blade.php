
<!DOCTYPE html>
<html>
<body>
<h1>
Congratulations. </h1>
<p>
Your advertisement has been approved successfully.
</p>
<p>
You may now select how many users will be able
to view your advertisement. </p>
<ul>
<li>50 badges = 1/4 Users</li>
<li>100 badges = 1/2 Users</li>
<li>200 badges = 3/4 Users</li>
<li>300 badges = All Users</li>
</ul>
<a href="{{env('FRONTEND_URL')}}/advertisement/{{$advertisement->id}}">
Select Advertisement Visibility
</a>
<br>
<br>
Thank you. </body>
</html>