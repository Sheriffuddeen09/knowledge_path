<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>

<body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:30px;">

<div style="
max-width:650px;
margin:auto;
background:#fff;
padding:30px;
border-radius:12px;
">

<h2 style="color:#2563eb;">
Congratulations!
</h2>

<p>

Hello
<strong>

{{ $request->teacher->first_name }}
{{ $request->teacher->last_name }}

</strong>,

</p>

<p>

The student has accepted your proposal request.

</p>

<hr>

<h3>

Proposal Information

</h3>

<p>

<strong>Title:</strong>

{{ $request->proposal->title }}

</p>

<p>

<strong>Subject:</strong>

{{ $request->proposal->subject }}

</p>

<p>

<strong>Student:</strong>

{{ $request->student->first_name }}
{{ $request->student->last_name }}

</p>

<hr>

<p>

You can now start chatting with the student from your dashboard.

</p>

<p>

Good luck with your teaching!

</p>

</div>

</body>
</html>