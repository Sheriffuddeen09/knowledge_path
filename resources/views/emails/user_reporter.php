<!DOCTYPE html>
<html>
<head>
    <title>Report Submitted</title>
</head>
<body>
    <h2>Thank you for reporting</h2>

    <p>Thanks for reporting the message "{{ $report->message->id ?? 'N/A' }}". We will check and take necessary action.</p>

    <p><strong>Reason:</strong> {{ $report->reason }}</p>
    <p><strong>Details:</strong> {{ $report->details }}</p>
</body>
</html>
