<?php

namespace App\Command;

use \Exception;
use Phpoaipmh\Endpoint;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TestCommand extends Command
{
    private $params;
    private $namespace;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName("app:test");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $oaiPmhApi = $this->params->get('oai_pmh_api');
        $this->namespace = $oaiPmhApi['namespace'];

        $datahubFields = $this->params->get('datahub_fields');
        $languages = $oaiPmhApi['languages'];

        try {
            $oaiPmhEndpoint = Endpoint::build($oaiPmhApi['url']);
            $allRecords = $oaiPmhEndpoint->listRecords($oaiPmhApi['metadata_prefix']);
            foreach($languages as $language) {
                echo 'Language ' . $language . ':' . PHP_EOL;
                $i = 0;
                foreach($allRecords as $record) {
                    $data = $record->metadata->children($this->namespace, true);
                    foreach ($datahubFields as $key => $xpathRaw) {
                        $xpath = $this->buildXpath($xpathRaw, $language);
                        $res = $data->xpath($xpath);
                        if ($res) {
                            foreach ($res as $resChild) {
                                echo $key . ': ' . $resChild . PHP_EOL;
                            }
                        }
                    }
                    echo PHP_EOL;

                    // Break after 5 records, we don't need all 8000 records for testing
                    $i++;
                    if($i == 5) {
                        echo PHP_EOL;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            echo $e . PHP_EOL;
        }

        return 0;
    }

    // Build the xpath based on the provided namespace (there are probably cleaner solutions)
    private function buildXpath($xpath, $language)
    {
        $xpath = str_replace('{language}', $language, $xpath);

        $xpath = str_replace('[@', '[@' . $this->namespace . ':', $xpath);
        $xpath = str_replace('[@' . $this->namespace . ':xml:', '[@xml:', $xpath);
        $xpath = preg_replace('/\[([^@])/', '[' . $this->namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\/([^\/])/', '/' . $this->namespace . ':${1}', $xpath);
        if(strpos($xpath, '/') !== 0) {
            $xpath = $this->namespace . ':' . $xpath;
        }
        $xpath = 'descendant::' . $xpath;
        return $xpath;
    }
}
