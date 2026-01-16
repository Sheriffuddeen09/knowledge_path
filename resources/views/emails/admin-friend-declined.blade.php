<!DOCTYPE html>
<html>
<head>
    <title>Friend Request Declined</title>
</head>
<body>
    <h2>Hello {{ $requestModel->admin->first_name }},</h2>

    <p>We regret to inform you that your Friend request Send to <strong>{{ $requestModel->admin->first_name }} {{ $requestModel->admin->last_name }}</strong>
     have been <strong>declined</strong>.</p>

    <p>You can try sending another request to the Student or choose a different Schedule.</p>

    <p>Thank you,<br>Islam Path Of Knowledge</p>
</body>
</html>
