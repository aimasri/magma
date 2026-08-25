<?php

declare(strict_types=1);

namespace Magma\pipeline;

use Closure;
use RuntimeException;

/**
 * Title: Generic Dual-Mode Pipeline Processor
 * 
 * Purpose:
 * - Pass an object (the "passable", such as an HTTP Request or Command) through a series of sequential 
 *   stages (the "pipes", such as Middleware or Guards) before reaching a final core destination handler.
 * - Flexibly handle both functional closures/callables (`$pipe($passable, $next)`), object-based 
 *   pipeline stages (`$pipe->process($passable, $next)` or `$pipe->handle($passable, $next)`), 
 *   and PSR-15 adapted middleware components.
 * 
 * Why / Why this design:
 * - Implements the Onion Architecture / Chain of Responsibility pattern.
 * - Decouples request processing, authentication, validation, and rate-limiting from core routing 
 *   and controller dispatch logic.
 * - Strict typing and dual-mode dispatch eliminate runtime `TypeError` exceptions when closures or 
 *   diverse middleware implementations are composed together.
 * 
 * Teaching notes:
 * - Functional composition via `array_reduce` builds nested closures from the innermost destination outward.
 * - When invoked, execution flows sequentially inward through each layer, executes the core destination, 
 *   and returns outward through the same layers, allowing pre-processing and post-processing.
 */
class Pipeline
{
    /**
     * The payload object being transported through the pipeline.
     */
    private mixed $passable = null;

    /**
     * Ordered list of pipes/middleware to pass the payload through.
     * @var array<int, object|callable|string>
     */
    private array $pipes = [];

    /**
     * Method name to invoke on object-based pipes.
     */
    private string $method = 'process';

    /**
     * Sets the object being sent through the pipeline.
     *
     * Execution Flow:
     * 1. Clone the current pipeline instance to preserve immutability.
     * 2. Store the passable payload on the cloned instance.
     * 3. Return the clone.
     *
     * Logic behind the logic:
     * - Immutability via cloning prevents race conditions or shared state mutation when 
     *   reusing pipeline templates across concurrent requests or asynchronous workers.
     *
     * @param mixed $passable The request, command, or context object.
     * @return self
     */
    public function send(mixed $passable): self
    {
        $clone = clone $this;
        $clone->passable = $passable;
        return $clone;
    }

    /**
     * Sets the array of pipes or middleware layers.
     *
     * Execution Flow:
     * 1. Clone the current pipeline instance.
     * 2. Store the pipes array on the cloned instance.
     * 3. Return the clone.
     *
     * Logic behind the logic:
     * - Allows dynamic middleware composition per route, controller, or command bus dispatch.
     *
     * @param array<int, object|callable|string> $pipes List of middleware stages.
     * @return self
     */
    public function through(array $pipes): self
    {
        $clone = clone $this;
        $clone->pipes = $pipes;
        return $clone;
    }

    /**
     * Sets the method name to invoke on object-based pipes.
     *
     * Execution Flow:
     * 1. Clone the current pipeline instance.
     * 2. Set the custom method name.
     * 3. Return the clone.
     *
     * Logic behind the logic:
     * - Enables the pipeline to process standard 'process', 'handle', or custom domain methods.
     *
     * @param string $method Method name to invoke.
     * @return self
     */
    public function via(string $method): self
    {
        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }

    /**
     * Executes the pipeline and resolves with the final destination callback.
     * 
     * Execution Flow:
     * 1. Reverse the pipes list so array_reduce wraps the innermost handler first.
     * 2. Construct the nested closure stack using getSlice().
     * 3. Execute the outermost closure with the initial passable payload and return the result.
     * 
     * Logic behind the logic:
     * - array_reduce elegantly folds the list of middlewares into a single callable onion stack.
     * 
     * @param callable $destination Core handler executed after all pipes pass.
     * @return mixed The final response or result bubbling out of the pipeline.
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
     * Creates a closure representing a single layer slice of the onion architecture.
     *
     * Execution Flow:
     * 1. Receive the next inner callable and the current pipe stage.
     * 2. Return a closure accepting $passable.
     * 3. When called:
     *    a. If pipe is callable / closure, execute $pipe($passable, $next).
     *    b. If pipe is an object with the configured method ($this->method), execute $pipe->{$this->method}($passable, $next).
     *    c. If pipe is an object with 'handle' method, execute $pipe->handle($passable, $next).
     *    d. If pipe has '__invoke', execute $pipe($passable, $next).
     *    e. Otherwise throw RuntimeException.
     *
     * Logic behind the logic:
     * - Dual-mode dispatch eliminates fragile type errors, seamlessly bridging PSR-15 objects, 
     *   Magma MiddlewareInterface objects, and inline PHP closures into a unified execution pipeline.
     *
     * @return Closure
     */
    private function getSlice(): Closure
    {
        return function (callable $next, object|callable $pipe): Closure {
            return function (mixed $passable) use ($next, $pipe): mixed {
                if (is_callable($pipe)) {
                    return $pipe($passable, $next);
                }

                if (is_object($pipe)) {
                    if (method_exists($pipe, $this->method)) {
                        return $pipe->{$this->method}($passable, $next);
                    }
                    if (method_exists($pipe, 'handle')) {
                        return $pipe->handle($passable, $next);
                    }
                }

                $type = is_object($pipe) ? get_class($pipe) : gettype($pipe);
                throw new RuntimeException("Pipeline stage [{$type}] does not have a valid execution method ({$this->method} or handle) and is not callable.");
            };
        };
    }
}
