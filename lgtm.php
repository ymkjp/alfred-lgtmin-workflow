<?php

require_once './workflows.php';

/**
 * Class Lgtm
 * @see http://alfredworkflow.readthedocs.org/
 */
class Lgtm
{
    const USAGE = "Usage: %s";

    const BUNDLE_ID = 'com.xn--nyqr7s4vc72p.lgtm-workflow';
    const CACHE_IMAGE_FILENAME = '%d.%s';

    const URL = 'http://www.lgtm.in/g';
    static $HTTP_OPTS = [
        'http' => [
            'method' => "GET",
            'header' => "Accept:application/json, text/javascript"
        ],
    ];
    static $ALLOWED_EXTENSIONS = ['png', 'jpeg', 'jpg', 'gif', 'agif'];
    protected static $_CLI_OPT;
    protected static $_CLI_ARGV;

    protected $_wf;
    protected $_context;

    public function __construct(Array $argv = null)
    {
        self::_initOptions($argv);
        $this->_wf = new Workflows(self::BUNDLE_ID);
        $this->_context = stream_context_create(self::$HTTP_OPTS);
    }

    protected static function _initOptions(Array $argv = null)
    {
        self::$_CLI_OPT = $opts = getopt('h', ['help']);
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
        $raw_data = $this->fetchData();
        $image_url = $raw_data->actualImageUrl;
        $raw_image = $this->_wf->request($image_url);
        if ($raw_image !== false) {
            $this->storeData($raw_image, $this->generateImageFilename($raw_data->id, $image_url));
        }
        $this->pushResult($raw_data);
        echo $this->_wf->toxml();
        return 0;
    }

    protected function storeData($data, $filename)
    {
        return $this->_wf->write($data, $filename);
    }

    protected static function generateImageFilename($id, $image_url)
    {
        $ext = self::extractExtension($image_url);
        $filename = in_array($ext, self::$ALLOWED_EXTENSIONS, $strict = true)
            ? sprintf(self::CACHE_IMAGE_FILENAME, $id, $ext)
            : '';
        return $filename;
    }

    protected static function extractExtension($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        return array_pop(explode('.', $path));
    }

    protected static function hasCliOption($key = '')
    {
        return array_key_exists($key, self::$_CLI_OPT);
    }

    protected function fetchData($assoc = false, $url = self::URL, $context = null)
    {
        $context = isset($context) ? $context : $this->_context;
        $raw_data = file_get_contents($url, false, $context);
        return json_decode($raw_data, $assoc);
    }

    protected function generateImagePath($id, $image_url)
    {
        $filename = $this->generateImageFilename($id, $image_url);
        $file_available = $this->_wf->read($filename);
        $image_path = $file_available === false ? null : $this->_wf->data() . '/' . $filename;
        return $image_path;
    }

    protected function pushResult($data)
    {
        $json = is_string($data) ? json_decode($data) : $data;
        $emoticon_1F44E = json_decode('"\uD83D\uDC4D"');
        $emoticon_1F44D = json_decode('"\uD83D\uDC4E"');
        $emoticon_1F602 = json_decode('"\uD83D\uDE02"');
        $emoticon_1F44F = json_decode('"\uD83D\uDC4F"');

        $subtitle = join("  ", [
            "$emoticon_1F44E:{$json->likes}",
            "$emoticon_1F44D:{$json->dislikes}",
            "$emoticon_1F602:{$json->impressions}",
            "$emoticon_1F44F:{$json->credits}",
        ]);
        $image_path = $this->generateImagePath($json->id, $json->actualImageUrl);

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


