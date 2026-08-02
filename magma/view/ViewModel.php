<?php

namespace Magma\view;

/**
 * Title: View Model (Presenter) Base
 *
 * Purpose:
 * - Base class for view models/presenters.
 *
 * Why this design:
 * - View Model Pattern: Encapsulates complex display logic, formatting, and aggregation before it reaches the TemplateEngine.
 * - Separation of Concerns: Prevents the template layer from containing PHP logic (e.g., date formatting, complex conditionals), ensuring templates remain purely declarative.
 *
 * Teaching notes:
 * - Always pass domain data into the constructor of your ViewModel, do processing there, and expose only scalar data via `toArray()`.
 */
abstract class ViewModel
{
    /**
     * Converts the view model into an array for the template engine.
     *
     * @return array
     */
    abstract public function toArray(): array;
}
