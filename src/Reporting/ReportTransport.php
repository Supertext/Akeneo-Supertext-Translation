<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Reporting;

use Supertext\PluginHealth\Http\HttpRequest;
use Supertext\PluginHealth\Http\HttpResponse;
use Supertext\PluginHealth\Http\HttpTransport;
use Supertext\Sdk\Http\HttpRequest as SdkRequest;
use Supertext\Sdk\Http\HttpTransport as SdkTransport;

/**
 * Sends Supertext Plugin Health's reports through an SDK transport (the one Reporting is given in
 * tests), so the key check and the report share one fake. The two libraries have the same HTTP
 * shapes in their own namespaces; this only converts between them. A missing answer stays an
 * exception, which Plugin Health records as a failed report. Without a transport, both use curl.
 */
final class ReportTransport implements HttpTransport
{
    public function __construct(private readonly SdkTransport $transport)
    {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $response = $this->transport->send(new SdkRequest($request->method, $request->url, $request->headers, $request->body, $request->timeout));

        return new HttpResponse($response->status, $response->body, $response->headers);
    }
}
