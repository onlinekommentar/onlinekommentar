<?php

namespace App\Api\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Symfony\Component\HttpFoundation\Response;

class Cache
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isLocal()) {
            return $next($request);
        }

        if ($request->method() !== 'GET') {
            return $next($request);
        }

        $key = 'jsonapi:'.md5($request->fullUrl());

        $data = CacheFacade::get($key);
        if ($data) {
            $response = response($data['content'], $data['status']);
            $response->headers->replace($data['headers']);
            $response->headers->set('X-Cache', 'HIT');

            return $response;
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $data = [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ];
            CacheFacade::put($key, $data, now()->addMinutes(15));
            $response->headers->set('Cache-Control', 'public, max-age=900');
        }

        return $response;
    }
}
