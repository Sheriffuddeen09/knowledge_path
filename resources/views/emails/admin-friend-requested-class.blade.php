<!DOCTYPE html>
<html>
<head>
    <title>New Live Class Request</title>
</head>
<body>

    <h2>Hello {{ $requestModel->admin->first_name }},</h2>
    <p>
        {{ $requestModel->admin->first_name }} {{ $requestModel->admin->last_name }} have send you Friend request 
.
    </p>
    <p>Please Review and Respond to the request.</p>
    <p>Thank you,<br>Islam Path Of Knowledge</p>
</body>
</html>
