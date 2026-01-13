<!DOCTYPE html>
<html>
<head>
    <title>New Live Class Request</title>
</head>
<body>

    <h2>Hello {{ $requestModel->student->first_name }},</h2>
    <p>
        {{ $requestModel->student->first_name }} {{ $requestModel->student->last_name }} have send you Friend request 
.
    </p>
    <p>Please review and respond to the request.</p>
    <p>Thank you,<br>Islam Path Of Knowledge</p>
</body>
</html>
