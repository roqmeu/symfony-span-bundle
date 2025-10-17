<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\State;

class TransactionContext
{
    /**
     * @var array{
     *     debug?: bool,
     *     environment?: string,
     *     framework?: string,
     *     version?: string,
     * }
     */
    public array $framework = [];

    /**
     * @var array{
     *     executable?: string,
     *     interactive?: bool,
     *     pid?: int,
     *     parent_pid?: int,
     *     runtime_name?: string,
     *     runtime_version?: string,
     * }
     */
    public array $process = [];

    /**
     * @var array{
     *     name?: string,
     * }
     */
    public array $command = [];

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
     * }
     */
    public array $http_request = [];

    /**
     * @var array{
     *     status_code?: int,
     * }
     */
    public array $http_response = [];
}
