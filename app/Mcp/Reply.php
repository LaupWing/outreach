<?php

namespace App\Mcp;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * A tool answer the model can read and the host can draw: the summary line, the same
 * data as JSON in the text (hosts that render a card show the model only the text),
 * and the structured content for the card.
 */
class Reply
{
    /**
     * @param  array<string, mixed>  $structured
     */
    public static function make(string $summary, array $structured): ResponseFactory
    {
        $json = json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return Response::make(Response::text($summary."\n\n".$json))->withStructuredContent($structured);
    }
}
