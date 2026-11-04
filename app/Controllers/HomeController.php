<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;

/**
 * The dynamic homepage. All fifteen sections read from MySQL, so the CMS can
 * change what is featured without touching a template; the template owns those
 * repository calls because it is the only consumer of the data.
 */
final class HomeController extends Controller
{
    public function index(): Response
    {
        return $this->view('home');
    }
}
