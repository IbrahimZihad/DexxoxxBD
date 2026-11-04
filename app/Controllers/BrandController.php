<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Repositories\BrandRepository;

/**
 * The brand directory, grouped A to Z. A single brand's plans are listed by
 * CatalogController::brand().
 */
final class BrandController extends Controller
{
    public function index(): Response
    {
        return $this->view('brands.index', [
            'brands' => (new BrandRepository())->allWithCounts(120),
        ]);
    }
}
