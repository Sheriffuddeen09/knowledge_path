<p>Hello {{ $report->reportedUser->first_name }},</p>

<p>
Your account has been reported for violating our chat guidelines.
</p>

<p><strong>Reason:</strong> {{ $report->reason }}</p>

<p>
If you believe this is a mistake, please contact support.
</p>