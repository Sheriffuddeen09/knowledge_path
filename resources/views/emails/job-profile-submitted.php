<h2>New Job Profile Submitted</h2>
<p>User:
 {{ $profile->user->first_name }}
 {{ $profile->user->last_name }}
</p>
<p>Type:
 {{ ucfirst($profile->type) }}
</p>
<p>Status:
 {{ $profile->status }}
</p>