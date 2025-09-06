<?php

namespace Fasync\EventLoop\Interfaces;

/**
 * Represents an HTTP request within the async system.
 *
 * This interface encapsulates an HTTP request with its associated cURL handle,
 * callback function, and execution methods for async HTTP operations.
 */
interface AsyncHttpRequestInterface
{
    /**
     * Gets the cURL handle associated with this HTTP request.
     *
     * @return \CurlHandle The cURL handle for this request
     */
    public function getHandle(): \CurlHandle;

    /**
     * Gets the callback function that will handle the response.
     *
     * @return callable The response callback function
     */
    public function getCallback(): callable;

    /**
     * Gets the URL being requested.
     *
     * @return string The request URL
     */
    public function getUrl(): string;

    /**
     * Executes the callback with the request results.
     *
     * This method is called when the HTTP request completes, either
     * successfully or with an error.
     *
     * @param  string|null  $error  Error message if the request failed, null on success
     * @param  string|null  $response  The HTTP response body, null on error
     * @param  int|null  $httpCode  The HTTP status code, null on error
     */
    public function executeCallback(?string $error, ?string $response, ?int $httpCode): void;
}
