<?php
namespace Meva\Entities\User\Services;

class UserLoginService
{
    public function handle($credentials)
    {
        return auth()->attempt($credentials);
    }
}
