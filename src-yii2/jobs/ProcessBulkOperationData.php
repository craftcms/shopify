<?php

namespace craft\shopify\jobs;

use craft\queue\BaseJob;
use CraftCms\Shopify\BulkOperation\Jobs\ProcessBulkOperationData as ProcessBulkOperationDataJob;
use CraftCms\Shopify\BulkOperation\Models\BulkOperation;

/**
 * Hands a job queued under the old class name over to the native job.
 *
 * Shopify 9.x development builds queued this job through the Yii 2 adapter, which wraps it in a job that only
 * accepts legacy jobs, so it can't be a `class_alias`. It keeps the old public properties so those payloads,
 * and failed jobs that are retried, still unserialize. When it runs, it dispatches the native job from the
 * same batch.
 *
 * @deprecated in 9.0.0. Use {@see ProcessBulkOperationDataJob} instead.
 */
class ProcessBulkOperationData extends BaseJob
{
    public string $bulkOperationShopifyGid = '';

    public string $dataUrl = '';

    public int $objectCount = 0;

    public ?string $tempFilePath = null;

    public string $clearData = BulkOperation::CLEAR_DATA_NONE;

    public ?int $dataFilePosition = null;

    public int $batchSize = 100;

    public int $batchIndex = 0;

    public int $itemOffset = 0;

    public ?int $priority = null;

    public ?int $ttr = null;

    public function execute($queue): void
    {
        $job = new ProcessBulkOperationDataJob(
            $this->bulkOperationShopifyGid,
            $this->dataUrl,
            $this->objectCount,
            $this->clearData,
        );

        $job->batchSize = $this->batchSize;
        $job->batchIndex = $this->batchIndex;
        $job->itemOffset = $this->itemOffset;
        $job->tempFilePath = $this->tempFilePath;
        $job->dataFilePosition = $this->dataFilePosition;

        if ($this->ttr !== null) {
            $job->timeout = $this->ttr;
        }

        dispatch($job);
    }
}
