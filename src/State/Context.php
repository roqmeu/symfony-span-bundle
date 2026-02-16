<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\State;

class Context
{
    /**
     * @var array{
     *     debug?: bool,
     *     environment?: string,
     *     name?: string,
     *     version?: string,
     * }|null
     */
    public ?array $framework = null;

    /**
     * @var array{
     *     executable?: ?string,
     *     interactive?: ?bool,
     *     pid?: ?int,
     *     parent_pid?: ?int,
     *     runtime_name?: ?string,
     *     runtime_version?: ?string,
     * }|null
     */
    public ?array $process = null;

    /**
     * @var array{
     *     name?: ?string,
     * }|null
     */
    public ?array $command = null;

    /**
     * @var array{
     *     method?: ?string,
     *     route?: ?string,
     *     url?: array{
     *         scheme?: ?string,
     *         domain?: ?string,
     *         port?: ?int,
     *         path?: ?string,
     *     }
     * }|null
     */
    public ?array $http_request = null;

    /**
     * @var array{
     *     status_code?: ?int,
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
     *     instance?: ?string,
     *     name?: ?string,
     *     statement?: ?string,
     *     system?: ?string,
     *     type?: ?string,
     * }|null
     */
    public ?array $db = null;

    /**
     * @var array{
     *     host?: ?string,
     *     port?: ?int,
     * }|null
     */
    public ?array $server = null;

    /**
     * @var array{
     *     consumer_name?: ?string,
     *     name?: ?string,
     *     queue_name?: ?string,
     *     retry_attempt?: ?int,
     *     retry_delay?: ?float,
     * }|null
     */
    public ?array $message = null;
}
