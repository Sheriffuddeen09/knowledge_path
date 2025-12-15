<?php

// app/Observers/UserObserver.php
class UserObserver
{
    public function created(User $user)
    {
        ProfileVisibility::create([
            'user_id' => $user->id
        ]);
    }
}
