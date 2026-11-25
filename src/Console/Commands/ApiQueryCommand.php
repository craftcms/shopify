<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Console\Commands;

use craft\shopify\exceptions\ShopifyApiException;
use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Shopify\Api\Api;
use Illuminate\Console\Command;
use Override;

class ApiQueryCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'shopify:api:query {gql : The GraphQL query to run against the Shopify Admin API}';

    #[Override]
    protected $description = 'Runs a GraphQL query against the Shopify Admin API.';

    #[Override]
    protected $aliases = ['shopify:api', 'shopify:api:query', 'shopify/api', 'shopify/api/query'];

    public function handle(Api $api): int
    {
        $start = microtime(true);

        try {
            $data = $api->query($this->argument('gql'));
        } catch (ShopifyApiException $e) {
            $this->components->error('There was a problem with the query: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Query ran in %fs.', microtime(true) - $start));
        dump($data);

        return self::SUCCESS;
    }
}
