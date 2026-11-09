<?php

namespace Statikbe\GoogleAuthenticate\Traits;

/**
 * Marker trait for models that log in with Google.
 *
 * It deliberately adds nothing to $fillable: the package sets `provider` and `provider_id`
 * itself with forceFill, so they are never mass-assignable through the host application.
 */
trait HasGoogleAuth {}
