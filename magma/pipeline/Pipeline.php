<?php

namespace Magma\pipeline;

use Magma\container\Container;

/**
 * Generic Pipeline Processor
 * 
 * Purpose:
 * - Pass an object (the "passable", like an HTTP Request) through a series of sequential 
 *   stages (the "pipes", like Middleware) before finally hitting a core destination handler.
 * 
 * Why / Why this design:
 * - The Onion architecture pattern perfectly encapsulates request processing. Extracting 
 *   this out of the Router completely resolves SRP violations, making the Router strictly 
 *   responsible for path matching, and making this Pipeline fully reusable for other 
 *   tasks (like queued jobs or command buses).
 * 
 * Teaching notes:
 * - The `array_reduce` function builds a nested set of closures from the inside out. 
 *   When the final pipeline is executed, the passable travels sequentially inward 
 *   through each layer of the onion, hits the destination, and then the responses 
 *   bubble back outward through the same layers.
 */
class Pipeline
{
    private mixed $passable;
    private array $pipes = [];
    private string $method = 'process'; // Default method to call on pipes

    public function __construct()
    {
    }

    /**
     * Set the object being sent through the pipeline.
     *
     * Logic behind the logic:
     * - Stores the primary payload (e.g., an HTTP request) that will be mutated or inspected by each pipe.
     *
     * @param mixed $passable The object to pass through the pipeline.
     * @return self
     */
    public function send(mixed $passable): self
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * Set the array of pipes.
     *
     * Logic behind the logic:
     * - Accepts the layers (middleware/handlers) that the passable must travel through, allowing dynamic configuration of the pipeline.
     *
     * @param array $pipes The array of middleware/pipes.
     * @return self
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Set the method to call on the pipes.
     *
     * Logic behind the logic:
     * - Allows flexibility in the interface of the pipes. While 'process' or 'handle' are common, this makes the pipeline completely agnostic.
     *
     * @param string $method The method name to invoke on each pipe.
     * @return self
     */
    public function via(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Run the pipeline with a final destination callback.
     * 
     * Execution Flow:
     * 1. Reverses the array of pipes so the innermost pipe is processed first by `array_reduce`.
     * 2. Uses `array_reduce` and `getSlice()` to wrap each pipe around the destination callback.
     * 3. Executes the fully constructed "onion" with the initial `$passable` payload.
     * 
     * @param callable $destination The final core handler to execute.
     * @return mixed The final response bubbling back out of the pipeline.
     */
    public function then(callable $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->getSlice(),
            $destination
        );

        return $pipeline($this->passable);
    }

    /**
     * Get a Closure that represents a slice of the application onion.
     *
     * Execution Flow:
     * 1. Returns a closure that wraps the current pipe and the next closure.
     * 2. When executed, it calls the specified method on the current pipe, passing the passable payload and the next layer.
     *
     * Logic behind the logic:
     * - This creates the recursive nested structure necessary for the Onion architecture. Each slice acts as a wrapper around the core, allowing pre- and post-processing of the payload.
     *
     * @return \Closure
     */
    private function getSlice(): \Closure
    {
        return function (callable $next, object|callable $pipe) {
            return function (mixed $passable) use ($next, $pipe) {
                if (is_callable($pipe)) {
                    return $pipe($passable, $next);
                }
                return $pipe->{$this->method}($passable, $next);
            };
        };
    }
}
