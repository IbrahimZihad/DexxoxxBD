<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Controller;
use App\Http\Response;
use App\Repositories\CategoryRepository;

final class CategoryController extends Controller
{
    public function index(): Response
    {
        return $this->json([
            'categories' => array_map(static fn (array $row) => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'icon' => (string) ($row['icon'] ?? ''),
                'product_count' => (int) ($row['product_count'] ?? 0),
            ], CategoryRepository::all()),
        ]);
    }
}
