<!DOCTYPE html>
<html>

<body>

<h2>Hello {{ $request->student->first_name }},</h2>

<p>
A teacher has sent a request to teach on your proposal.
</p>

<hr>

<h3>Teacher</h3>

<p>
{{ $request->teacher->first_name }}
{{ $request->teacher->last_name }}
</p>

<h3>Proposal</h3>

<p>
<strong>Title:</strong>
{{ $request->proposal->title }}
</p>

<p>
<strong>Subject:</strong>
{{ $request->proposal->subject }}
</p>

<p>
Please login to your dashboard to accept or reject the request.
</p>

</body>
</html>