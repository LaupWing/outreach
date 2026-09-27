<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Snelreach card: Catalog, with a link into the app.')]
#[AppMeta]
class CatalogApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('mcp.catalog-app', ['title' => 'Catalog']);
    }
}
