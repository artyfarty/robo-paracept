<?php
namespace Codeception\Task;

use Robo\Common\TaskIO;
use Robo\Contract\TaskInterface;
use Robo\Exception\TaskException;

trait MergeReports
{
    protected function taskMergeXmlReports($src = [])
    {
        return new MergeXmlReportsTask($src);
    }
}

class MergeXmlReportsTask implements TaskInterface
{
    use TaskIO;

    protected $src = [];
    protected $dst;
    protected $summarizeTime = true;

    public function __construct($src = [])
    {
        $this->src = $src;
    }

    public function sumTime()
    {
        $this->summarizeTime = true;
    }

    public function maxTime()
    {
        $this->summarizeTime = false;
    }

    public function from($fileName)
    {
        if (is_array($fileName)) {
            $this->src = array_merge($fileName, $this->src);
        } else {
            $this->src[] = $fileName;
        }
        return $this;
    }

    public function into($fileName)
    {
        $this->dst = $fileName;
        return $this;
    }

    public function run()
    {
        if (!$this->dst) {
            throw new TaskException($this, "No destination file is set. Use `->into()` method to set result xml");
        }
        $this->printTaskInfo("Merging JUnit XML reports into {$this->dst}");
        $dstXml = new \DOMDocument();
        $dstXml->appendChild($dstXml->createElement('testsuites'));

        $resultNodes = [];

        foreach ($this->src as $src) {
            $this->printTaskInfo("Processing $src");
            
            $srcXml = new \DOMDocument();
            if (!file_exists($src)) {
                $this->printTaskInfo("<error>File $src could not be found</error>");
                continue;
            }
            $loaded = $srcXml->load($src);
            if (!$loaded) {
                $this->printTaskInfo("<error>File $src can't be loaded as XML</error>");
                continue;
            }
            $suiteNodes = (new \DOMXPath($srcXml))->query('//testsuites/testsuite');
            foreach ($suiteNodes as $suiteNode) {
                $suiteNode = $dstXml->importNode($suiteNode, true);
                /** @var $suiteNode \DOMElement  **/
                $suiteName = $suiteNode->getAttribute('name');
                if (!isset($resultNodes[$suiteName])) {
                    $resultNode = $dstXml->createElement("testsuite");
                    $resultNode->setAttribute('name', $suiteName);
                    $resultNodes[$suiteName] = $resultNode;
                }
                $this->mergeSuites($resultNodes[$suiteName], $suiteNode);
            }
        }

        foreach ($resultNodes as $suiteNode) {
            $dstXml->firstChild->appendChild($suiteNode);
        }
        $dstXml->save($this->dst);
        $this->printTaskInfo("File <info>{$this->dst}</info> saved. ".count($resultNodes).' suites added');

    }

    protected function mergeSuites(\DOMElement $resulted, \DOMElement $current)
    {
        foreach (['tests', 'assertions', 'failures', 'errors'] as $attr) {
            $sum = (int)$current->getAttribute($attr) + (int)$resulted->getAttribute($attr);
            $resulted->setAttribute($attr, $sum);
        }

        if ($this->summarizeTime) {
            $resulted->setAttribute('time', (float)$current->getAttribute('time') + (float)$resulted->getAttribute('time'));
        } else {
            $resulted->setAttribute('time', max($current->getAttribute('time'), $resulted->getAttribute('time')));
        }

        /** @var \DOMNode $node */
        foreach ($current->childNodes as $node) {
            $resulted->appendChild($node->cloneNode(true));
        }
    }
}
