<?php declare(strict_types=1);

namespace Statistics\Controller;

use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\Controller\AbstractActionController;

/**
 * The download controller class.
 *
 * Count direct download of a file.
 *
 * @see \Access\Controller\AccessFileController
 */
class DownloadController extends AbstractActionController
{
    /**
     * @var string
     */
    protected $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Forward to action "file".
     *
     * @see self::filesAction()
     */
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'file';
        return $this->forward()->dispatch('Statistics\Controller\Download', $params);
    }

    /**
     * Check file and prepare it to be sent.
     *
     * Unlike Omeka Classic, the current file is already logged in main event
     * "view.layout" (see main Module and HitAdapter::currentRequest()).
     * So, just send the file.
     *
     * @todo Manage other storage than local one.
     */
    public function fileAction()
    {
        $params = $this->params()->fromRoute();
        $storageType = $params['type'] ?? '';
        $filename = $params['filename'] ?? '';
        $filepath = sprintf('%s/%s/%s', $this->basePath, $storageType, $filename);
        $result = $this->sendFile($filepath);
        if (empty($result)) {
            return $this->notFoundAction();
        }
        // Return response to avoid default view rendering and to manage events.
        // Since there is no more event in Omeka, the url is logged directly.
        return $result;
    }

    /**
     * Send file as stream. The file should exist.
     *
     * @todo Use Laminas stream response.
     *
     * @see \Access\Controller\AccessFileController::sendFile()
     * @see \DerivativeMedia\Controller\IndexController::sendFile()
     * @see \Statistics\Controller\DownloadController::sendFile()
     * and
     * @see \ImageServer\Controller\ImageController::fetchAction()
     */
    protected function sendFile(string $filepath): ?HttpResponse
    {
        // A security. Don't check the realpath to avoid issue on some configs.
        if (strpos($filepath, '../') !== false
            || !file_exists($filepath)
            || !is_readable($filepath)
        ) {
            return null;
        }

        $filesize = filesize($filepath);
        if (!$filesize) {
            return null;
        }

        $filename = pathinfo($filepath, PATHINFO_BASENAME);

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mediaType = $finfo->file($filepath);
        $mediaType = \Omeka\File\TempFile::MEDIA_TYPE_ALIASES[$mediaType] ?? $mediaType;

        // Everything has been checked.
        $dispositionMode = 'inline';

        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $this->getResponse();

        // Write headers.
        $headers = $response->getHeaders()
            ->addHeaderLine(sprintf('Content-Type: %s', $mediaType))
            ->addHeaderLine(sprintf('Content-Disposition: %s; filename="%s"', $dispositionMode, $filename))
            ->addHeaderLine('Content-Transfer-Encoding: binary')
            // Use this to open files directly.
            // Cache for 30 days.
            ->addHeaderLine('Cache-Control: private, max-age=2592000, post-check=2592000, pre-check=2592000')
            ->addHeaderLine(sprintf('Expires: %s', gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT'))
            ->addHeaderLine('Accept-Ranges: bytes');

        // TODO Check for Apache XSendFile or Nginx: https://stackoverflow.com/questions/4022260/how-to-detect-x-accel-redirect-nginx-x-sendfile-apache-support-in-php
        // TODO Use Laminas stream response?
        // $response = new \Laminas\Http\Response\Stream();

        // Adapted from https://stackoverflow.com/questions/15797762/reading-mp4-files-with-php.
        $hasRange = !empty($_SERVER['HTTP_RANGE']);
        if ($hasRange) {
            // Start/End are pointers that are 0-based.
            $start = 0;
            $end = $filesize - 1;
            $matches = [];
            $result = preg_match('/bytes=\h*(?<start>\d+)-(?<end>\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches);
            if ($result) {
                $start = (int) $matches['start'];
                if (!empty($matches['end'])) {
                    $end = (int) $matches['end'];
                }
            }
            // Check valid range to avoid hack.
            $hasRange = ($start < $filesize && $end < $filesize && $start < $end)
                && ($start > 0 || $end < ($filesize - 1));
        }

        if ($hasRange) {
            // Set partial content.
            $response
                ->setStatusCode($response::STATUS_CODE_206);
            $headers
                ->addHeaderLine('Content-Length: ' . ($end - $start + 1))
                ->addHeaderLine("Content-Range: bytes $start-$end/$filesize");
        } else {
            $headers
                ->addHeaderLine('Content-Length: ' . $filesize);
        }

        // Fix deprecated warning in \Laminas\Http\PhpEnvironment\Response::sendHeaders() (l. 113).
        $errorReporting = error_reporting();
        error_reporting($errorReporting & ~E_DEPRECATED);

        // Send headers separately to handle large files.
        $response->sendHeaders();

        error_reporting($errorReporting);

        // Clears all active output buffers to avoid memory overflow.
        $response->setContent('');
        while (ob_get_level()) {
            ob_end_clean();
        }

        if ($hasRange) {
            $fp = @fopen($filepath, 'rb');
            $buffer = 1024 * 8;
            $pointer = $start;
            fseek($fp, $start, SEEK_SET);
            while (!feof($fp)
                && $pointer <= $end
                && connection_status() === CONNECTION_NORMAL
            ) {
                set_time_limit(0);
                echo fread($fp, min($buffer, $end - $pointer + 1));
                flush();
                $pointer += $buffer;
            }
            fclose($fp);
        } else {
            readfile($filepath);
        }

        // TODO Fix issue with session. See readme of module XmlViewer.
        ini_set('display_errors', '0');

        // Return response to avoid default view rendering and to manage events.
        return $response;
    }
}
