<?php
namespace App\controllers;

use Magma\controllers\BaseController;
use Magma\http\RedirectResponse;

class HomeController extends BaseController
{
    public function index()
    {
        return $this->render('welcome', [
            'title' => 'Welcome to Magma Framework'
        ], null); // Pass null as layout since we don't have a layout system setup in welcome.php
    }
}
