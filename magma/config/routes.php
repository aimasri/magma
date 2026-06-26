<?php
use App\controllers\HomeController;

return [
    ['GET', '/', [HomeController::class, 'index']],
];
