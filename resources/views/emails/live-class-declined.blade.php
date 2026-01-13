<!DOCTYPE html>
<html>
<head>
    <title>Live Class Declined</title>
</head>
<body>
    <h2>Hello {{ $requestModel->student->first_name }},</h2>

    <p>We regret to inform you that your live class request with <strong>{{ $requestModel->teacher->first_name }} {{ $requestModel->teacher->last_name }}</strong> for the 
     Online Course has been <strong>declined</strong>.</p>

    <p>You can try sending another request to the teacher or choose a different schedule.</p>

    <p>Thank you,<br>Islam Path Of Knowledge</p>
</body>
</html>
