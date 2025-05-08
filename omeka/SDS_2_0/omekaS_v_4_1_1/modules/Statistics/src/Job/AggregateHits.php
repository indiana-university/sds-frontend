<?php declare(strict_types=1);

namespace Statistics\Job;

use Omeka\Job\AbstractJob;

class AggregateHits extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();

        $logger = $services->get('Omeka\Logger');

        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Log');
        if ($module && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE) {
            $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
            $referenceIdProcessor->setReferenceId('statistics/aggregate_hits/job_' . $this->job->getId());
            $logger->addProcessor($referenceIdProcessor);
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');

        // Use direct sql requests because hits are generally numerous.
        $sqls = <<<SQL
# Remove existing stats.
TRUNCATE TABLE `stat`;

# Create stats for page (browser) and download (file).
INSERT INTO `stat` (
    `type`,
    `url`,
    `entity_id`,
    `entity_name`,
    `hits`,
    `hits_anonymous`,
    `hits_identified`,
    `created`,
    `modified`
)
SELECT DISTINCT
    IF(`url` LIKE "/files/%", "download", "page"),
    MIN(`url`),
    `entity_id`,
    `entity_name`,
    COUNT(`id`),
    SUM(CASE WHEN `user_id` < 1 THEN 1 ELSE 0 END),
    SUM(CASE WHEN `user_id` > 0 THEN 1 ELSE 0 END),
    MIN(`created`),
    MAX(`created`)
FROM `hit`
GROUP BY `url`
ORDER BY `created` ASC
;

# Create stats for resource (page or download).
INSERT INTO `stat` (
    `type`,
    `url`,
    `entity_id`,
    `entity_name`,
    `hits`,
    `hits_anonymous`,
    `hits_identified`,
    `created`,
    `modified`
)
SELECT DISTINCT
    "resource",
    MIN(`url`),
    `entity_id`,
    `entity_name`,
    COUNT(`id`),
    SUM(CASE WHEN `user_id` < 1 THEN 1 ELSE 0 END),
    SUM(CASE WHEN `user_id` > 0 THEN 1 ELSE 0 END),
    MIN(`created`),
    MAX(`created`)
FROM `hit`
GROUP BY `entity_name`, `entity_id`
ORDER BY `created` ASC
;
SQL;
        $connection->executeStatement($sqls);
    }
}
