<?php

namespace Tests\Feature\Api;

use App\Http\Requests\Api\V1FormRequest;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

class V1RouteRequestContractTest extends TestCase
{
    public function test_workspace_scoped_route_actions_type_hint_v1_form_request(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (! preg_match('#^api/v1/(channels|posts)(/|$)#', $uri)) {
                continue;
            }

            $uses = $route->getAction('controller');

            if (! is_string($uses)) {
                $this->fail('Route '.$uri.' must use a controller action string, not a closure.');
            }

            if (str_contains($uses, '@')) {
                [$class, $method] = explode('@', $uses, 2);
            } else {
                $class = $uses;
                $method = '__invoke';
            }

            $this->assertTrue(
                class_exists($class),
                'Route '.$uri.' controller class '.$class.' must exist.'
            );

            $reflection = new ReflectionMethod($class, $method);
            $hasV1FormRequest = false;

            foreach ($reflection->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $typeName = $type->getName();

                if (is_a($typeName, V1FormRequest::class, true)) {
                    $hasV1FormRequest = true;

                    break;
                }
            }

            $methods = implode(',', array_diff($route->methods(), ['HEAD']));
            $this->assertTrue(
                $hasV1FormRequest,
                'Route ['.$methods.'] '.$uri.' ('.$class.'::'.$method.') must type-hint a subclass of '.V1FormRequest::class
            );
        }
    }
}
