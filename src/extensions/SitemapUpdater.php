<?php

/**
 *
 * A simple {@link SiteTreeExtension} that invokes a {@link QueuedJob} to
 * physically generate and update a sitemap.xml file on the filesystem.
 *
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @author Russell Michell 2023 http://theruss.com
 * @package silverstripe-googlesitemapautoupdate
 */

namespace DeviateLtd\Silverstripe\SitemapUpdater;

use SilverStripe\CMS\Model\SiteTreeExtension;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Jobs\GenerateGoogleSitemapJob;

class SitemapUpdater extends SiteTreeExtension
{
    /**
     *
     * Create a new {@link GenerateGoogleSitemapJob} after each CMS write manipulation.
     *
     * @param {@inheritdoc}
     * @return mixed void | null
     */
    public function onAfterPublish(&$original)
    {
        if (!class_exists('AbstractQueuedJob')) {
            return;
        }

        // Get all "running" GenerateGoogleSitemapJob's
        $list = QueuedJobDescriptor::get()->filter([
            'Implementation' => GenerateGoogleSitemapJob::class,
            'JobStatus' => [QueuedJob::STATUS_INIT, QueuedJob::STATUS_RUN]
        ]);
        $existingJob = $list ? $list->first() : null;

        if ($existingJob && $existingJob->exists()) {
            // Do nothing. There's a job for generating the sitemap already running
        } else {
            $where = '"StartAfter" > \'' . date('Y-m-d H:i:s') . '\'';
            $list = QueuedJobDescriptor::get()->where($where);
            $list = $list->filter([
                'Implementation'=> GenerateGoogleSitemapJob::class,
                'JobStatus'        => [QueuedJob::STATUS_NEW],
            ]);
            $list = $list->sort('ID', 'ASC');

            if ($list && $list->count()) {
                // Execute immediately
                $existingJob = $list->first();
                $existingJob->StartAfter = date('Y-m-d H:i:s');
                $existingJob->write();

                return;
            }

            // If no such a job existing, create a new one for the first time, and run immediately
            // But first remove all legacy jobs which might be of the following statuses:
            /**
             * New (but Start data somehow is less than now)
             * Waiting
             * Completed
             * Paused
             * Cancelled
             * Broken
             */
            $list = QueuedJobDescriptor::get()->filter([
                'Implementation' => GenerateGoogleSitemapJob::class,
            ]);

            if ($list && $list->count()) {
                $list->removeAll();
            }

            $job = new GenerateGoogleSitemapJob();
            singleton(QueuedJobService::class)->queueJob($job);
        }
    }
}
