<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\State;

class SpanContext
{
    /**
     * @var array{
     *     type: string,
     *     name: string,
     * }|null
     */
    public ?array $target = null;

    /**
     * @var array{
     *     method?: string,
     *     route?: string,
     *     url?: array{
     *         scheme?: string,
     *         domain?: string,
     *         port?: string,
     *         path?: string,
     *     }
     * }|null
     */
    public ?array $http_request = null;

    /**
     * @var array{
     *     status_code?: int,
     * }|null
     */
    public ?array $http_response = null;

    /**
     * @var array{
     *     stacktrace?: string[],
     * }|null
     */
    public ?array $profile = null;

    /**
     * @var array{
     *     instance?: string,
     *     name?: string,
     *     statement?: string,
     *     system?: string,
     *     type?: string,
     * }|null
     */
    public ?array $db = null;
}
