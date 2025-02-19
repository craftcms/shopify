<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\shopify\Plugin;
use craft\web\Controller;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * SyncController class
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
class SyncController extends Controller
{
    /**
     * @return Response
     * @throws \Throwable
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws BadRequestHttpException
     */
    public function actionDelete(): Response
    {
        $this->requireAcceptsJson();
        $id = Craft::$app->getRequest()->getBodyParam('id');

        if (Plugin::getInstance()->getBulkOperations()->deleteBulkOperationById($id)) {
            return $this->asSuccess(Craft::t('shopify', 'Sync deleted'));
        }

        return $this->asFailure(Craft::t('shopify', 'Failed to delete sync'));
    }
}
