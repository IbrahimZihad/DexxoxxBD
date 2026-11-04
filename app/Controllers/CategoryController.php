<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Repositories\CategoryRepository;

/**
 * The category directory. Browsing inside a shelf is CatalogController's job;
 * this page exists so shoppers and search engines can see the whole tree.
 */
final class CategoryController extends Controller
{
    public function index(): Response
    {
        return $this->view('categories.index', [
            'tree' => (new CategoryRepository())->tree(),
        ]);
    }
}
