<?php
/**
 *    ______            __             __
 *   / ____/___  ____  / /__________  / /
 *  / /   / __ \/ __ \/ __/ ___/ __ \/ /
 * / /___/ /_/ / / / / /_/ /  / /_/ / /
 * \______________/_/\__/_/   \____/_/
 *    /   |  / / /_
 *   / /| | / / __/
 *  / ___ |/ / /_
 * /_/ _|||_/\__/ __     __
 *    / __ \___  / /__  / /____
 *   / / / / _ \/ / _ \/ __/ _ \
 *  / /_/ /  __/ /  __/ /_/  __/
 * /_____/\___/_/\___/\__/\___/
 *
 */

namespace App\Http\Controllers;

use App\GraphQL;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class Category
{
    public function __invoke(string $urlKey)
    {
        $category = $this->getCategory($urlKey);
        $products = $this->getProducts($category['id']);

        $products = array_map(function ($data) { return \App\DTO\Product::fromGraphqlResponse($data);}, $products ?? []);

        return view('category', [
            'category' => $category,
            'products' => $products,
        ]);
    }

    private function getCategory(string $urlKey): array
    {
        return Cache::tags(['categories'])->remember($urlKey, 3600, function () use ($urlKey) {
            $query = <<<'QUERY'
                query($urlKey:String!) {
                    categories(filters: {url_key: {eq:$urlKey}}) {
                        items {
                            id
                            name
                        }
                    }
                }
QUERY;

            return Arr::get(
                GraphQL::query($query, ['urlKey' => $urlKey]),
                'data.categories.items.0'
            );
        });
    }

    private function getProducts($id)
    {
        $cache = Cache::tags(['categories', 'categories.products']);
        if ($cache->has($id)) {
            return $cache->get($id);
        }

        $query = <<<'QUERY'
            query($id:String!) {
                products(filter:{category_id:{eq:$id}}) {
                    aggregations {
                        attribute_code
                        count
                        label
                        options {
                            count
                            label
                            value
                        }
                    }
                    items {
                        PRODUCT_CONTENTS
                    }
                }
            }
QUERY;

        $result = Arr::get(
            GraphQL::query($query, ['id' => $id]),
            'data.products.items'
        );

        $cache->put($id . '.aggregations', Arr::get(
            GraphQL::query($query, ['id' => $id]),
            'data.products.aggregations'
        ));

        $cache->put($id, $result);

        return $result;
    }
}
