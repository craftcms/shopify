<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\shopify\Plugin;
use Shopify\Exception\ShopifyException;
use yii\console\ExitCode;

/**
 * Run queries against the Shopify GraphQL Admin API.
 *
 * @link https://shopify.dev/docs/api/admin-graphql/2025-10
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0
 */
class ApiController extends Controller
{
    /** @var string $defaultAction */
    public $defaultAction = 'query';

    /**
     * Send the provided GraphQL query string to the Shopify API.
     *
     * @param string $gql GraphQL fragment to send. Variables are not supported!
     */
    public function actionQuery(string $gql): int
    {
        // Record how long the API service is:
        $start = microtime(true);

        $err = null;
        $data = null;

        try {
            $this->stdout("Running query... ");

            $data = Plugin::getInstance()->getApi()->query($gql);
        } catch (ShopifyException $e) {
            $err = $e->getMessage();
        }

        $this->stdout("done!", $err ? Console::FG_RED : Console::FG_GREEN);

        // Report timing:
        $this->stdout(sprintf(' (%fs)', microtime(true) - $start), Console::FG_GREY);
        $this->stdout(PHP_EOL);

        if ($err) {
            $this->failure("There was a problem with the query: " . $err);

            return ExitCode::UNAVAILABLE;
        }

        $print = Craft::dump(
            $data,
            highlight: false,
            return: true,
        );

        $this->stdout('Response:' . PHP_EOL . $print . PHP_EOL);

        return ExitCode::OK;
    }
}
