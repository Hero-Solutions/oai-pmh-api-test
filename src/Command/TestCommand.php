<?php

namespace App\Command;

use \Exception;
use Phpoaipmh\Client;
use Phpoaipmh\Endpoint;
use Phpoaipmh\HttpAdapter\CurlAdapter;
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
        $overrideCertificateAuthorityFile = $this->params->get('override_certificate_authority');
        $sslCertificateAuthorityFile = $this->params->get('ssl_certificate_authority_file');

        $lastExpression = null;
        try {
            $curlAdapter = new CurlAdapter();
            if ($overrideCertificateAuthorityFile) {
                $curlOpts[CURLOPT_CAINFO] = $sslCertificateAuthorityFile;
                $curlOpts[CURLOPT_CAPATH] = $sslCertificateAuthorityFile;
            }
            $curlAdapter->setCurlOpts($curlOpts);
            $oaiPmhClient = new Client($oaiPmhApi['url'], $curlAdapter);
            $oaiPmhEndpoint = new Endpoint($oaiPmhClient);

            foreach ($datahubFields as $name => $field) {
                $lastExpression = $field['xpath'];
                echo $name  . ': ' . $field['record'] . PHP_EOL;
                $record = $oaiPmhEndpoint->getRecord($oaiPmhApi['id_prefix'] . $field['record'], $oaiPmhApi['metadata_prefix']);
                $data = $record->GetRecord->record->metadata->children($this->namespace, true);
                echo 'XPath without namespace:  ' . $field['xpath'] . PHP_EOL;
                $xpath = $this->buildXPath($field['xpath'], $this->namespace);
                echo 'XPath with namespace:     ' . str_replace('descendant::', '', $xpath) . PHP_EOL;
                echo 'Data:' . PHP_EOL;
                $res = $data->xpath($xpath);
                if ($res) {
                    foreach ($res as $resChild) {
                        $str = (string)$resChild;
                        echo '    ' . $str . PHP_EOL;
                    }
                }
                echo PHP_EOL;
            }
        } catch(Exception $e) {
            echo PHP_EOL;
            echo $lastExpression . PHP_EOL;
            echo $e . PHP_EOL;
        }

        return 0;
    }

    // Builds an xpath-expression based on the provided namespace (there are probably cleaner solutions)
    private function buildXPath($xpath, $namespace)
    {
        $prepend = '';
        if(strpos($xpath, '(') === 0) {
            $prepend = '(';
            $xpath = substr($xpath, 1);
        }
        $xpath = preg_replace('/\[@(?!xml|text)/', '[@' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\(@(?!xml|text)/', '(@' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\[(?![@0-9]|not\(|text)/', '[' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\/@/', '/@' . $namespace . ':', $xpath);
        $xpath = preg_replace('/\/([^@\/])/', '/' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/ and @(?!xml)/', ' and @' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/ and (?!@|@xml)/', ' and ' . $namespace . ':${1}', $xpath);
        if(strpos($xpath, '/') !== 0) {
            $xpath = $namespace . ':' . $xpath;
        }
        $xpath = 'descendant::' . $xpath;
        $xpath = $prepend . $xpath;
        return $xpath;
    }
}
