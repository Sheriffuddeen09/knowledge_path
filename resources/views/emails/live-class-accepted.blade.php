<!DOCTYPE html>
<html>
<head>
    <title>Live Class Accepted</title>
</head>
<body>
    <h2>Hello {{ $requestModel->student->first_name }},</h2>

    <p>Your live class request with <strong>{{ $requestModel->teacher->first_name }} {{ $requestModel->teacher->last_name }}</strong>
 for the Online Course has been <strong>accepted</strong>.</p>

    <p>You can now join the live class.</p>

    <p>Thank you,<br>Your App Name</p>
</body>
</html>
