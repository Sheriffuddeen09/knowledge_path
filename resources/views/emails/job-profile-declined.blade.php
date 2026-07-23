<h2>Job Profile Declined</h2>
<p>Hello {{ $profile->user->first_name }},</p>
<p>
Unfortunately, your {{ ucfirst($profile->type) }} profile was not approved.
</p>
<p>
<strong>Reason:</strong><br>
{{ $profile->decline_reason }}
</p>
<p>
Please update your profile and submit it again.
</p>
<p>Thank you.</p>