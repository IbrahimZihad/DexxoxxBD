<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ProductRepository;

/**
 * Read-only JSON used by the storefront's progressive enhancements: the header
 * type-ahead and, in later phases, live stock checks. Nothing here writes.
 */
final class ApiController extends Controller
{
    private ProductRepository $products;

    public function __construct()
    {
        $this->products = new ProductRepository();
    }

    /**
     * Type-ahead for the header search box. Images are stored relative to
     * public/assets, so they leave here as absolute URLs the browser can load.
     */
    public function suggest(Request $request): Response
    {
        $term = mb_substr(trim($request->string('q')), 0, 80);
        if (mb_strlen($term) < 2) {
            return $this->ok([]);
        }

        $limit = max(1, min(10, $request->integer('limit', 8)));
        $rows = [];

        foreach ($this->products->suggestions($term, $limit) as $row) {
            $image = (string) ($row['image'] ?? '');
            $rows[] = [
                'label' => (string) $row['label'],
                'url' => (string) $row['url'],
                'type' => (string) $row['type'],
                'image' => $image === '' ? '' : image_url($image),
            ];
        }

        return $this->ok($rows);
    }
}
