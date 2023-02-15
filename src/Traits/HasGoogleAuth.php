<?php

namespace Statikbe\GoogleAuthenticate\Traits;

trait HasGoogleAuth
{

    /**
     * The attributes that are needed for Google Auth
     * @var array
     */
    protected array $auth_fillable = [
        'provider',
        'provider_id'
    ];


    public function getFillable(): array
    {

        return array_merge($this->fillable, $this->auth_fillable);
    }

}
