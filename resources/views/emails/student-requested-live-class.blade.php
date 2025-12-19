<!DOCTYPE html>
<html>
<head>
    <title>New Live Class Request</title>
</head>
<body>
    @php
        $teacherInfo = json_decode($requestModel->teacher->teacher_info, true);
    @endphp

    <h2>Hello {{ $requestModel->teacher->first_name }},</h2>
    <p>
        {{ $requestModel->student->first_name }} {{ $requestModel->student->last_name }} has requested a live class for 
        Your Online Course Teaching
.
    </p>
    <p>Please review and respond to the request.</p>
    <p>Thank you,<br>Islam Path Of Knowledge</p>
</body>
</html>
