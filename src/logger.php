<?php
/**
 * this7 PHP Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright 2016-2018 Yan TianZeng<qinuoyun@qq.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://www.ub-7.com
 */
namespace this7\logger;
use \Exception;

/**
 * 日志管理
 */
class logger {
    /**
     * 单例实例
     * @var null
     */
    public static $instance = NULL;

    /**
     * 保存日志文件的路径
     * @var [type]
     */
    public $_log_path;

    /**
     * 日志级别
     * @var integer
     */
    public $_threshold = 0;

    /**
     * 记录错误阈值
     * @var array
     */
    public $_threshold_array = array();

    /**
     * 设置文件权限
     * @var integer
     */
    public $_file_permissions = 0644;

    /**
     * 日志文件的时间戳格式
     * @var string
     */
    public $_date_fmt = 'Y-m-d H:i:s';

    /**
     * 文件名前缀
     * @var string
     */
    public $_file_prefix = 'log-';

    /**
     * 文件扩展名
     * @var string
     */
    public $_file_ext = 'log';

    /**
     * 日志记录器是否可以写入日志文件
     * @var boolean
     */
    public $_enabled = TRUE;

    /**
     * 日志信息
     * @var array
     */
    public $log = [];

    /**
     * 预定义的日志级别
     * @var array
     */
    public $_levels = array(
        'ERROR'             => 1,
        'WARNING'           => 2,
        'SQL'               => 3,
        'PARSE'             => 4,
        'DEBUG'             => 5,
        'INFO'              => 6,
        'ALL'               => 7,
        'NOTICE'            => 8,
        'EXCEPTION'         => 9,
        'CORE_ERROR'        => 16,
        'CORE_WARNING'      => 32,
        'COMPILE_ERROR'     => 64,
        'COMPILE_WARNING'   => 128,
        'USER_ERROR'        => 256,
        'USER_WARNING'      => 512,
        'USER_NOTICE'       => 1024,
        'STRICT'            => 2048,
        'RECOVERABLE_ERROR' => 4096,
        'DEPRECATED'        => 8192,
        'USER_DEPRECATED'   => 16384,
    );

    public function __construct() {
        #如果输出日志被禁用，什么也不做
        if (!C('logger', 'EnableOutputLog')) {
            $this->_enabled = FALSE;
            return;
        }

        $this->_log_path        = ROOT_DIR . DS . C('logger', 'LogPath');
        $this->_threshold       = C('logger', 'LogThreshold');
        $this->_threshold_array = C('logger', 'LogThresholdArray');

        if (!is_dir($this->_log_path)) {
            mkdir($this->_log_path, 0755, TRUE);
        }
        if (!is_dir($this->_log_path) || !is_writable($this->_log_path)) {
            $this->_enabled = FALSE;
        }

        if (!empty($this->_threshold_array)) {
            $this->_threshold       = 0;
            $this->_threshold_array = array_flip($this->_threshold_array);
        }
    }

    public function __call($name, $arguments) {
        if (in_array($name, array('error', 'debug', 'info'))) {
            return $this->writeLog($name, $arguments);
        }

        throw new Exception("调用未定义的方法日志记录器::{$name}()", 1);
    }

    public function getInstance() {
        if (self::$instance === NULL) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function writeLog($level, $messages) {
        $result = array();
        if (is_array($messages)) {
            foreach ($messages as $key => $value) {
                $message = $value;
                if (is_array($message)) {
                    $message = to_json($message);
                }
                if (is_string($message) || is_numeric($message)) {
                    $result[] = $message;
                }
            }
        } else {
            if (is_string($messages) || is_numeric($messages)) {
                $result[] = $messages;
            }
        }
        $instance = $this->getInstance();
        $instance->write_log($level, implode(' ', $result) . "\n");
    }

    /**
     * 记录日志内容
     *
     * @param $message 错误
     * @param string $level 级别
     */
    public function record($message, $level = self::ERROR) {
        $this->log[] = date("[ c ]") . "{$level}: {$message}" . PHP_EOL;
    }

    /**
     * 公共克隆方法，以防止克隆*Singleton*实例的实例。
     * @Author   Sean       Yan
     * @DateTime 2018-08-31
     * @return   [type]     [description]
     */
    public function __clone() {}

    /**
     * ublic反序列化方法，以防止对*Singleton*实例的反序列化。
     * @Author   Sean       Yan
     * @DateTime 2018-08-31
     */
    public function __wakeup() {}

    /**
     * 写日志文件
     * @param  string $level 错误级别
     * @param  string $msg   错误消息
     * @return bool
     */
    public function write_log($level, $msg) {
        if ($this->_enabled === FALSE) {
            return FALSE;
        }

        $level = strtoupper($level);

        if (TRUE
            && (!isset($this->_levels[$level]) || $this->_levels[$level] > $this->_threshold)
            && !isset($this->_threshold_array[$this->_levels[$level]])
        ) {
            return FALSE;
        }

        $filepath = $this->_log_path . $this->_file_prefix . date('Y-m-d') . '.' . $this->_file_ext;

        $message = '';

        if (!file_exists($filepath)) {
            $newfile = TRUE;
        }

        if (!$fp = @fopen($filepath, 'ab')) {
            return FALSE;
        }

        flock($fp, LOCK_EX);

        #用微秒附加到初始日期实例化日期时间
        #是正确支持这种格式所必需的
        if (strpos($this->_date_fmt, 'u') !== FALSE) {
            $microtime_full  = microtime(TRUE);
            $microtime_short = sprintf('%06d', ($microtime_full - floor($microtime_full)) * 1000000);
            $date            = new DateTime(date('Y-m-d H:i:s.' . $microtime_short, $microtime_full));
            $date            = $date->format($this->_date_fmt);

        } else {
            $date = date($this->_date_fmt);
        }

        $message .= $this->_format_line($level, $date, $msg);

        for ($written = 0, $length = strlen($message); $written < $length; $written += $result) {
            if (($result = fwrite($fp, substr($message, $written))) === FALSE) {
                break;
            }
        }

        flock($fp, LOCK_UN);
        fclose($fp);

        if (isset($newfile) && $newfile === TRUE) {
            chmod($filepath, $this->_file_permissions);
        }

        return is_int($result);
    }

    /**
     * 格式化日志行。
     * @param字符串$level错误级别
     * @param字符串$date格式化日期字符串
     * @param字符串$消息日志消息
     * @return字符串格式的日志行，末尾有一个新的行字符'\n'
     */
    protected function _format_line($level, $date, $message) {
        return "[{$date}][{$level}] {$message}\n";
    }
}
