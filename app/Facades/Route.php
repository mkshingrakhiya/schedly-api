<?php

namespace App\Facades;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Route as IlluminateRoute;

/**
 * Extends the framework Route facade so IDE and static analysis know about
 * macros registered on the router in {@see AppServiceProvider::boot()}.
 *
 * @method static void enum(string $enum, callable(object): void $callback)
 */
class Route extends IlluminateRoute {}
