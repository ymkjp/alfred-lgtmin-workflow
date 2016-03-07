<?php

require_once('workflows.php');

/**
 * Class Lgtm
 * @see http://alfredworkflow.readthedocs.org/
 */
class Lgtm
{
    const USAGE = "Usage: %s
		--async-store-cache\tCache data
		--skip-using-cache\tIgnore cached data";

    const BUNDLE_ID = 'com.xn--nyqr7s4vc72p.lgtm-workflow';
    const CACHE_INFO_FILENAME = 'e2ec0803935f4a8ae665475b0f59e56225d3ab92.json';
    const CACHE_IMAGE_FILENAME = '0e76292794888d4f1fa75fb3aff4ca27c58f56a6';

    const URL = 'http://www.lgtm.in/g';
    const RUNTIME = 'php';
    public static $_HTTP_OPTS = [
        'http' => [
            'method' => "GET",
            'header' => "Accept:application/json, text/javascript"
        ],
    ];
    protected static $_CLI_OPT;
    protected static $_CLI_ARGV;

    protected $_wf;
    protected $_context;

    public function __construct(Array $argv = null)
    {
        self::_initOptions($argv);
        $this->_wf = new Workflows(self::BUNDLE_ID);
        $this->_context = stream_context_create(self::$_HTTP_OPTS);
    }

    protected static function _initOptions(Array $argv = null)
    {
        self::$_CLI_OPT = $opts = getopt('h', ['help', 'skip-using-cache', 'async-store-cache']);
        self::$_CLI_ARGV = $argv;
        if (isset($opts['h']) || isset($opts['help'])) {
            $message = sprintf(self::USAGE, $argv[0]);
            echo "$message\n";
            die(0);
        }
    }

    /**
     * @param $argv
     * @return int  0: Succeed
     */
    static function main(Array $argv = null)
    {
        return (new self($argv))->_run();
    }

    protected function _run()
    {
        if (self::hasCliOption('async-store-cache')) {
            $next_data = $this->fetchData($assoc = true);
            $is_cached = $this->refreshCache($next_data, self::CACHE_INFO_FILENAME);
            $image_url = $next_data['actualImageUrl'];
            $raw_image = $this->_wf->request($image_url);
            $ext = self::extractExtension($image_url);
            if ($raw_image !== false && !empty($ext)) {
                $this->refreshCache($raw_image, self::CACHE_IMAGE_FILENAME . ".$ext");
            }
            return $is_cached ? 0 : 1;
        } else {
            echo $this->showResult();
            self::execAsync(['--async-store-cache']);
        }
        return 0;
    }

    protected function refreshCache($data, $filename)
    {
        $this->_wf->delete($filename);
        return $this->_wf->write($data, $filename);
    }

    protected static function extractExtension($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        return array_pop(explode('.', $path));
    }

    protected static function execAsync($options)
    {
        $command = sprintf('%s %s %s 2>&1 /dev/null &', self::RUNTIME, self::$_CLI_ARGV[0], join(' ', $options));
        return exec($command);
    }

    protected static function hasCliOption($key = '')
    {
        return array_key_exists($key, self::$_CLI_OPT);
    }

    protected function showResult()
    {
        $cache_data = self::hasCliOption('skip-using-cache') ? null : $this->_wf->read(self::CACHE_INFO_FILENAME);
        if ($cache_data) {
            $this->pushResult($cache_data);
        } else {
            $raw_data = $this->fetchData();
            $this->pushResult($raw_data);
        }
        return $this->_wf->toxml();
    }

    protected function fetchData($assoc = false, $url = self::URL, $context = null)
    {
        $context = isset($context) ? $context : $this->_context;
        $raw_data = file_get_contents($url, false, $context);
        return json_decode($raw_data, $assoc);
    }

    protected function generateImagePath($image_url)
    {
        $ext = self::extractExtension($image_url);
        $filename = self::CACHE_IMAGE_FILENAME . ".$ext";
        $file_available = $this->_wf->read($filename);
        $image_path = $file_available === false ? null : $this->_wf->data() . '/' . $filename;
        return $image_path;
    }

    protected function pushResult($data)
    {
        $json = is_string($data) ? json_decode($data) : $data;
        $subtitle = join(' ', [
            "Likes:{$json->likes}",
            "Dislikes:{$json->dislikes}",
            "Impressions:{$json->impressions}",
            "Credits:{$json->credits}",
        ]);
        $image_path = $this->generateImagePath($json->actualImageUrl);

        # Markdown's image syntax
        $this->_wf->result(
            $json->id,
            "![{$json->imageUrl}]({$json->imageUrl})",
            "![{$json->imageUrl}]({$json->imageUrl})",
            $subtitle,
            $image_path
        );

        # Raw image url
        $this->_wf->result(
            $json->id,
            $json->imageUrl,
            $json->imageUrl,
            $subtitle,
            $image_path
        );
    }
}

if (isset($argv[0]) && __FILE__ === realpath($argv[0])) {
    exit(Lgtm::main($argv));
}


