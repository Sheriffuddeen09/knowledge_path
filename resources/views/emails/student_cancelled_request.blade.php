<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>

<h2>Teacher Request Cancelled</h2>

<p>Hello {{ $request->teacher->first_name }},</p>

<p>
    The following student has cancelled their teacher request.
</p>

<ul>
    <li><strong>Student:</strong>
        {{ $request->student->first_name }}
        {{ $request->student->last_name }}
    </li>

    <li><strong>Subject:</strong>
        {{ $request->subject }}
    </li>

    <li><strong>Status:</strong>
        Cancelled by Student
    </li>
</ul>

<p>
    This request no longer requires any action.
</p>

</body>
</html>