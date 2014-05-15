<?php
/**
 * Created by Khairul
 * Date: 04/12/13
 */
namespace We2o\Component\Semaphore;

use Psr\Log\LoggerInterface;

class FileSemaphore
{
    /** @var  string */
    protected $fileDirectory;

    /** @var int */
    protected $sleepTimeMicroseconds;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;


    public function __construct($fileDirectory, $sleepTimeMicroseconds, LoggerInterface $logger)
    {
        $this->sleepTimeMicroseconds = $sleepTimeMicroseconds;
        $this->logger = $logger;
        $this->fileDirectory = $fileDirectory;

        if (!is_dir($this->fileDirectory)) {
            if(!mkdir($this->fileDirectory, 0777, true)){
                throw new \RuntimeException;
            }

        }else{
            throw new \RuntimeException;
        }
    }

    protected function microSecondsTime()
    {
        $microtime = microtime(true);
        $microtime *= 1000000.0;
        $microtime = intval($microtime);

        return $microtime;
    }

    protected function getFileName($key)
    {
        return $this->fileDirectory.'/'.$key.".csv";
    }

    /**
     * @param $locked
     * @param null $microtime
     * @return string
     */
    protected function generateFileContent($locked, $microtime = null)
    {
        if (is_null($microtime)) {
            $microtime = $this->microSecondsTime();
        }
        $lockedString = $locked ? '1' : '0';
        return $lockedString.';'.$microtime;
    }

    protected function readFile($fp)
    {
        $content = fread($fp, 100);
        $tab = explode(";", $content);

        return array(
            "locked" => ($tab[0] == "1"),
            "microtime" => (array_key_exists('1', $tab)) ? $tab[1]:$this->microSecondsTime()
        );
    }
    protected function writeAndClose($fp, $content)
    {
        ftruncate($fp, 0);    //Truncate the file to 0
        rewind($fp);
        fwrite($fp, $content);    //Write the new Hit Count
        fflush($fp);
        flock($fp, LOCK_UN);    //Unlock File
        fclose($fp);
    }
    public function deleteFile($key)
    {
        @unlink($this->getFileName($key));
    }

    /**
     * @inheritdoc
     */
    public function acquire($key)
    {
        $pid = getmypid();
        $locked = true;
        $this->logger->debug("[$pid] acquire requested, key=$key");
        // get file pointer
        if (!is_file($this->getFileName($key))) {
            file_put_contents($this->getFileName($key), $this->generateFileContent(true));
            $this->logger->debug("[$pid] aquire obtained loopCount=0, key=$key");
            return true;
        }
        $loopCount = 0;

        while ($locked == true) {
            $fp = fopen($this->getFileName($key), "r+");
            if (!flock($fp, LOCK_EX)) {
                throw new \RuntimeException("semaphore, [$pid] flock failed");
            }
            $content = $this->readFile($fp);
            $locked = $content["locked"];
            $microtime = $content["microtime"];

            if (true == $locked) {
                $backtraceList = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $backtrace = $backtraceList[0];
                $now = new \DateTime();
                $this->logger->warning("[$pid] Dead lock detected, loopCount=$loopCount , at ".$now->format(DATE_RFC2822)." in ".$backtrace["file"].'('.$backtrace["line"].')');

                $this->writeAndClose($fp, $this->generateFileContent(true));
                return false;

            } elseif ($locked == false) {
                $this->writeAndClose($fp, $this->generateFileContent(true));
                $this->logger->debug("[$pid] aquire obtained, loopCount=$loopCount, key=$key");
                return true;
            }
            flock($fp, LOCK_UN);    //Unlock File
            fclose($fp);
            $loopCount ++;
            usleep($this->sleepTimeMicroseconds);
        }
    }

    /**
     * @inheritdoc
     */
    public function release($key)
    {
        $pid = getmypid();
        $fp = fopen($this->getFileName($key), "r+");
        if (!flock($fp, LOCK_EX)) {
            throw new \RuntimeException("kitpages_semaphore, [$pid] flock failed");
        }
        $content = $this->readFile($fp);
        $locked = $content["locked"];
        if (!$locked) {
            $backtraceList = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $backtrace = $backtraceList[0];
            $now = new \DateTime();
            $this->logger->warning("[$pid] Realease requested, but semaphore not locked, at ".$now->format(DATE_RFC2822)." in ".$backtrace["file"].'('.$backtrace["line"].')');
        }
        $this->writeAndClose($fp, $this->generateFileContent(false));
        $this->deleteFile($key);
        $this->logger->debug("[$pid] release ok, key=$key");
        return;
    }
}