<?php
namespace App\controllers;

use Magma\controllers\BaseController;
use Magma\http\RedirectResponse;

/**
 * Title: Home Controller
 *
 * Purpose:
 * - Handles requests to the application's root landing page.
 * - Bridges the routing mechanism to the presentation layer.
 *
 * Why / Why this design:
 * - Follows the MVC (Model-View-Controller) paradigm, keeping routing simple and offloading 
 *   view assembly to dedicated classes.
 *
 * Teaching notes:
 * - Controllers should serve only as HTTP traffic directors. They collect input, invoke domain logic, 
 *   and return a response, but should not contain complex business rules.
 */
class HomeController extends BaseController
{
    /**
     * Renders the welcome page for incoming requests.
     *
     * 1. Defines template variables (e.g., the page title).
     * 2. Delegates the rendering process to the base controller's `render` method.
     * 3. Returns the formulated HTTP response to the client.
     *
     * Logic behind the logic:
     * - Supplying `null` for the layout explicitly communicates that this specific view 
     *   is standalone and does not inherit from the global UI shell.
     *
     * @return \Magma\http\Response
     */
    public function index()
    {
        return $this->render('welcome', [
            'title' => 'Welcome to Magma Framework'
        ], null); // Pass null as layout since we don't have a layout system setup in welcome.php
    }
}
