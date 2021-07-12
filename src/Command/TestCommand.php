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
        $maxRecords = $oaiPmhApi['max_records'];
        $recordData = array();

        try {
            $oaiPmhEndpoint = Endpoint::build($oaiPmhApi['url']);
            $allRecords = $oaiPmhEndpoint->listRecords($oaiPmhApi['metadata_prefix']);

            foreach($languages as $language) {
//                echo 'Language ' . $language . ':' . PHP_EOL;
                $i = 0;

                foreach($allRecords as $record) {
                    $arr = array();
                    $data = $record->metadata->children($this->namespace, true);

                    foreach ($datahubFields as $key => $xpathRaw) {
                        $xpath = $this->buildXpath($xpathRaw, $language);
                        $res = $data->xpath($xpath);
                        if ($res) {
                            foreach ($res as $resChild) {
                                $str = (string)$resChild;
                                if($key == 'thumbnail') {
                                    $arr[] = $str;
                                    $str .= '/full/500,/0/default.jpg';
                                }
                                $arr[] = $str;
//                                echo $key . ': ' . $resChild . PHP_EOL;
                            }
                        }
                    }
                    if(!empty($arr)) {
                        $recordData[] = $arr;
                    }
//                    echo PHP_EOL;

                    // Break after X records
                    $i++;
                    if($maxRecords > 0 && $i == $maxRecords) {
//                        echo PHP_EOL;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            echo $e . PHP_EOL;
        }
//        var_dump($recordData);
        $fp = fopen('manifests.csv', 'w');
        fputcsv($fp, array('inventarisnummer', 'iiif_manifest', 'image', 'thumbnail'));

// Loop through file pointer and a line
        foreach ($recordData as $fields) {
            fputcsv($fp, $fields);
        }

        fclose($fp);

        return 0;
    }

    // Builds an xpath-expression based on the provided namespace (there are probably cleaner solutions)
    private function buildXpath($xpath, $language)
    {
        // We use {language} as a placeholder for the language in which we want to have our data
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
