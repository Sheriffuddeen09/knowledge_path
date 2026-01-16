<!DOCTYPE html>
<html>
<head>
    <title>Friend Request Accepted</title>
</head>
<body>
    <h2>Hello {{ $requestModel->admin->first_name }},</h2>

    <p>Your Friend request Send to <strong>{{ $requestModel->admin->first_name }} {{ $requestModel->admin->last_name }}</strong>
  have been <strong>accepted</strong>.</p>

    <p>You can now Start Messaging Each Other.</p>

    <p>Thank you,<br>Islam Path Of Knowledge</p>
</body>
</html>
