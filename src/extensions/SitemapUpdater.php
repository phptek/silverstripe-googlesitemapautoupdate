<?php

namespace PhpTek\GoogleSiteMapUpdate;

use DNADesign\Elemental\TopPage\SiteTreeExtension;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Jobs\GenerateGoogleSitemapJob;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * A simple {@link SiteTreeExtension} that invokes a {@link QueuedJob} to
 * physically generate and update a sitemap.xml file on the filesystem.
 *
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @author Russell Michell 2024 <russ@theruss.com>
 * @package phptek/silverstripe-googlesitemapautoupdate
 */
class SitemapUpdater extends SiteTreeExtension
{
    /**
     * Create a new {@link GenerateGoogleSitemapJob} after each CMS write manipulation.
     *
     * @param {@inheritdoc}
     * @return mixed void|null
     */
    public function onAfterPublish(&$original): void
    {
        if (!class_exists(AbstractQueuedJob::class)) {
            return;
        }

        // Get all "running" GenerateGoogleSitemapJobs
        $list = QueuedJobDescriptor::get()->filter([
            'Implementation' => 'GenerateGoogleSitemapJob',
            'JobStatus'      => [QueuedJob::STATUS_INIT, QueuedJob::STATUS_RUN],
        ]);

        $existingJob = $list?->first();

        if ($existingJob && $existingJob->exists()) {
            // Do nothing. There's a job for generating the sitemap already running
        } else {
            $where = '"StartAfter" > \'' . date('Y-m-d H:i:s') . '\'';
            $list = QueuedJobDescriptor::get()->where($where);
            $list = $list->filter([
                'Implementation' => 'GenerateGoogleSitemapJob',
                'JobStatus'      => [QueuedJob::STATUS_NEW],
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
            singleton('QueuedJobService')->queueJob($job);
        }
    }
}
