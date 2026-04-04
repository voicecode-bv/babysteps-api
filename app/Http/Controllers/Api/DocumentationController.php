<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;
use OpenApi\Generator;

class DocumentationController extends Controller
{
    public function ui(): View
    {
        return view('api-docs');
    }

    public function spec(): Response
    {
        $openapi = (new Generator)
            ->generate([app_path()]);

        return response($openapi->toJson(), 200, [
            'Content-Type' => 'application/json',
        ]);
    }
}
