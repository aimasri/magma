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
     */
    public function send(mixed $passable): self
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * Set the array of pipes.
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Set the method to call on the pipes.
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
     */
    private function getSlice(): \Closure
    {
        return function (callable $next, object|callable $pipe) {
            return function (mixed $passable) use ($next, $pipe) {
                return $pipe->{$this->method}($passable, $next);
            };
        };
    }
}
