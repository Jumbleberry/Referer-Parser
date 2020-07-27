<?php
namespace PhpSnowplowParser;

use \Snowplow\RefererParser\Config\ConfigReaderInterface;
use \Snowplow\RefererParser\Parser;

class SnowplowParser extends Parser
{
    public $additionalConfig;

    public function __construct(ConfigReaderInterface $configReader = null, array $internalHosts = [])
    {
        parent::__construct($configReader, $internalHosts);
        $this->additionalConfig = static::createAdditionalConfigReader();
    }

    public function getLeadSource($source)
    {
        $leadSource = $this->parseReferrerUrl($source);

        if (!$leadSource) {
            $leadSource = $this->parseUseragent($source);
        }
        return $leadSource;
    }

    public function parseReferrerUrl($source)
    {
        if ($source['page_url'] || $source['page_referrer']) {
            return parent::parse($source['page_referrer'], $source['page_url'])->getSource() ?? $this->parseUrlQuery($source['page_referrer'], $source['page_url']);
        }
    }

    public function parseUseragent($source)
    {
        if ($source['useragent']) {
            foreach ($this->additionalConfig as $key => $params) {
                if (isset($params['regex'])
                    && preg_match('/' . str_replace('/', '\/', str_replace('\/', '/', $params['regex'])) . '/i', $source['useragent'], $matches)
                ) {
                    return $key;
                }
            }
        }
    }

    protected function parseUrlQuery($refererUrl, $pageUrl)
    {
        $query1 = explode('&', parse_url($refererUrl, PHP_URL_QUERY));
        $query2 = explode('&', parse_url($pageUrl, PHP_URL_QUERY));

        $queryParams = array_map(function ($param) {
            return explode('=', $param)[0];
        }, array_merge($query1, $query2));

        foreach ($this->additionalConfig as $key => $params) {
            if (isset($params['identifiers']) && in_array($params['identifiers'], $queryParams)) {
                return $key;
            }
        }
    }

    protected static function createAdditionalConfigReader()
    {
        $file = file_get_contents('data/referers.json');
        return json_decode($file, true);
    }
}