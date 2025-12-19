<!DOCTYPE html>
<html>
<head>
    <title>Reported User Notification</title>
</head>
<body>
    <h2>You have been reported</h2>

    <p><strong>Reported by:</strong> {{ $report->reporter->first_name }} {{ $report->reporter->last_name }}</p>
    <p><strong>Reason:</strong> {{ $report->reason }}</p>
    <p><strong>Details:</strong> {{ $report->details }}</p>
    <p><strong>Message ID:</strong> {{ $report->message->id ?? 'N/A' }}</p>
</body>
</html>
